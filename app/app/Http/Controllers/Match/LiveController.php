<?php

namespace App\Http\Controllers\Match;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\Matchup;
use App\Models\Registration;
use App\Services\Match\LiveScoreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LiveController extends Controller
{
    public function __construct(private LiveScoreService $live) {}

    public function index(Request $request): View
    {
        $competitions = Competition::with('event')->where('live_score', true)
            ->whereHas('event', fn ($q) => $q->where('status', 'approved'))
            ->when(! $request->user(), fn ($q) => $q->where('live_visibility', 'public'))
            ->orderBy('starts_at')->get();

        return view('live.index', ['competitions' => $competitions]);
    }

    public function show(Request $request, Competition $competition): View|RedirectResponse
    {
        if (! $this->live->canView($request->user(), $competition)) {
            abort_unless($competition->live_score && ! $request->user(), 404);

            return redirect()->guest('/login');
        }

        return view('live.show', ['competition' => $competition->load('event')]);
    }

    public function operator(Request $request): View
    {
        $user = $request->user();
        $managed = $user->managedCompetitionIds();
        $ids = $managed === null ? null : $managed->merge($user->scopeIds('operator', 'competition_id'))->unique();
        abort_unless($ids === null || $ids->isNotEmpty(), 403);
        $competitions = Competition::with('event')->where(fn ($q) => $q->where('live_score', true)->orWhere('assessment_type', 'bracket'))
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
            ->get();
        $selected = $competitions->firstWhere('id', (int) $request->query('lomba')) ?? $competitions->first();

        return view('live.operator', [
            'competitions' => $competitions,
            'selected' => $selected,
            'matches' => $selected?->assessment_type === 'bracket' ? $selected->matchups()->with('sideA', 'sideB')->where('is_bye', false)->get() : collect(),
            'entries' => $selected && $selected->assessment_type !== 'bracket' ? $selected->registrations()->where('status', 'accepted')->with('user')->orderByRaw('performance_order IS NULL')->orderBy('performance_order')->get() : collect(),
        ]);
    }

    public function updateMatch(Request $request, Matchup $matchup): RedirectResponse
    {
        $data = $request->validate([
            'score_a' => 'nullable|integer|min:0|max:100000',
            'score_b' => 'nullable|integer|min:0|max:100000',
            'score_detail' => 'nullable|string|max:190',
            'period_label' => 'nullable|string|max:40',
            'status' => 'required|in:scheduled,live',
            'version' => 'required|integer|min:0',
        ]);
        $this->live->updateMatch($request->user(), $matchup, $data);

        return back()->with('success', 'Skor sementara diperbarui.');
    }

    public function performance(Request $request, Registration $registration): RedirectResponse
    {
        $data = $request->validate(['status' => 'required|in:waiting,performing,done', 'order' => 'nullable|integer|min:1|max:999']);
        $this->live->setPerformance($request->user(), $registration, $data['status'], $data['order'] ?? null);

        return back()->with('success', 'Status tampil diperbarui.');
    }
}
