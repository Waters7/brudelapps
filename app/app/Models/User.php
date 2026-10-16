<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Services\Club\ClubService;
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

#[Fillable(['name', 'email', 'password', 'nim', 'program_id', 'active', 'publication_consent', 'email_verified_at', 'public_slug', 'status_changed_at', 'photo_path', 'photo_disk', 'google_sub'])]
#[Hidden(['password', 'remember_token', 'google_sub'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * HIMAJU membership is mandatory, so it follows every change of a student's program or active status,
     * whichever code path (import, status change, seeder) saved it.
     */
    protected static function booted(): void
    {
        static::saved(function (User $user) {
            if ($user->nim && ($user->wasRecentlyCreated || $user->wasChanged(['program_id', 'active', 'nim']))) {
                app(ClubService::class)->syncHimaju($user);
            }
        });
    }

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

    public function departmentId(): ?int
    {
        return $this->program?->department_id;
    }

    /**
     * Assignments whose validity window includes the current moment.
     */
    public function currentAssignments(): HasMany
    {
        return $this->assignments()->where('starts_at', '<=', now())->where('ends_at', '>', now());
    }

    /**
     * Check an active assignment, optionally limited to one club, event, jurusan or competition.
     */
    public function assigned(string $role, ?int $club = null, ?int $event = null, ?int $department = null, ?int $competition = null): bool
    {
        return $this->currentAssignments()->where('role', $role)
            ->when($club !== null, fn (Builder $q) => $q->where('club_id', $club))
            ->when($event !== null, fn (Builder $q) => $q->where('event_id', $event))
            ->when($department !== null, fn (Builder $q) => $q->where('department_id', $department))
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

    /**
     * Account generated automatically for one competition; its password is managed by the proposer.
     */
    public function isCompetitionAccount(): bool
    {
        return CompetitionAccount::where('user_id', $this->id)->exists();
    }

    public function managesClub(int $club): bool
    {
        return $this->isStudentAffairs() || $this->assigned('pengurus', $club) || $this->administersHimaju($club);
    }

    /**
     * Kemahasiswaan jurusan administers the HIMAJU of its own jurusan; everything else belongs to kemahasiswaan direktorat.
     */
    public function administersHimaju(int $club): bool
    {
        $departments = $this->scopeIds('jurusan', 'department_id');

        return $departments->isNotEmpty() && Club::whereKey($club)->where('category', 'himaju')->whereIn('department_id', $departments)->exists();
    }

    /**
     * Ketua (or kemahasiswaan) proposes events on behalf of the club.
     */
    public function leadsClub(int $club): bool
    {
        return $this->isStudentAffairs() || $this->currentAssignments()->where('role', 'pengurus')
            ->where('club_id', $club)->whereIn('position', config('brudel.chair_positions'))->exists();
    }

    /**
     * Ketua, Wakil Ketua and Sekretaris (or kemahasiswaan) maintain the officer roster.
     */
    public function managesOfficers(int $club): bool
    {
        return $this->isStudentAffairs() || $this->administersHimaju($club) || $this->currentAssignments()->where('role', 'pengurus')
            ->where('club_id', $club)->whereIn('position', config('brudel.officer_managers'))->exists();
    }

    /**
     * Every pengurus and the pelatih/pembina run club activities and attendance.
     */
    public function coachesClub(int $club): bool
    {
        return $this->managesClub($club) || $this->assigned('pembina', $club);
    }

    public function managesEvent(int $event): bool
    {
        return $this->isStudentAffairs() || $this->assigned('admin_event', null, $event);
    }

    public function managesCompetition(Competition $competition): bool
    {
        return $this->managesEvent($competition->event_id) || $this->assigned('pengelola_lomba', competition: $competition->id);
    }

    /**
     * Competitions the user manages, or null when the user manages every competition.
     *
     * @return Collection<int, int>|null
     */
    public function managedCompetitionIds(): ?Collection
    {
        if ($this->isStudentAffairs()) {
            return null;
        }

        return Competition::whereIn('event_id', $this->scopeIds('admin_event', 'event_id'))->pluck('id')
            ->merge($this->scopeIds('pengelola_lomba', 'competition_id'))->unique()->values();
    }

    public function managesAnyCompetition(): bool
    {
        return $this->isStudentAffairs() || $this->assigned('admin_event') || $this->assigned('pengelola_lomba');
    }

    public function judges(Competition $competition): bool
    {
        return $this->assigned('penilai', competition: $competition->id);
    }

    public function operates(Competition $competition): bool
    {
        return $this->managesCompetition($competition) || $this->assigned('operator', competition: $competition->id);
    }

    public function reviewsDepartment(?int $department): bool
    {
        return $department !== null && ($this->isStudentAffairs() || $this->assigned('jurusan', department: $department));
    }

    public function isReviewer(): bool
    {
        return $this->isStudentAffairs() || $this->assigned('pengurus') || $this->assigned('admin_event') || $this->assigned('pengelola_lomba');
    }

    public function isStaff(): bool
    {
        return $this->currentAssignments()->exists();
    }

    public function initial(): string
    {
        return mb_strtoupper(mb_substr($this->name, 0, 1));
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
