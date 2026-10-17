<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Announcement of kemahasiswaan direktorat to many organizations at once; shown in each organization's space,
 * on members' dashboards, and as a notification. Club officers' own announcements are ClubPost.
 */
class Broadcast extends Model
{
    public const AUDIENCES = [
        'semua' => 'Semua organisasi (BEM, MPM, HIMAJU, UKM)',
        'ukm' => 'Semua UKM',
        'himaju' => 'Semua HIMAJU',
        'lembaga' => 'BEM & MPM',
        'pilihan' => 'Organisasi tertentu',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'is_public' => 'boolean'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function clubs(): BelongsToMany
    {
        return $this->belongsToMany(Club::class, 'broadcast_club');
    }

    /**
     * Announcements still on display (no end date, or the end date is ahead).
     */
    public function scopeShown(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /**
     * Announcements kemahasiswaan also shows on the sign-in page.
     */
    public function scopePublic(Builder $query): void
    {
        $query->where('is_public', true);
    }

    /**
     * Announcements addressed to any of the clubs.
     *
     * @param  iterable<int, int>  $clubs
     */
    public function scopeForClubs(Builder $query, iterable $clubs): void
    {
        $query->whereHas('clubs', fn (Builder $q) => $q->whereIn('clubs.id', collect($clubs)->all()));
    }

    public function audienceLabel(): string
    {
        return self::AUDIENCES[$this->audience] ?? $this->audience;
    }
}
