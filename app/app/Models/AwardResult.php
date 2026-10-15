<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AwardResult extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['awarded_on' => 'date', 'verified_at' => 'datetime', 'is_team' => 'boolean', 'level' => 'integer'];
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(AwardRecipient::class);
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function levelLabel(): string
    {
        return config('brudel.levels')[$this->level] ?? (string) $this->level;
    }
}
