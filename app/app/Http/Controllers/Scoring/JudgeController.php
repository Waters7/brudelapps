<?php

namespace App\Http\Controllers\Scoring;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\Registration;
use App\Models\Score;
use App\Services\Scoring\ScoringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JudgeController extends Controller
{
    public function index(Request $request): View
    {
        $ids = $request->user()->scopeIds('penilai', 'competition_id');

        return view('scoring.index', [
            'competitions' => Competition::with('event')->whereIn('id', $ids)->withCount([
                'registrations as accepted_count' => fn ($q) => $q->where('status', 'accepted'),
                'criteria as criteria_count',
                'scores as my_scores' => fn ($q) => $q->where('judge_id', $request->user()->id),
            ])->get(),
        ]);
    }

    public function show(Request $request, Competition $competition): View
    {
        abort_unless($request->user()->judges($competition), 403);
        $competition->load('event', 'criteria');
        $entries = $competition->registrations()->where('status', 'accepted')->with('participants.user')->orderBy('performance_order')->orderBy('id')->get();

        return view('scoring.show', [
            'competition' => $competition,
            'entries' => $entries,
            'scores' => Score::where('competition_id', $competition->id)->where('judge_id', $request->user()->id)->get()->groupBy('registration_id'),
        ]);
    }

    public function store(Request $request, Competition $competition, Registration $registration, ScoringService $scoring): RedirectResponse
    {
        $data = $request->validate([
            'scores' => 'required|array',
            'scores.*.score' => 'nullable|numeric|min:0|max:100',
            'scores.*.note' => 'nullable|string|max:500',
        ]);
        $scoring->score($request->user(), $competition, $registration, $data['scores']);

        return redirect('/penilaian/'.$competition->id.'#entry-'.$registration->id)->with('success', 'Nilai '.$registration->displayName().' tersimpan.');
    }
}
