<?php

namespace App\Services\Club;

use App\Models\Club;
use App\Models\ClubApplication;
use App\Models\Membership;
use App\Models\Program;
use App\Models\Recruitment;
use App\Models\User;
use App\Notifications\BrudelNotice;
use App\Services\DomainService;
use App\Services\Wita;
use Illuminate\Support\Facades\DB;

/**
 * UKM module: club profile, recruitment periods and member status. Officer roles are assignments, kept
 * separate from membership so a change of chair never deletes member history.
 */
class ClubService extends DomainService
{
    /**
     * @param  array{name: string, field: string, description: string, mentor: string, contact: string, symbol?: ?string, activities?: ?string, active?: bool}  $data
     */
    public function saveClub(User $actor, ?Club $club, array $data): Club
    {
        abort_unless($club ? $actor->managesClub($club->id) : $actor->isStudentAffairs(), 403);
        if (! $actor->isStudentAffairs()) {
            unset($data['name'], $data['active'], $data['category'], $data['department_id']);
        }

        return DB::transaction(function () use ($actor, $club, $data) {
            $club ??= new Club(['symbol' => 'spark', 'active' => true, 'category' => 'ukm']);
            $club->fill($data);
            $this->ensure(array_key_exists($club->category, config('brudel.club_categories')), 'Kategori organisasi tidak valid.');
            $this->ensure(! $club->isUkm() || $club->field, 'Bidang UKM wajib dipilih.');
            if ($club->category === 'himaju') {
                $this->ensure((bool) $club->department_id, 'HIMAJU wajib dikaitkan dengan satu jurusan.');
                $this->ensure(! Club::where('category', 'himaju')->where('department_id', $club->department_id)->whereKeyNot($club->id)->exists(), 'Jurusan ini sudah memiliki HIMAJU.');
            } else {
                $club->department_id = null;
            }
            $club->save();
            $this->audit($actor, $club->wasRecentlyCreated ? 'club.created' : 'club.updated', $club);

            return $club;
        });
    }

    /**
     * @param  array{name: string, opens_at: string, closes_at: string, quota: int, requirements: string, selection_notes?: ?string, requires_document?: bool}  $data
     */
    public function saveRecruitment(User $actor, Club $club, ?Recruitment $recruitment, array $data): Recruitment
    {
        abort_unless($actor->managesClub($club->id), 403);
        abort_if($recruitment && $recruitment->club_id !== $club->id, 404);
        $this->ensure($club->isUkm(), $club->category === 'himaju'
            ? 'Anggota HIMAJU otomatis dari data mahasiswa jurusan; rekrutmen tidak diperlukan.'
            : 'Anggota BEM dan MPM ditetapkan dari hasil pemilihan, bukan rekrutmen terbuka.');
        $opens = Wita::toUtc($data['opens_at']);
        $closes = Wita::toUtc($data['closes_at']);
        $this->ensure($opens && $closes && $closes->gt($opens), 'Tanggal tutup harus setelah tanggal buka.');

        return DB::transaction(function () use ($actor, $club, $recruitment, $data, $opens, $closes) {
            if ($recruitment) {
                $recruitment = Recruitment::lockForUpdate()->findOrFail($recruitment->id);
                $accepted = $recruitment->applications()->where('status', 'accepted')->count();
                $this->ensure($data['quota'] >= $accepted, 'Kuota tidak boleh lebih kecil dari '.$accepted.' anggota yang sudah diterima.');
            }
            $recruitment ??= new Recruitment(['club_id' => $club->id, 'created_by' => $actor->id]);
            $recruitment->fill([
                'name' => $data['name'],
                'opens_at' => $opens,
                'closes_at' => $closes,
                'quota' => $data['quota'],
                'requirements' => $data['requirements'],
                'selection_notes' => $data['selection_notes'] ?? null,
                'requires_document' => (bool) ($data['requires_document'] ?? false),
            ])->save();
            $this->audit($actor, 'recruitment.saved', $recruitment, ['quota' => $data['quota']]);

            return $recruitment;
        });
    }

    /**
     * @param  array{status: string, division?: ?string, reason?: ?string}  $data
     */
    public function updateMembership(User $actor, Membership $membership, array $data): void
    {
        abort_unless($actor->managesClub($membership->club_id), 403);
        abort_if($membership->user_id === $actor->id && $data['status'] !== $membership->status, 403, 'Tidak dapat mengubah status keanggotaan sendiri.');
        $this->ensure(array_key_exists($data['status'], config('brudel.membership_statuses')), 'Status keanggotaan tidak valid.');

        DB::transaction(function () use ($actor, $membership, $data) {
            $membership = Membership::lockForUpdate()->findOrFail($membership->id);
            $from = $membership->status;
            $changed = $from !== $data['status'];
            if ($changed) {
                $this->ensure(mb_strlen(trim($data['reason'] ?? '')) >= 5, 'Alasan perubahan status minimal 5 karakter.');
                $this->ensure($membership->club->category !== 'himaju' || $data['status'] !== 'left', 'Keanggotaan HIMAJU wajib bagi mahasiswa jurusannya; gunakan status Nonaktif atau Alumni.');
                if ($data['status'] === 'active') {
                    $this->ensure(! $this->choiceConflict($membership->user_id, $membership->club), 'Mahasiswa sudah aktif di UKM '.$membership->club->field.' lain; hanya satu yang dapat dipilih.');
                }
            }
            $membership->update([
                'status' => $data['status'],
                'division' => $data['division'] ?? $membership->division,
                'left_at' => in_array($data['status'], ['left', 'alumni'], true) ? ($membership->left_at ?? now()) : null,
                'status_reason' => $changed ? $data['reason'] : $membership->status_reason,
            ]);
            if ($changed) {
                $this->history($membership, $from, $data['status'], $actor, $data['reason']);
                $membership->user->notify(new BrudelNotice('Keanggotaan '.$membership->club->name, 'Status keanggotaan: '.$this->label($data['status']).'.', '/ukm-saya'));
            }
            $this->audit($actor, 'membership.updated', $membership, ['from' => $from, 'to' => $data['status'], 'division' => $membership->division]);
        });
    }

