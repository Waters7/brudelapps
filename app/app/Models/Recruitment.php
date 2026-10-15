<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recruitment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['opens_at' => 'datetime', 'closes_at' => 'datetime', 'requires_document' => 'boolean'];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(ClubApplication::class);
    }

    public function isOpen(): bool
    {
        return now()->between($this->opens_at, $this->closes_at);
    }
}
