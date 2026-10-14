<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ClubApplication extends Model {
    protected $guarded = [];
    public function recruitment() { return $this->belongsTo(Recruitment::class); }
    public function user() { return $this->belongsTo(User::class); }
}
