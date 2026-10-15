<?php

namespace App\Http\Controllers\Match;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AwardResult;
use App\Models\Certificate;
use App\Models\Competition;
use App\Models\Matchup;
use App\Services\Achievement\AchievementService;
use App\Services\Match\BracketService;
use App\Services\Scoring\ResultService;
use App\Services\Scoring\ScoringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Operations hub for one competition: entrants, seeds, bracket, rubric, staff, official results and awards.
 */
class CompetitionOpsController extends Controller
{
    public function show(Request $request, Competition $competition, ScoringService $scoring): View
    {
        abort_unless($request->user()->managesCompetition($competition), 403);
        $competition->load('event', 'criteria');
        $staff = Assignment::with('user')->where('competition_id', $competition->id)->whereIn('role', ['penilai', 'operator'])->where('ends_at', '>', now())->get()->groupBy('role');

        return view('competitions.ops', [
            'competition' => $competition,
            'registrations' => $competition->registrations()->whereNotIn('status', ['draft', 'cancelled'])->with('user', 'participants.user.program')->orderByRaw('seed IS NULL')->orderBy('seed')->orderBy('submitted_at')->get(),
            'matches' => $competition->matchups()->with('sideA', 'sideB', 'winner')->get()->groupBy('round'),
            'missing' => $competition->assessment_type === 'rubric' ? $scoring->missing($competition) : [],
            'ranking' => $competition->assessment_type === 'rubric' && ! $competition->resultsPublished() ? $scoring->ranking($competition) : [],
            'results' => $competition->results()->with('registration.user')->get(),
            'awards' => AwardResult::where('competition_id', $competition->id)->withCount('recipients')->get(),
            'certificates' => Certificate::where('competition_id', $competition->id)->count(),
            'staff' => $staff,
        ]);
    }

    public function seeds(Request $request, Competition $competition, BracketService $bracket): RedirectResponse
    {
        $data = $request->validate(['seeds' => 'array', 'seeds.*' => 'nullable|integer|min:1|max:512']);
        $bracket->setSeeds($request->user(), $competition, $data['seeds'] ?? []);

        return back()->with('success', 'Unggulan disimpan.');
    }

    public function generate(Request $request, Competition $competition, BracketService $bracket): RedirectResponse
    {
        $bracket->generate($request->user(), $competition);

        return back()->with('success', 'Bagan sistem gugur dibuat. Peserta tanpa lawan otomatis lolos (bye).');
    }

    public function schedule(Request $request, Matchup $matchup, BracketService $bracket): RedirectResponse
    {
        $data = $request->validate(['scheduled_at' => 'nullable|date', 'location' => 'nullable|string|max:190']);
        $bracket->schedule($request->user(), $matchup, $data['scheduled_at'] ?? null, $data['location'] ?? null);

        return back()->with('success', 'Jadwal pertandingan disimpan.');
    }

    public function winner(Request $request, Matchup $matchup, BracketService $bracket): RedirectResponse
    {
        $data = $request->validate([
            'winner' => 'required|integer',
            'score_a' => 'nullable|integer|min:0|max:100000',
            'score_b' => 'nullable|integer|min:0|max:100000',
            'score_detail' => 'nullable|string|max:190',
            'version' => 'required|integer|min:0',
        ]);
        $bracket->recordWinner($request->user(), $matchup, $data['winner'], $data['score_a'] ?? null, $data['score_b'] ?? null, $data['score_detail'] ?? null, $data['version']);

        return back()->with('success', 'Hasil resmi dicatat dan pemenang masuk babak berikutnya.');
    }

    public function correct(Request $request, Matchup $matchup, BracketService $bracket): RedirectResponse
    {
        $data = $request->validate(['reason' => 'required|string|min:5|max:1000']);
        $bracket->correct($request->user(), $matchup, $data['reason']);

        return back()->with('success', 'Hasil dibatalkan untuk koreksi. Catat ulang pemenang yang benar.');
    }

    public function criteria(Request $request, Competition $competition, ScoringService $scoring): RedirectResponse
    {
        $data = $request->validate([
            'criteria' => 'required|array|min:1|max:15',
            'criteria.*.name' => 'nullable|string|max:120',
            'criteria.*.weight' => 'nullable|numeric|min:0|max:100',
            'criteria.*.description' => 'nullable|string|max:500',
        ]);
        $scoring->saveCriteria($request->user(), $competition, $data['criteria']);

        return back()->with('success', 'Rubrik disimpan (total bobot 100%).');
    }

    public function publish(Request $request, Competition $competition, ResultService $results): RedirectResponse
    {
        $results->publish($request->user(), $competition);

        return back()->with('success', 'Hasil disahkan dan dikunci. Peserta menerima notifikasi.');
    }

    public function reopen(Request $request, Competition $competition, ResultService $results): RedirectResponse
    {
        $data = $request->validate(['reason' => 'required|string|min:10|max:1000']);
        $results->reopen($request->user(), $competition, $data['reason']);

        return back()->with('success', 'Pengesahan dibuka untuk koreksi. Hasil sebelumnya tersimpan di audit.');
    }

    public function award(Request $request, Competition $competition, AchievementService $achievements): RedirectResponse
    {
        $data = $request->validate(['registration_id' => 'required|integer', 'award' => 'required|string|max:80']);
        $achievements->fromResult($request->user(), $competition, $data['registration_id'], $data['award']);

        return back()->with('success', 'Penghargaan dicatat sebagai prestasi terverifikasi.');
    }
}
