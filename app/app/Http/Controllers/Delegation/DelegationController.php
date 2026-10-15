<?php

namespace App\Http\Controllers\Delegation;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\Delegation;
use App\Models\StatusHistory;
use App\Models\User;
use App\Services\Delegation\DelegationService;
use App\Services\PrivateFiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DelegationController extends Controller
{
    public function __construct(private DelegationService $delegations) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $all = $user->isStudentAffairs() || $user->isLeadership();
        $events = $user->scopeIds('admin_event', 'event_id');
        $delegations = Delegation::with('competition.event')->withCount('members')
            ->when(! $all, fn ($q) => $q->where(fn ($w) => $w->whereHas('members', fn ($m) => $m->where('user_id', $user->id))
                ->orWhereHas('competition', fn ($c) => $c->whereIn('event_id', $events))))
            ->latest()->get();
        $selections = $user->isStudentAffairs() || $events->isNotEmpty()
            ? Competition::with('event')->whereHas('event', fn ($q) => $q->where('pathway', 'delegation')->when(! $user->isStudentAffairs(), fn ($e) => $e->whereIn('id', $events)))->get()
            : collect();

        return view('delegations.index', ['delegations' => $delegations, 'selections' => $selections, 'canPropose' => $user->isStudentAffairs() || $events->isNotEmpty()]);
    }

    public function create(Request $request): View
    {
        $competition = $request->query('lomba') ? Competition::with('event', 'results.registration.participants.user')->findOrFail($request->query('lomba')) : null;
        abort_unless($request->user()->isStudentAffairs() || ($competition && $request->user()->managesCompetition($competition)), 403);
        $candidates = $competition
            ? $competition->registrations()->where('status', 'accepted')->with('participants.user', 'competition')->get()
            : collect();

        return view('delegations.create', ['competition' => $competition, 'candidates' => $candidates]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'competition_id' => 'nullable|integer|exists:competitions,id',
            'title' => 'required|string|max:190',
            'destination_event' => 'required|string|max:190',
            'organizer' => 'required|string|max:190',
            'level' => 'required|integer|between:1,6',
            'field' => 'required|in:'.implode(',', config('brudel.fields')),
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date',
            'location' => 'required|string|max:190',
            'reason' => 'required|string|min:10|max:3000',
            'participants' => 'required_without:participant_nims|array|min:1',
            'participants.*' => 'integer',
            'participant_nims' => 'nullable|string|max:1000',
            'companions' => 'nullable|string|max:1000',
        ]);
        if (! empty($data['participant_nims'])) {
            $nims = preg_split('/[\s,;]+/', $data['participant_nims'], -1, PREG_SPLIT_NO_EMPTY);
            $data['participants'] = array_merge($data['participants'] ?? [], User::whereIn('nim', $nims)->pluck('id')->all());
            if (count($data['participants']) < count(array_unique($nims))) {
                throw ValidationException::withMessages(['participant_nims' => 'Sebagian NIM peserta tidak ditemukan.']);
            }
        }
        $delegation = $this->delegations->propose($request->user(), $data);

        return redirect('/delegasi/'.$delegation->id)->with('success', 'Usulan delegasi dibuat sebagai draf. Periksa lalu ajukan ke pimpinan.');
    }

    public function show(Request $request, Delegation $delegation): View
    {
        abort_unless($this->delegations->canView($request->user(), $delegation), 403);
        $delegation->load('members.user.program', 'competition.event', 'proposer', 'decider');

        return view('delegations.show', [
            'delegation' => $delegation,
            'canManage' => $this->delegations->canManage($request->user(), $delegation),
            'canDecide' => $request->user()->isLeadership() && $delegation->status === 'submitted' && $delegation->proposed_by !== $request->user()->id && ! $delegation->members->contains('user_id', $request->user()->id),
            'histories' => StatusHistory::where('subject_type', 'delegations')->where('subject_id', $delegation->id)->oldest('id')->get(),
        ]);
    }

    public function submit(Request $request, Delegation $delegation): RedirectResponse
    {
        $this->delegations->submit($request->user(), $delegation);

        return back()->with('success', 'Usulan dikirim ke pimpinan.');
    }

    public function decide(Request $request, Delegation $delegation): RedirectResponse
    {
        $data = $request->validate(['decision' => 'required|in:approved,revision,rejected', 'note' => 'nullable|string|max:2000']);
        $this->delegations->decide($request->user(), $delegation, $data['decision'], $data['note'] ?? null);

        return back()->with('success', 'Keputusan pimpinan tersimpan.');
    }

    public function letter(Request $request, Delegation $delegation): RedirectResponse
    {
        $data = $request->validate(['letter_number' => 'required|string|max:80', 'file' => PrivateFiles::RULES]);
        $this->delegations->uploadLetter($request->user(), $delegation, $data['letter_number'], $request->file('file'));

        return back()->with('success', 'Surat tugas resmi tersimpan privat.');
    }

    public function downloadLetter(Request $request, Delegation $delegation, PrivateFiles $files): StreamedResponse
    {
        abort_unless($this->delegations->canView($request->user(), $delegation), 403);
        abort_unless($delegation->letter_path, 404);

        return $files->download($delegation->letter_path, $delegation->letter_disk, 'surat-tugas-'.$delegation->id.'.pdf');
    }

    public function outcome(Request $request, Delegation $delegation): RedirectResponse
    {
        $data = $request->validate([
            'outcome' => 'required|string|min:5|max:2000',
            'award' => 'nullable|string|max:80',
            'awarded_on' => 'nullable|date|before_or_equal:today',
            'evidence' => 'nullable|file|mimes:pdf|extensions:pdf|max:5120',
        ]);
        $this->delegations->recordOutcome($request->user(), $delegation, $data, $request->file('evidence'));

        return back()->with('success', 'Hasil keikutsertaan dicatat. Penghargaan menunggu verifikasi kemahasiswaan.');
    }
}
