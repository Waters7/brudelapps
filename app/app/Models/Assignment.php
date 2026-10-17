<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Assignment extends Model
{
    protected $guarded = [];

    /**
     * Bumped on every change so users drop their memoized permission checks (see User::activeAssignments).
     */
    public static int $revision = 0;

    protected static function booted(): void
    {
        static::saved(fn () => self::$revision++);
        static::deleted(fn () => self::$revision++);
    }

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

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
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
        if ($this->department) {
            return 'Jurusan '.$this->department->name;
        }

        return $this->club?->name ?? $this->event?->name ?? $this->competition?->name ?? 'Seluruh kampus';
    }
}
