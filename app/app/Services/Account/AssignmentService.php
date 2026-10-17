<?php

namespace App\Services\Account;

use App\Models\Assignment;
use App\Models\Competition;
use App\Models\Department;
use App\Models\User;
use App\Notifications\BrudelNotice;
use App\Services\Campus\MasterData;
use App\Services\DomainService;
use App\Services\Wita;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Role assignments with validity windows. Kemahasiswaan direktorat grants any role; Ketua, Wakil Ketua and Sekretaris
 * pick officers from the active members of their own club, kemahasiswaan jurusan does the same for its HIMAJU.
 * Nobody changes their own rights.
 */
class AssignmentService extends DomainService
{
    /**
     * @param  array{user: string, role: string, scope_id?: ?int, position?: ?string, period?: ?string, starts_at: string, ends_at: string}  $data
     */
    public function grant(User $actor, array $data): Assignment
    {
        $roles = config('brudel.roles');
        $this->ensure(isset($roles[$data['role']]), 'Peran tidak dikenal.');
        $scope = $roles[$data['role']]['scope'];
        $scopeId = $data['scope_id'] ?? null;
        $this->ensure($scope === null || $scopeId !== null, 'Lingkup penugasan wajib dipilih untuk peran ini.');
        $this->ensure($scope !== 'department_id' || Department::whereKey($scopeId)->exists(), 'Jurusan tidak dikenal.');

        abort_unless($this->mayManage($actor, $data['role'], $scopeId ? (int) $scopeId : null), 403);

        $identifier = trim($data['user']);
        $user = User::where('email', mb_strtolower($identifier))->orWhere('nim', $identifier)->orWhere('username', mb_strtolower($identifier))->first();
        $this->ensure($user !== null && $user->active, 'Pengguna aktif dengan email, NIM, atau username tersebut tidak ditemukan.');
        abort_if($user->id === $actor->id, 403, 'Tidak dapat mengubah hak akses sendiri.');
        if ($data['role'] === 'pengurus') {
            $this->ensure($user->memberships()->where('club_id', $scopeId)->where('status', 'active')->exists(), 'Pengurus dipilih dari anggota aktif organisasi ini.');
            $this->ensure(in_array($data['position'] ?? 'Anggota pengurus', app(MasterData::class)->officerPositions(), true), 'Jabatan pengurus tidak dikenal.');
        }

        $starts = Wita::toUtc($data['starts_at']);
        $ends = Wita::toUtc($data['ends_at']);
        $this->ensure($starts !== null && $ends !== null && $ends->gt($starts), 'Masa berlaku penugasan tidak valid.');
        $this->ensure($ends->lte($starts->copy()->addYears(5)), 'Masa penugasan maksimal 5 tahun.');

        return DB::transaction(function () use ($actor, $data, $scope, $scopeId, $user, $starts, $ends) {
            User::whereKey($user->id)->lockForUpdate()->first();
            $overlap = Assignment::where('user_id', $user->id)->where('role', $data['role'])
                ->when($scope, fn ($q) => $q->where($scope, $scopeId))
                ->where('starts_at', '<', $ends)->where('ends_at', '>', $starts)->exists();
            $this->ensure(! $overlap, 'Pengguna sudah memiliki penugasan yang sama pada periode tersebut.');
            if ($data['role'] === 'penilai') {
                Competition::whereKey($scopeId)->lockForUpdate()->first();
                $judges = Assignment::where('role', 'penilai')->where('competition_id', $scopeId)->where('ends_at', '>', now())->count();
                $this->ensure($judges < (int) config('brudel.max_judges'), 'Nomor lomba ini sudah memiliki '.config('brudel.max_judges').' juri (batas maksimal).');
            }

            $assignment = Assignment::create([
                'user_id' => $user->id,
                'role' => $data['role'],
                'club_id' => $scope === 'club_id' ? $scopeId : null,
                'event_id' => $scope === 'event_id' ? $scopeId : null,
                'department_id' => $scope === 'department_id' ? $scopeId : null,
                'competition_id' => $scope === 'competition_id' ? $scopeId : null,
                'position' => match ($data['role']) {
                    'pengurus' => $data['position'] ?? 'Anggota pengurus',
                    'pimpinan' => in_array($data['position'] ?? null, config('brudel.pimpinan_positions'), true) ? $data['position'] : 'Wakil Direktur III',
                    default => null,
                },
                'period' => $data['period'] ?? null,
                'starts_at' => $starts,
                'ends_at' => $ends,
                'created_by' => $actor->id,
            ]);
            $this->audit($actor, 'role.granted', $assignment, ['user_id' => $user->id, 'role' => $data['role'], 'scope' => $scopeId]);
            $user->notify(new BrudelNotice('Penugasan baru', 'Anda ditugaskan sebagai '.$assignment->roleLabel().($data['role'] === 'jurusan' ? '' : ' · '.$assignment->scopeLabel()).'.', '/'));

            return $assignment;
        });
    }

