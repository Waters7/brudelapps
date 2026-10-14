<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Registration extends Model {
    protected $guarded = [];
    public function competition() { return $this->belongsTo(Competition::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function participants() { return $this->hasMany(Participant::class); }
}
