<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

class Competition extends Model
{
    public const RESULT_ACCESS = [
        'public' => 'Umum, termasuk pengunjung tanpa akun',
        'auth' => 'Semua pengguna yang masuk',
        'locked' => 'Terkunci, hanya admin jurusan ke atas',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'results_published_at' => 'datetime',
            'results_locked_at' => 'datetime',
            'fee' => 'integer',
            'judge_count' => 'integer',
            'requires_document' => 'boolean',
            'requires_program_approval' => 'boolean',
            'live_score' => 'boolean',
            'live_show_provisional' => 'boolean',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function companions(): MorphMany
    {
        return $this->morphMany(Companion::class, 'subject')->orderBy('id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(Score::class);
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(RubricCriterion::class)->orderBy('position');
    }

    public function matchups(): HasMany
    {
        return $this->hasMany(Matchup::class)->orderBy('round')->orderBy('position');
    }

    public function results(): HasMany
    {
        return $this->hasMany(CompetitionResult::class)->orderBy('rank');
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(CompetitionAccount::class)->orderBy('role')->orderBy('slot');
    }

    public function scoreLogs(): HasMany
    {
        return $this->hasMany(ScoreLog::class)->latest('id');
    }

    /**
     * Last moment the generated accounts and staff assignments stay valid.
     */
    public function staffUntil(): Carbon
    {
        return ($this->ends_at ?? $this->starts_at)->copy()->addDays(30);
    }

    public function isOpen(): bool
    {
        return $this->event?->status === 'approved' && now()->between($this->opens_at, $this->closes_at);
    }

    public function isPaid(): bool
    {
        return $this->fee > 0;
    }

    public function feeLabel(): string
    {
        if (! $this->isPaid()) {
            return 'Gratis';
        }

        return 'Rp '.number_format($this->fee, 0, ',', '.').' / '.($this->fee_basis === 'participant' ? 'peserta' : 'tim');
    }

    public function resultsLocked(): bool
    {
        return $this->results_locked_at !== null;
    }

    public function resultsPublished(): bool
    {
        return $this->results_published_at !== null;
    }
}
