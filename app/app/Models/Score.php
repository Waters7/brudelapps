<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Score extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['score' => 'float'];
    }

    public function judge(): BelongsTo
    {
        return $this->belongsTo(User::class, 'judge_id');
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(RubricCriterion::class, 'rubric_criterion_id');
    }
}
