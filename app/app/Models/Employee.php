<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Master data of a dosen or tendik; the login itself is the related user, whose username is the NIP.
 */
class Employee extends Model
{
    public const KINDS = ['dosen' => 'Dosen', 'tendik' => 'Tendik'];

    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }

    public function placement(): string
    {
        return $this->department?->name ?? $this->unit ?? '—';
    }
}
