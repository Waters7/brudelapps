<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Department extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }

    public function students(): HasManyThrough
    {
        return $this->hasManyThrough(User::class, Program::class)->whereNotNull('users.nim');
    }
}
