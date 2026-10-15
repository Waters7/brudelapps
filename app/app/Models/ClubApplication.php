<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClubApplication extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function recruitment(): BelongsTo
    {
        return $this->belongsTo(Recruitment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(StatusHistory::class, 'subject_id')->where('subject_type', 'club_applications')->oldest('id');
    }
}
