<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Recruitment extends Model {
    protected $guarded = [];
    protected function casts(): array { return ['opens_at'=>'datetime','closes_at'=>'datetime']; }
    public function club() { return $this->belongsTo(Club::class); }
    public function applications() { return $this->hasMany(ClubApplication::class); }
}
