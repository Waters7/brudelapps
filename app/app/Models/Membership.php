<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Membership extends Model {
    protected $guarded = [];
    protected function casts(): array { return ['joined_at'=>'datetime']; }
    public function club() { return $this->belongsTo(Club::class); }
}
