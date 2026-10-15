<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Assignment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function isCurrent(): bool
    {
        return $this->starts_at <= now() && $this->ends_at > now();
    }

    /**
     * Human readable scope of the assignment.
     */
    public function scopeLabel(): string
    {
        return $this->club?->name ?? $this->event?->name ?? $this->program?->name ?? $this->competition?->name ?? 'Seluruh kampus';
    }
}
