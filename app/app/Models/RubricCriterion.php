<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RubricCriterion extends Model
{
    protected $table = 'rubric_criteria';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['weight' => 'float'];
    }
}
