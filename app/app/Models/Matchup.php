<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Matchup extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'is_bye' => 'boolean'];
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function sideA(): BelongsTo
    {
        return $this->belongsTo(Registration::class, 'registration_a_id');
    }

    public function sideB(): BelongsTo
    {
        return $this->belongsTo(Registration::class, 'registration_b_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(Registration::class, 'winner_registration_id');
    }

    public function next(): BelongsTo
    {
        return $this->belongsTo(Matchup::class, 'next_matchup_id');
    }

    public function isOfficial(): bool
    {
        return $this->status === 'finished';
    }
}
