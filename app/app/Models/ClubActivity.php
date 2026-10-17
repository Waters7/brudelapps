<?php

namespace App\Models;

use App\Services\Campus\MasterData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClubActivity extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'code_expires_at' => 'datetime',
            'reported_at' => 'datetime',
            'is_public' => 'boolean',
            'needs_room' => 'boolean',
            'permit_decided_at' => 'datetime',
        ];
    }

    /**
     * Activities that need no permit or whose permit was approved; pending ones stay with officers and approvers.
     */
    public function scopeConfirmed(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q->whereNull('permit_status')->orWhere('permit_status', 'approved'));
    }

    /**
     * What a user may see (Jelajah Kegiatan, calendar): confirmed public activities and those of the user's active
     * memberships, plus every activity of the clubs the user coaches.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $memberOf = Membership::where('user_id', $user->id)->where('status', 'active')->select('club_id');
        $query->where(fn (Builder $visible) => $visible
            ->where(fn (Builder $open) => $open->confirmed()->where(fn (Builder $q) => $q->where('is_public', true)->orWhereIn('club_id', $memberOf)))
            ->orWhereIn('club_id', Club::coachedBy($user)->select('id')));
    }

    public function awaitsPermit(): bool
    {
        return $this->permit_status === 'pending';
    }

    public function permitLevelLabel(): string
    {
        return $this->permit_level === 'jurusan' ? 'Kemahasiswaan Jurusan' : 'Kemahasiswaan Direktorat';
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'permit_decided_by');
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
        return app(MasterData::class)->activityTypeLabel($this->type);
    }

    /**
     * Report format from master data: attendance, match (opponent and score), or minutes.
     */
    public function reportKind(): string
    {
        return app(MasterData::class)->activityReport($this->type);
    }

    public function codeIsOpen(): bool
    {
        return $this->attendance_code !== null && $this->code_expires_at?->isFuture() && $this->status !== 'cancelled';
    }
}
