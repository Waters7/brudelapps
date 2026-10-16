<?php

namespace App\Livewire;

use App\Models\Club;
use App\Models\ClubMessage;
use App\Services\Club\SpaceService;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Live group chat of an organization's member space. Access is re-checked on every refresh, so a member
 * who leaves loses the chat at once.
 */
class ClubChat extends Component
{
    #[Locked]
    public int $clubId;

    public string $body = '';

    public function send(SpaceService $space): void
    {
        $this->validate(['body' => 'required|string|max:1000']);
        if (! RateLimiter::attempt('club-chat|'.auth()->id(), 30, fn () => true, 60)) {
            throw ValidationException::withMessages(['body' => 'Terlalu banyak pesan. Coba lagi sebentar lagi.']);
        }
        $space->sendMessage(auth()->user(), Club::findOrFail($this->clubId), $this->body);
        $this->reset('body');
    }

    public function remove(int $id, SpaceService $space): void
    {
        $message = ClubMessage::where('club_id', $this->clubId)->findOrFail($id);
        $space->deleteMessage(auth()->user(), $message);
    }

    public function render(SpaceService $space): View
    {
        $club = Club::findOrFail($this->clubId);
        abort_unless($space->canEnter(auth()->user(), $club), 403);

        return view('livewire.club-chat', [
            'messages' => ClubMessage::with('author')->where('club_id', $club->id)->latest('id')->limit(80)->get(),
            'canModerate' => $space->canLead(auth()->user(), $club),
        ]);
    }
}
