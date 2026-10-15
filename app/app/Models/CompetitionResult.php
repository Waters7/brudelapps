<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitionResult extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['total' => 'float'];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }
}