    /**
     * Kemahasiswaan direktorat creates a staff account (e.g. kemahasiswaan jurusan) that signs in with a username.
     * The initial password must be replaced at first sign-in; the first role is granted in the same transaction.
     *
     * @param  array{name: string, username: string, email?: ?string, password: string, role: string, scope_id?: ?int, ends_at: string}  $data
     */
    public function createStaff(User $actor, array $data): User
    {
        abort_unless($actor->isStudentAffairs(), 403);
        $username = mb_strtolower(trim($data['username']));
        $email = mb_strtolower(trim((string) ($data['email'] ?? ''))) ?: $username.'@staf.brudel.invalid';
        $this->ensure(! User::where('username', $username)->orWhere('email', $email)->exists(), 'Username atau email sudah dipakai akun lain.');

        return DB::transaction(function () use ($actor, $data, $username, $email) {
            $user = new User;
            $user->forceFill(['name' => trim($data['name']), 'username' => $username, 'email' => $email, 'password' => Hash::make($data['password']),
                'active' => true, 'must_change_password' => true])->save();
            $this->audit($actor, 'account.staff_created', $user, ['username' => $username]);
            $this->grant($actor, ['user' => $username, 'role' => $data['role'], 'scope_id' => $data['scope_id'] ?? null,
                'starts_at' => now(config('brudel.timezone'))->format('Y-m-d H:i'), 'ends_at' => $data['ends_at']]);

            return $user;
        });
    }

    /**
     * Kemahasiswaan manages every role; a Ketua UKM manages officers/coaches of the club;
     * an event admin assigns judges and operators for competitions of the event.
     */
    private function mayManage(User $actor, string $role, ?int $scopeId): bool
    {
        if ($actor->isStudentAffairs()) {
            return true;
        }
        if ($role === 'pengurus') {
            return $scopeId !== null && $actor->managesOfficers($scopeId);
        }
        if ($role === 'pembina') {
            return $scopeId !== null && ($actor->leadsClub($scopeId) || $actor->administersHimaju($scopeId));
        }
        if (in_array($role, ['penilai', 'operator'], true)) {
            $competition = $scopeId ? Competition::find($scopeId) : null;

            return $competition !== null && $actor->managesCompetition($competition);
        }

        return false;
    }

    public function end(User $actor, Assignment $assignment, string $reason): void
    {
        abort_unless($this->mayManage($actor, $assignment->role, $assignment->club_id ?? $assignment->competition_id), 403);
        abort_if($assignment->user_id === $actor->id, 403, 'Tidak dapat mengakhiri hak akses sendiri.');
        $this->ensure(mb_strlen(trim($reason)) >= 5, 'Alasan minimal 5 karakter.');
        $this->ensure($assignment->ends_at->isFuture(), 'Penugasan sudah berakhir.');

        DB::transaction(function () use ($actor, $assignment, $reason) {
            $assignment = Assignment::lockForUpdate()->findOrFail($assignment->id);
            if ($assignment->role === 'kemahasiswaan') {
                $others = Assignment::where('role', 'kemahasiswaan')->where('id', '!=', $assignment->id)
                    ->where('starts_at', '<=', now())->where('ends_at', '>', now())->exists();
                $this->ensure($others, 'Minimal satu pengelola kemahasiswaan harus tetap aktif.');
            }
            $assignment->update(['ends_at' => $assignment->starts_at->isFuture() ? $assignment->starts_at : now(), 'ended_reason' => $reason]);
            $this->audit($actor, 'role.ended', $assignment, ['reason' => $reason]);
        });
    }
}
