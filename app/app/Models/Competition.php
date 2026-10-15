<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Competition extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'results_published_at' => 'datetime',
            'fee' => 'integer',
            'requires_document' => 'boolean',
            'requires_program_approval' => 'boolean',
            'live_score' => 'boolean',
            'live_show_provisional' => 'boolean',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(RubricCriterion::class)->orderBy('position');
    }

    public function matchups(): HasMany
    {
        return $this->hasMany(Matchup::class)->orderBy('round')->orderBy('position');
    }

    public function results(): HasMany
    {
        return $this->hasMany(CompetitionResult::class)->orderBy('rank');
    }

    public function isOpen(): bool
    {
        return $this->event?->status === 'approved' && now()->between($this->opens_at, $this->closes_at);
    }

    public function isPaid(): bool
    {
        return $this->fee > 0;
    }

    public function feeLabel(): string
    {
        if (! $this->isPaid()) {
            return 'Gratis';
        }

        return 'Rp '.number_format($this->fee, 0, ',', '.').' / '.($this->fee_basis === 'participant' ? 'peserta' : 'tim');
    }

    public function resultsPublished(): bool
    {
        return $this->results_published_at !== null;
    }
}
