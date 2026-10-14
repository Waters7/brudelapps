<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Club extends Model {
    protected $guarded = [];
    public function recruitments() { return $this->hasMany(Recruitment::class); }
    public function memberships() { return $this->hasMany(Membership::class); }
}
