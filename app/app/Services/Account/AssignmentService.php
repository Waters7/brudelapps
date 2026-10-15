<?php

namespace App\Services\Account;

use App\Models\Assignment;
use App\Models\Competition;
use App\Models\User;
use App\Notifications\BrudelNotice;
use App\Services\DomainService;
use App\Services\Wita;
use Illuminate\Support\Facades\DB;

/**
 * Role assignments with validity windows. Kemahasiswaan grants any role; a Ketua UKM may manage officers
 * and coaches of their own club only. Nobody changes their own rights.
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

        abort_unless($this->mayManage($actor, $data['role'], $scopeId ? (int) $scopeId : null), 403);

        $identifier = trim($data['user']);
        $user = User::where('email', mb_strtolower($identifier))->orWhere('nim', $identifier)->first();
        $this->ensure($user !== null && $user->active, 'Pengguna aktif dengan email/NIM tersebut tidak ditemukan.');
        abort_if($user->id === $actor->id, 403, 'Tidak dapat mengubah hak akses sendiri.');

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
            $user->notify(new BrudelNotice('Penugasan baru', 'Anda ditugaskan sebagai '.config('brudel.roles')[$data['role']]['label'].' · '.$assignment->scopeLabel().'.', '/'));

            return $assignment;
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
        if (in_array($role, ['pengurus', 'pembina'], true)) {
            return $scopeId !== null && $actor->leadsClub($scopeId);
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
