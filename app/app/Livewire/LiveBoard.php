<?php

namespace App\Livewire;

use App\Models\Competition;
use App\Services\Match\LiveScoreService;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Live score board refreshed by periodic polling. Access is re-checked on every refresh.
 */
class LiveBoard extends Component
{
    #[Locked]
    public int $competitionId;

    public function render(LiveScoreService $live): View
    {
        $competition = Competition::with('event')->findOrFail($this->competitionId);
        abort_unless($live->canView(auth()->user(), $competition), 403);

        return view('livewire.live-board', $live->board($competition) + ['checkedAt' => now()]);
    }
}
