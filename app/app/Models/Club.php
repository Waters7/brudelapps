<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Club extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function recruitments(): HasMany
    {
        return $this->hasMany(Recruitment::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function officers(): HasMany
    {
        return $this->hasMany(Assignment::class)->where('role', 'pengurus');
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function clubActivities(): HasMany
    {
        return $this->hasMany(ClubActivity::class);
    }

    public function awards(): HasMany
    {
        return $this->hasMany(AwardResult::class)->where('status', 'verified');
    }
}
