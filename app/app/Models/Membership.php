<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Membership extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['joined_at' => 'datetime', 'left_at' => 'datetime'];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(StatusHistory::class, 'subject_id')->where('subject_type', 'memberships')->oldest('id');
    }
}
