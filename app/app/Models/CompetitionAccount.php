<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Login generated automatically for one competition (pengelola, operator or juri). The password is stored
 * encrypted with APP_KEY so the proposer and kemahasiswaan can hand it over; viewing and rotation are audited.
 */
class CompetitionAccount extends Model
{
    protected $guarded = [];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return ['secret' => 'encrypted', 'rotated_at' => 'datetime'];
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            'pengelola_lomba' => 'Pengelola lomba',
            'operator' => 'Operator skor & live score',
            'penilai' => 'Juri '.$this->slot,
            default => $this->role,
        };
    }
}
