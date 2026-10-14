<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Competition extends Model {
    protected $guarded = [];
    protected function casts(): array { return ['opens_at'=>'datetime','closes_at'=>'datetime','starts_at'=>'datetime','fee'=>'integer','requires_document'=>'boolean','requires_program_approval'=>'boolean']; }
    public function event() { return $this->belongsTo(Event::class); }
    public function registrations() { return $this->hasMany(Registration::class); }
}
