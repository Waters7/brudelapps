<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A call for new officers of BEM, MPM or HIMAJU, with the posts on offer and the criteria set by the outgoing officers.
 */
class OfficerRecruitment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'positions' => 'array', 'criteria' => 'array', 'opens_at' => 'datetime', 'closes_at' => 'datetime',
            'term_starts_at' => 'datetime', 'term_ends_at' => 'datetime',
        ];
    }

    public function scopeOpen(Builder $query): void
    {
        $query->where('opens_at', '<=', now())->where('closes_at', '>', now());
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(OfficerCandidate::class);
    }

    public function isOpen(): bool
    {
        return now()->between($this->opens_at, $this->closes_at);
    }
}
