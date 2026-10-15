<?php

namespace App\Services\Club;

use App\Models\Club;
use App\Models\Membership;
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
            unset($data['name'], $data['active']);
        }

        return DB::transaction(function () use ($actor, $club, $data) {
            $club ??= new Club(['symbol' => 'spark', 'active' => true]);
            $club->fill($data)->save();
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
                if ($data['status'] === 'active' && ! config('brudel.multiple_clubs')) {
                    $this->ensure(! Membership::where('user_id', $membership->user_id)->where('status', 'active')->exists(), 'Mahasiswa sudah memiliki UKM aktif.');
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
}
