<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameReward extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['earned_at' => 'datetime', 'best_at' => 'datetime'];
    }
}
