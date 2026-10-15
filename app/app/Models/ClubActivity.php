<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClubActivity extends Model
{
    public const TYPES = ['latihan' => 'Jadwal latihan', 'persahabatan' => 'Pertandingan persahabatan', 'meeting' => 'Meeting internal'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'code_expires_at' => 'datetime',
            'reported_at' => 'datetime',
            'is_public' => 'boolean',
        ];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(ActivityAttendance::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function codeIsOpen(): bool
    {
        return $this->attendance_code !== null && $this->code_expires_at?->isFuture() && $this->status !== 'cancelled';
    }
}
