<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A proposal or accountability report an organization sends to kemahasiswaan jurusan or direktorat for validation.
 */
class ClubSubmission extends Model
{
    public const TYPES = ['proposal' => 'Proposal kegiatan', 'lpj_kegiatan' => 'LPJ kegiatan', 'lpj_kepengurusan' => 'LPJ kepengurusan'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'version' => 'integer'];
    }

    /**
     * Direktorat sees everything; others see what their organizations sent and what was addressed to their jurusan.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->when(! $user->isStudentAffairs(), fn ($q) => $q->where(fn ($scope) => $scope
            ->whereIn('club_id', Club::coachedBy($user)->select('id'))
            ->orWhere(fn ($addressed) => $addressed->where('target', 'jurusan')->whereIn('department_id', $user->scopeIds('jurusan', 'department_id')))));
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(StatusHistory::class, 'subject_id')->where('subject_type', 'club_submissions')->oldest('id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function targetLabel(): string
    {
        return $this->target === 'jurusan' ? 'Kemahasiswaan Jurusan '.$this->department?->name : 'Kemahasiswaan Direktorat';
    }
}
