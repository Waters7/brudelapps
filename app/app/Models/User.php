<?php

namespace App\Models;

use App\Services\Campus\MasterData;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Services\Club\ClubService;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

#[Fillable(['name', 'email', 'username', 'phone', 'password', 'nim', 'program_id', 'active', 'publication_consent', 'email_verified_at', 'public_slug', 'status_changed_at', 'photo_path', 'photo_disk', 'google_sub'])]
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

    /** @var Collection<int, Assignment>|null */
    private ?Collection $loadedAssignments = null;

    /** @var Collection<int, int>|null */
    private ?Collection $loadedHimajuClubs = null;

    private int $assignmentsRevision = -1;

    /**
     * Active assignments loaded once per request: every permission check below filters this collection
     * instead of querying again. Any assignment change reloads it; PrivateResponse flushes it per request.
     *
     * @return Collection<int, Assignment>
     */
    public function activeAssignments(): Collection
    {
        if ($this->loadedAssignments === null || $this->assignmentsRevision !== Assignment::$revision) {
            $this->loadedHimajuClubs = null;
            $this->assignmentsRevision = Assignment::$revision;
            $this->loadedAssignments = $this->currentAssignments()->get();
        }

        return $this->loadedAssignments;
    }

    public function flushAssignments(): void
    {
        $this->loadedAssignments = null;
        $this->loadedHimajuClubs = null;
    }

    /**
     * Check an active assignment, optionally limited to one club, event, jurusan or competition.
     */
    public function assigned(string $role, ?int $club = null, ?int $event = null, ?int $department = null, ?int $competition = null): bool
    {
        return $this->activeAssignments()->contains(fn (Assignment $a) => $a->role === $role
            && ($club === null || $a->club_id === $club)
            && ($event === null || $a->event_id === $event)
            && ($department === null || $a->department_id === $department)
            && ($competition === null || $a->competition_id === $competition));
    }

    /**
     * IDs from the scope column of every active assignment with the given role.
     *
     * @return Collection<int, int>
     */
    public function scopeIds(string $role, string $column): Collection
    {
        return $this->activeAssignments()->where('role', $role)->whereNotNull($column)->pluck($column)->unique()->values();
    }

    /**
     * Distinct role names of the active assignments, for navigation.
     *
     * @return Collection<int, string>
     */
    public function activeRoles(): Collection
    {
        return $this->activeAssignments()->pluck('role')->unique()->values();
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
     * Before using BRUDEL every account confirms an active email and mobile number and replaces an initial password.
     * Competition accounts are managed by their proposer and are exempt.
     */
    public function needsAccountSetup(): bool
    {
        return ($this->must_change_password || $this->phone === null || str_ends_with((string) $this->email, '.invalid')) && ! $this->isCompetitionAccount();
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
        if ($departments->isEmpty()) {
            return false;
        }

        return ($this->loadedHimajuClubs ??= Club::where('category', 'himaju')->whereIn('department_id', $departments)->pluck('id'))->contains($club);
    }

    /**
     * Ketua (or kemahasiswaan) proposes events on behalf of the club.
     */
    public function leadsClub(int $club): bool
    {
        return $this->isStudentAffairs() || $this->holdsPosition($club, app(MasterData::class)->chairPositions());
    }

    /**
     * Ketua, Wakil Ketua and Sekretaris (or kemahasiswaan) maintain the officer roster.
     */
    public function managesOfficers(int $club): bool
    {
        return $this->isStudentAffairs() || $this->administersHimaju($club) || $this->holdsPosition($club, app(MasterData::class)->officerManagers());
    }

    /**
     * @param  list<string>  $positions
     */
    private function holdsPosition(int $club, array $positions): bool
    {
        return $this->activeAssignments()->contains(fn (Assignment $a) => $a->role === 'pengurus' && $a->club_id === $club && in_array($a->position, $positions, true));
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

    /**
     * Jurusan whose students this user administers: null means every jurusan (kemahasiswaan direktorat),
     * an empty collection means none.
     *
     * @return Collection<int, int>|null
     */
    public function studentDepartments(): ?Collection
    {
        return $this->isStudentAffairs() ? null : $this->scopeIds('jurusan', 'department_id');
    }

    /**
     * Direktorat administers every account; kemahasiswaan jurusan only students of its own jurusan.
     */
    public function administersStudent(User $student): bool
    {
        return $this->isStudentAffairs() || ($student->nim !== null && $this->reviewsDepartment($student->program?->department_id));
    }

    public function isReviewer(): bool
    {
        return $this->isStudentAffairs() || $this->assigned('pengurus') || $this->assigned('admin_event') || $this->assigned('pengelola_lomba');
    }

    public function isStaff(): bool
    {
        return $this->activeAssignments()->isNotEmpty();
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
            'must_change_password' => 'boolean',
        ];
    }
}
