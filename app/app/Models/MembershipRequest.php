<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A pending way into an organization: a student's own request, or an invitation the student has to accept.
 */
class MembershipRequest extends Model
{
    public const REQUEST = 'request';

    public const INVITATION = 'invitation';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePending(Builder $query): void
    {
        $query->where('status', 'pending');
    }

    public function isInvitation(): bool
    {
        return $this->direction === self::INVITATION;
    }
}
