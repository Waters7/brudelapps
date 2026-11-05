<?php

namespace App\Services\Club;

use App\Models\Club;
use App\Models\Membership;
use App\Models\MembershipRequest;
use App\Models\User;
use App\Notifications\BrudelNotice;
use App\Services\DomainService;
use Illuminate\Support\Facades\DB;

/**
 * The two ways into a UKM or HIMAJU besides a recruitment period: a student asks to join and an officer, coach or
 * admin jurusan decides; or the ketua, pembina, or (for a HIMAJU) admin jurusan invites a student, who accepts.
 * BEM and MPM stay elected. A HIMAJU only takes students of its own jurusan.
 */
class MembershipRequestService extends DomainService
{
    public function __construct(private ClubService $clubs) {}

    public function canInvite(User $user, Club $club): bool
    {
        return $this->acceptsMembers($club) && ($user->leadsClub($club->id) || $user->assigned('pembina', $club->id) || $user->administersHimaju($club->id));
    }

    public function canReview(User $user, Club $club): bool
    {
        return $user->coachesClub($club->id);
    }

    /**
     * Why this student cannot ask to join, or null when the request form may be shown.
     */
    public function blockedReason(User $student, Club $club): ?string
    {
        return match (true) {
            ! $this->acceptsMembers($club) => 'Organisasi ini tidak menerima anggota lewat permintaan.',
            ! $club->active => 'Organisasi ini sedang tidak menerima anggota.',
            ! $this->inJurusan($student, $club) => 'Khusus mahasiswa Jurusan '.$club->department?->name.'.',
            $club->recruitments()->where('opens_at', '<=', now())->where('closes_at', '>', now())->exists() => 'Rekrutmen sedang dibuka; daftar lewat formulir rekrutmen.',
            default => null,
        };
    }

    public function request(User $student, Club $club, ?string $message): MembershipRequest
    {
        $this->ensure($student->active && $student->nim, 'Hanya mahasiswa aktif yang dapat meminta bergabung.');
        $blocked = $this->blockedReason($student, $club);
        $this->ensure($blocked === null, (string) $blocked);
        $this->ensure(! $this->clubs->choiceConflict($student->id, $club, true), 'Kamu hanya dapat memilih satu UKM '.$club->field.'.');

        return DB::transaction(function () use ($student, $club, $message) {
            User::whereKey($student->id)->lockForUpdate()->first();
            $this->ensureOpen($student, $club);
            $request = MembershipRequest::create(['club_id' => $club->id, 'user_id' => $student->id, 'direction' => MembershipRequest::REQUEST,
                'message' => $this->clean($message), 'created_by' => $student->id]);
            $this->audit($student, 'membership.requested', $request);

            return $request;
        });
    }

    public function invite(User $actor, Club $club, string $identifier, ?string $message): MembershipRequest
    {
        abort_unless($this->canInvite($actor, $club), 403);
        $identifier = trim($identifier);
        $student = User::whereNotNull('nim')->where(fn ($q) => $q->where('nim', $identifier)->orWhere('email', mb_strtolower($identifier)))->first();
        $this->ensure($student !== null && $student->active, 'Mahasiswa aktif dengan NIM/email tersebut tidak ditemukan.');
        abort_if($student->id === $actor->id, 403, 'Tidak dapat mengundang diri sendiri.');
        $this->ensure($this->inJurusan($student, $club), 'Anggota '.$club->name.' harus mahasiswa Jurusan '.$club->department?->name.'.');

        return DB::transaction(function () use ($actor, $club, $student, $message) {
            User::whereKey($student->id)->lockForUpdate()->first();
            $this->ensureOpen($student, $club);
            $invitation = MembershipRequest::create(['club_id' => $club->id, 'user_id' => $student->id, 'direction' => MembershipRequest::INVITATION,
                'message' => $this->clean($message), 'created_by' => $actor->id]);
            $this->audit($actor, 'membership.invited', $invitation);
            $student->notify(new BrudelNotice('Undangan bergabung '.$club->name, 'Terima atau tolak undangan di menu UKM Saya.', '/ukm-saya'));

            return $invitation;
        });
    }

