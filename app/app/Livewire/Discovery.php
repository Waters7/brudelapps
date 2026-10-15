<?php

namespace App\Livewire;

use App\Models\Club;
use App\Models\Competition;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Discovery extends Component
{
    #[Locked]
    public string $kind = 'ukm';

    public string $search = '';

    public string $field = '';

    public function render()
    {
        $q = $this->kind === 'ukm' ? Club::with('recruitments')->where('active', true)->withCount(['memberships' => fn ($q) => $q->where('status', 'active')]) : Competition::with('event')->whereHas('event', fn ($q) => $q->where('status', 'approved'));
        $q->when($this->field, fn ($q) => $q->where('field', $this->field));
        $q->when($this->search, fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower(mb_substr($this->search, 0, 100)).'%']));

        return view('livewire.discovery', ['items' => $q->get()]);
    }
}
