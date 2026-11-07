<?php

namespace App\Http\Controllers\Game;

use App\Http\Controllers\Controller;
use App\Models\GameReward;
use App\Services\Game\HknEngine;
use App\Services\Game\HknGameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HknGameController extends Controller
{
    public function frame(Request $request, HknGameService $game): RedirectResponse
    {
        $data = $request->validate(['enabled' => 'required|boolean']);
        $game->setFrame($request->user(), (bool) $data['enabled']);

        return redirect('/profil')->with('success', $data['enabled'] ? 'Frame HKN ke-62 dipasang.' : 'Frame foto dilepas.');
    }

    public function index(Request $request, HknGameService $game): View
    {
        $game->authorize($request->user());

        return view('games.hkn62');
    }

    public function start(Request $request, HknGameService $game): JsonResponse
    {
        $run = $game->start($request->user());

        return response()->json(['token' => $run->token, 'seed' => $run->seed]);
    }

    public function finish(Request $request, HknGameService $game): JsonResponse
    {
        $data = $request->validate(['token' => 'required|uuid', 'ticks' => 'required|integer|min:1|max:'.HknEngine::MAX_TICKS,
            'jumps' => 'present|array|max:2000', 'jumps.*' => 'required|integer|min:1|max:'.HknEngine::MAX_TICKS,
            'answers' => 'present|array|max:4', 'answers.*' => 'required|array:tick,choice',
            'answers.*.tick' => 'required|integer|min:1|max:'.HknEngine::MAX_TICKS, 'answers.*.choice' => 'required|integer|min:0|max:2']);
        $result = $game->finish($request->user(), $data['token'], (int) $data['ticks'], $data['jumps'], $data['answers']);

        return response()->json(['awarded' => $result['awarded'], 'points' => $result['reward']->points,
            'badge' => $result['reward']->badge, 'best_ticks' => $result['reward']->best_ticks]);
    }

    public function standings(Request $request, HknGameService $game): JsonResponse
    {
        $game->authorize($request->user());
        $query = GameReward::where('game', HknGameService::GAME);
        $mine = (clone $query)->where('user_id', $request->user()->id)->first();
        $rows = $query->where('best_rules_version', HknGameService::RULES_VERSION)->orderBy('best_ticks')->orderBy('best_at')->orderBy('id')->limit(10)->get();

        return response()->json(['mine' => $mine ? ['points' => $mine->points, 'badge' => $mine->badge, 'best_ticks' => (int) $mine->best_rules_version === HknGameService::RULES_VERSION ? $mine->best_ticks : null] : null,
            'rows' => $rows->values()->map(fn ($row, $i) => ['rank' => $i + 1, 'name' => $row->user_id === $request->user()->id ? 'Kamu' : 'Pelari #'.$row->id,
                'ticks' => $row->best_ticks, 'mine' => $row->user_id === $request->user()->id])]);
    }
}
