<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Participant extends Model {
    protected $guarded = [];
    public function user() { return $this->belongsTo(User::class); }
    public function registration() { return $this->belongsTo(Registration::class); }
}