    /**
     * The student answers an invitation.
     */
    public function respond(User $student, MembershipRequest $invitation, bool $accept): void
    {
        abort_unless($invitation->isInvitation() && $invitation->user_id === $student->id, 403);
        $this->close($student, $invitation, $accept, 'Menerima undangan');
    }

    /**
     * An officer, coach or admin jurusan decides on a student's request.
     */
    public function decide(User $actor, MembershipRequest $request, bool $accept): void
    {
        abort_unless(! $request->isInvitation() && $this->canReview($actor, $request->club), 403);
        abort_if($request->user_id === $actor->id, 403, 'Tidak dapat memutuskan permintaan sendiri.');
        $this->close($actor, $request, $accept, 'Permintaan bergabung disetujui');
        $request->user->notify(new BrudelNotice('Permintaan bergabung '.$request->club->name, $accept ? 'Permintaanmu disetujui. Selamat bergabung!' : 'Permintaanmu belum dapat disetujui.', '/ukm-saya'));
    }

    /**
     * The student withdraws a request, or the club withdraws an invitation.
     */
    public function cancel(User $actor, MembershipRequest $item): void
    {
        abort_unless($item->isInvitation() ? $this->canReview($actor, $item->club) : $item->user_id === $actor->id, 403);
        DB::transaction(function () use ($actor, $item) {
            $item = MembershipRequest::lockForUpdate()->findOrFail($item->id);
            $this->ensure($item->status === 'pending', 'Sudah tidak menunggu.');
            $item->update(['status' => 'cancelled', 'decided_by' => $actor->id, 'decided_at' => now()]);
            $this->audit($actor, 'membership.cancelled', $item);
        });
    }

    private function close(User $actor, MembershipRequest $item, bool $accept, string $reason): void
    {
        DB::transaction(function () use ($actor, $item, $accept, $reason) {
            $item = MembershipRequest::with('club')->lockForUpdate()->findOrFail($item->id);
            $this->ensure($item->status === 'pending', 'Sudah diputuskan sebelumnya.');
            if ($accept) {
                $this->join($actor, $item, $reason);
            }
            $item->update(['status' => $accept ? 'accepted' : 'declined', 'decided_by' => $actor->id, 'decided_at' => now()]);
            $this->audit($actor, 'membership.'.($accept ? 'accepted' : 'declined'), $item);
        });
    }

    private function join(User $actor, MembershipRequest $item, string $reason): void
    {
        $this->ensure(! $this->clubs->choiceConflict($item->user_id, $item->club), 'Mahasiswa sudah aktif di UKM '.$item->club->field.' lain; hanya satu yang dapat dipilih.');
        $membership = Membership::lockForUpdate()->firstOrNew(['club_id' => $item->club_id, 'user_id' => $item->user_id]);
        $this->ensure(! ($membership->exists && $membership->status === 'active'), 'Sudah menjadi anggota aktif '.$item->club->name.'.');
        $from = $membership->exists ? $membership->status : null;
        $membership->fill(['status' => 'active', 'joined_at' => now(), 'left_at' => null, 'status_reason' => $reason])->save();
        $this->history($membership, $from, 'active', $actor, $reason);
    }

    private function ensureOpen(User $student, Club $club): void
    {
        $this->ensure(! Membership::where('club_id', $club->id)->where('user_id', $student->id)->where('status', 'active')->exists(), $student->name.' sudah menjadi anggota aktif '.$club->name.'.');
        $this->ensure(! MembershipRequest::where('club_id', $club->id)->where('user_id', $student->id)->pending()->exists(), 'Masih ada permintaan atau undangan yang menunggu.');
    }

    private function acceptsMembers(Club $club): bool
    {
        return $club->isUkm() || $club->category === 'himaju';
    }

    private function inJurusan(User $student, Club $club): bool
    {
        return $club->category !== 'himaju' || $student->departmentId() === $club->department_id;
    }

    private function clean(?string $message): ?string
    {
        return filled($message) ? mb_substr(trim($message), 0, 500) : null;
    }
}
