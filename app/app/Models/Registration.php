<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Registration extends Model
{
    /** Statuses that hold a quota place. */
    public const HOLDING = ['program_review', 'submitted', 'accepted'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'fee_snapshot' => 'integer'];
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(DepartmentApproval::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PaymentProof::class)->latest('id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(StatusHistory::class, 'subject_id')->where('subject_type', 'registrations')->oldest('id');
    }

    /**
     * Public display name: the team name, or the registrant for individual entries.
     */
    public function displayName(): string
    {
        return $this->team_name ?: ($this->user?->name ?? 'Peserta');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'revision'], true);
    }
}
