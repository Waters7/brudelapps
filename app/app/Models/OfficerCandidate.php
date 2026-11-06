<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A student standing for a post, with the criteria the outgoing officers found met.
 */
class OfficerCandidate extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['met_criteria' => 'array', 'reviewed_at' => 'datetime'];
    }

    public function recruitment(): BelongsTo
    {
        return $this->belongsTo(OfficerRecruitment::class, 'officer_recruitment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(StatusHistory::class, 'subject_id')->where('subject_type', 'officer_candidates')->oldest('id');
    }
}
