<?php

namespace App\Http\Controllers\Club;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubSubmission;
use App\Models\Department;
use App\Services\Club\ClubSubmissionService;
use App\Services\PrivateFiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Proposal & LPJ: officers send documents and follow their validation; kemahasiswaan decides.
 */
class ClubSubmissionController extends Controller
{
    public function __construct(private ClubSubmissionService $submissions) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->isStudentAffairs() || $user->assigned('jurusan') || $user->assigned('pengurus') || $user->assigned('pembina'), 403);

        return view('submissions.index', [
            'submissions' => ClubSubmission::with('club', 'department')->visibleTo($user)
                ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
                ->when($request->query('jenis'), fn ($q, $type) => $q->where('type', $type))
                ->latest('submitted_at')->paginate(30)->withQueryString(),
            'clubs' => Club::whereIn('id', $user->scopeIds('pengurus', 'club_id'))->orderBy('name')->get(),
            'departments' => Department::orderBy('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'club_id' => 'required|integer|exists:clubs,id',
            'type' => ['required', Rule::in(array_keys(ClubSubmission::TYPES))],
            'title' => 'required|string|min:5|max:190',
            'summary' => 'nullable|string|max:2000',
            'target' => 'required|string|max:20',
            'document' => PrivateFiles::DOCUMENT_RULES,
        ]);
        [$target, $department] = array_pad(explode(':', $data['target'], 2), 2, null);
        $submission = $this->submissions->submit($request->user(), Club::findOrFail($data['club_id']), ['target' => $target, 'department_id' => $department] + $data, $request->file('document'));

        return redirect('/proposal/'.$submission->id)->with('success', 'Pengajuan terkirim. Pantau statusnya di halaman ini.');
    }

    public function show(Request $request, ClubSubmission $submission): View
    {
        $user = $request->user();
        abort_unless($this->submissions->canView($user, $submission), 403);

        return view('submissions.show', [
            'submission' => $submission->load('club', 'department', 'submitter', 'reviewer', 'histories'),
            'canDecide' => $submission->status === 'submitted' && $submission->submitted_by !== $user->id && $this->submissions->canReview($user, $submission),
            'canResubmit' => $submission->status === 'revision' && $this->submissions->canSubmit($user, $submission->club),
        ]);
    }

    public function review(Request $request, ClubSubmission $submission): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', Rule::in(ClubSubmissionService::DECISIONS)], 'note' => 'nullable|string|max:2000']);
        $this->submissions->review($request->user(), $submission, $data['decision'], $data['note'] ?? null);

        return back()->with('success', 'Keputusan disimpan dan pengusul diberi tahu.');
    }

    public function resubmit(Request $request, ClubSubmission $submission): RedirectResponse
    {
        $data = $request->validate(['summary' => 'nullable|string|max:2000', 'document' => PrivateFiles::DOCUMENT_RULES]);
        $this->submissions->resubmit($request->user(), $submission, $data['summary'] ?? null, $request->file('document'));

        return back()->with('success', 'Perbaikan terkirim untuk ditinjau kembali.');
    }

    public function download(Request $request, ClubSubmission $submission, PrivateFiles $files): StreamedResponse
    {
        abort_unless($this->submissions->canView($request->user(), $submission), 403);

        return $files->download($submission->file_path, $submission->file_disk, str($submission->title)->slug().'.'.pathinfo($submission->file_path, PATHINFO_EXTENSION));
    }
}