    /**
     * True when joining $club would give the student a second UKM in a single-choice field (Kerohanian).
     * With $pending, a waiting application to another UKM of that field also counts.
     */
    public function choiceConflict(int $userId, Club $club, bool $pending = false): bool
    {
        if (! $club->isUkm() || ! in_array($club->field, config('brudel.single_choice_fields'), true)) {
            return false;
        }
        $sameField = fn ($q) => $q->where('category', 'ukm')->where('field', $club->field)->whereKeyNot($club->id);
        $member = Membership::where('user_id', $userId)->where('status', 'active')->whereHas('club', $sameField)->exists();

        return $member || ($pending && ClubApplication::where('user_id', $userId)->whereIn('status', ['submitted', 'waitlisted', 'revision'])
            ->whereHas('recruitment.club', $sameField)->exists());
    }

    /**
     * Every active student is a member of their own jurusan's HIMAJU. A move to another jurusan ends the old
     * membership; a deactivated student account makes it inactive. Runs whenever a student's program or status changes.
     */
    public function syncHimaju(User $student): void
    {
        if (! $student->nim) {
            return;
        }
        $department = $student->program_id ? Program::whereKey($student->program_id)->value('department_id') : null;
        $target = $student->active && $department ? Club::where('category', 'himaju')->where('department_id', $department)->value('id') : null;

        DB::transaction(function () use ($student, $target) {
            $others = Membership::where('user_id', $student->id)->where('status', 'active')
                ->whereHas('club', fn ($q) => $q->where('category', 'himaju'))->when($target, fn ($q) => $q->where('club_id', '!=', $target))->get();
            foreach ($others as $old) {
                [$status, $reason] = $student->active ? ['left', 'Pindah jurusan'] : ['inactive', 'Status mahasiswa nonaktif'];
                $old->update(['status' => $status, 'left_at' => $status === 'left' ? now() : null, 'status_reason' => $reason]);
                $this->history($old, 'active', $status, null, $reason);
            }
            if (! $target) {
                return;
            }
            $membership = Membership::firstOrNew(['club_id' => $target, 'user_id' => $student->id]);
            if ($membership->exists && $membership->status === 'active') {
                return;
            }
            $from = $membership->exists ? $membership->status : null;
            $membership->fill(['status' => 'active', 'joined_at' => $membership->joined_at ?? now(), 'left_at' => null, 'status_reason' => 'Anggota otomatis HIMAJU jurusan'])->save();
            $this->history($membership, $from, 'active', null, 'Anggota otomatis HIMAJU jurusan');
        });
    }

    /**
     * BEM and MPM have no open recruitment: their members are appointed from the election result.
     */
    public function appointMember(User $actor, Club $club, string $identifier, ?string $division, string $basis): Membership
    {
        abort_unless($actor->managesClub($club->id), 403);
        $this->ensure($club->category === 'lembaga', 'Penetapan anggota terpilih hanya untuk BEM dan MPM.');
        $this->ensure(mb_strlen(trim($basis)) >= 5, 'Dasar penetapan (mis. hasil pemilihan) minimal 5 karakter.');

        return DB::transaction(function () use ($actor, $club, $identifier, $division, $basis) {
            $student = User::whereNotNull('nim')->where(fn ($q) => $q->where('nim', $identifier)->orWhere('email', mb_strtolower($identifier)))->lockForUpdate()->first();
            $this->ensure($student !== null && $student->active, 'Mahasiswa aktif dengan NIM/email tersebut tidak ditemukan.');
            abort_if($student->id === $actor->id, 403, 'Tidak dapat menetapkan diri sendiri.');
            $membership = Membership::lockForUpdate()->firstOrNew(['club_id' => $club->id, 'user_id' => $student->id]);
            $this->ensure(! ($membership->exists && $membership->status === 'active'), $student->name.' sudah menjadi anggota aktif '.$club->name.'.');
            $from = $membership->exists ? $membership->status : null;
            $membership->fill(['status' => 'active', 'joined_at' => now(), 'left_at' => null, 'division' => $division, 'status_reason' => $basis])->save();
            $this->history($membership, $from, 'active', $actor, $basis);
            $this->audit($actor, 'membership.appointed', $membership, ['basis' => $basis, 'division' => $division]);
            $student->notify(new BrudelNotice('Anda ditetapkan sebagai anggota '.$club->name, $basis.($division ? ' · '.$division : '').'.', '/ukm-saya'));

            return $membership;
        });
    }
}
