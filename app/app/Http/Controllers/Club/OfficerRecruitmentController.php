<?php

namespace App\Http\Controllers\Club;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\OfficerCandidate;
use App\Models\OfficerRecruitment;
use App\Services\Campus\MasterData;
use App\Services\Club\OfficerRecruitmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Rekrutmen pengurus: the outgoing officers of BEM, MPM and HIMAJU open it and validate candidates; students stand.
 */
class OfficerRecruitmentController extends Controller
{
    public function __construct(private OfficerRecruitmentService $recruitments) {}

    public function index(Request $request, Club $club, MasterData $master): View
    {
        abort_unless($this->recruitments->canView($request->user(), $club), 403);

        return view('clubs.officer-recruitments', [
            'club' => $club,
            'canManage' => $this->recruitments->canManage($request->user(), $club),
            'positions' => $master->officerPositions(),
            'recruitments' => OfficerRecruitment::where('club_id', $club->id)->withCount([
                'candidates as waiting_count' => fn ($q) => $q->where('status', 'submitted'),
                'candidates as accepted_count' => fn ($q) => $q->where('status', 'accepted'),
            ])->latest('opens_at')->get(),
        ]);
    }

    public function store(Request $request, Club $club): RedirectResponse
    {
        $recruitment = $this->recruitments->save($request->user(), $club, null, $this->validated($request));

        return redirect('/kelola/rekrutmen-pengurus/'.$recruitment->id)->with('success', 'Rekrutmen pengurus disimpan.');
    }

    public function show(Request $request, OfficerRecruitment $recruitment, MasterData $master): View
    {
        abort_unless($this->recruitments->canView($request->user(), $recruitment->club), 403);

        return view('clubs.officer-recruitment', [
            'recruitment' => $recruitment,
            'club' => $recruitment->club,
            'canManage' => $this->recruitments->canManage($request->user(), $recruitment->club),
            'positions' => $master->officerPositions(),
            'candidates' => $recruitment->candidates()->with('user.program', 'reviewer')->where('status', '!=', 'cancelled')->orderByRaw("status = 'submitted' desc")->oldest('id')->get(),
        ]);
    }

    public function update(Request $request, OfficerRecruitment $recruitment): RedirectResponse
    {
        $this->recruitments->save($request->user(), $recruitment->club, $recruitment, $this->validated($request));

        return back()->with('success', 'Rekrutmen pengurus diperbarui.');
    }

    public function decide(Request $request, OfficerCandidate $candidate): RedirectResponse
    {
        $data = $request->validate(['decision' => 'required|in:accepted,rejected', 'met' => 'array', 'met.*' => 'integer|min:0', 'note' => 'nullable|string|max:1000']);
        $this->recruitments->decide($request->user(), $candidate, $data['decision'], array_map('intval', $data['met'] ?? []), $data['note'] ?? null);

        return back()->with('success', $data['decision'] === 'accepted' ? $candidate->user->name.' diterima sebagai '.$candidate->position.'.' : 'Kandidat ditolak dan diberi tahu.');
    }

    public function apply(Request $request, OfficerRecruitment $recruitment): RedirectResponse
    {
        $data = $request->validate(['position' => 'required|string|max:60', 'statement' => 'required|string|min:20|max:2000']);
        $this->recruitments->apply($request->user(), $recruitment, $data['position'], $data['statement']);

        return back()->with('success', 'Pengajuanmu terkirim. Pantau hasilnya di UKM Saya.');
    }

    public function cancel(Request $request, OfficerCandidate $candidate): RedirectResponse
    {
        $this->recruitments->cancel($request->user(), $candidate);

        return back()->with('success', 'Pengajuan dibatalkan.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|min:5|max:150',
            'period' => 'required|string|max:30',
            'description' => 'nullable|string|max:2000',
            'positions' => 'required|array|min:1',
            'positions.*' => 'string|max:60',
            'criteria' => 'required|string|max:3000',
            'opens_at' => 'required|date',
            'closes_at' => 'required|date',
            'term_starts_at' => 'required|date',
            'term_ends_at' => 'required|date',
        ]);
    }
}
