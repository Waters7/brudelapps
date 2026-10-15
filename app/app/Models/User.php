<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

#[Fillable(['name', 'email', 'password', 'nim', 'program_id', 'active', 'publication_consent', 'email_verified_at', 'public_slug', 'status_changed_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * Assignments whose validity window includes the current moment.
     */
    public function currentAssignments(): HasMany
    {
        return $this->assignments()->where('starts_at', '<=', now())->where('ends_at', '>', now());
    }

    /**
     * Check an active assignment, optionally limited to one club, event, program or competition.
     */
    public function assigned(string $role, ?int $club = null, ?int $event = null, ?int $program = null, ?int $competition = null): bool
    {
        return $this->currentAssignments()->where('role', $role)
            ->when($club !== null, fn (Builder $q) => $q->where('club_id', $club))
            ->when($event !== null, fn (Builder $q) => $q->where('event_id', $event))
            ->when($program !== null, fn (Builder $q) => $q->where('program_id', $program))
            ->when($competition !== null, fn (Builder $q) => $q->where('competition_id', $competition))
            ->exists();
    }

    /**
     * IDs from the scope column of every active assignment with the given role.
     *
     * @return Collection<int, int>
     */
    public function scopeIds(string $role, string $column): Collection
    {
        return $this->currentAssignments()->where('role', $role)->whereNotNull($column)->pluck($column)->unique()->values();
    }

    /**
     * Distinct role names of the active assignments, for navigation.
     *
     * @return Collection<int, string>
     */
    public function activeRoles(): Collection
    {
        return $this->currentAssignments()->pluck('role')->unique()->values();
    }

    public function isStudentAffairs(): bool
    {
        return $this->assigned('kemahasiswaan');
    }

    public function isLeadership(): bool
    {
        return $this->assigned('pimpinan');
    }

    public function isStudent(): bool
    {
        return $this->active && $this->nim !== null && $this->email_verified_at !== null;
    }

    public function managesClub(int $club): bool
    {
        return $this->isStudentAffairs() || $this->assigned('pengurus', $club);
    }

    /**
     * Ketua UKM (or kemahasiswaan) may change the officer roster of the club.
     */
    public function leadsClub(int $club): bool
    {
        return $this->isStudentAffairs() || $this->currentAssignments()->where('role', 'pengurus')
            ->where('club_id', $club)->where('position', 'Ketua')->exists();
    }

    public function managesEvent(int $event): bool
    {
        return $this->isStudentAffairs() || $this->assigned('admin_event', null, $event);
    }

    public function managesCompetition(Competition $competition): bool
    {
        return $this->managesEvent($competition->event_id);
    }

    public function judges(Competition $competition): bool
    {
        return $this->assigned('penilai', competition: $competition->id);
    }

    public function operates(Competition $competition): bool
    {
        return $this->managesCompetition($competition) || $this->assigned('operator', competition: $competition->id);
    }

    public function reviewsProgram(int $program): bool
    {
        return $this->isStudentAffairs() || $this->assigned('prodi', program: $program);
    }

    public function isReviewer(): bool
    {
        return $this->isStudentAffairs() || $this->assigned('pengurus') || $this->assigned('admin_event');
    }

    public function isStaff(): bool
    {
        return $this->currentAssignments()->exists();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'status_changed_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
            'publication_consent' => 'boolean',
        ];
    }
}
