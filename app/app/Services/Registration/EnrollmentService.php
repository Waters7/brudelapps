<?php

namespace App\Services\Registration;

use App\Models\ClubApplication;
use App\Models\Competition;
use App\Models\DepartmentApproval;
use App\Models\Membership;
use App\Models\Participant;
use App\Models\PaymentAccount;
use App\Models\Recruitment;
use App\Models\Registration;
use App\Models\User;
use App\Notifications\BrudelNotice;
use App\Services\DomainService;
use App\Services\PrivateFiles;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Registration module: UKM applications and competition registrations (individual and team).
 * Lock order for competition mutations: competition → registration → participant/approval.
 */
class EnrollmentService extends DomainService
{
    public function __construct(private PrivateFiles $files) {}

    private function student(User $user): void
    {
        $this->ensure($user->active && $user->nim && $user->email_verified_at, 'Akun mahasiswa harus aktif dan sudah diaktivasi.');
    }

    /**
     * The single-UKM policy counts UKM only; BEM, MPM, and HIMAJU memberships do not use up the slot.
     *
     * @param  Builder<Membership>  $members
     */
    private function hasActiveUkm(Builder $members): bool
    {
        return (clone $members)->whereHas('club', fn ($q) => $q->where('category', 'ukm'))->exists();
    }

    public function applyClub(User $user, int $id, string $motivation, ?UploadedFile $document = null): ClubApplication
    {
        $stored = $document ? $this->files->storePdf($document, 'club-applications/'.$user->id) : null;

        try {
            return DB::transaction(function () use ($user, $id, $motivation, $stored) {
                $this->student($user);
                $recruitment = Recruitment::lockForUpdate()->findOrFail($id);
                User::whereKey($user->id)->lockForUpdate()->first();
                $this->ensure($recruitment->isOpen(), 'Periode pendaftaran UKM telah ditutup atau belum dimulai.');
                $club = $recruitment->club;
                $this->ensure($club->active, 'UKM ini sedang tidak menerima anggota.');
                $this->ensure($club->category !== 'himaju' || $user->departmentId() === $club->department_id, $club->name.' hanya untuk mahasiswa Jurusan '.$club->department?->name.'.');
                $members = Membership::where('user_id', $user->id)->where('status', 'active');
                $this->ensure(! (clone $members)->where('club_id', $recruitment->club_id)->exists(), 'Anda sudah menjadi anggota aktif UKM ini.');
                $this->ensure(config('brudel.multiple_clubs') || ! $club->isUkm() || ! $this->hasActiveUkm($members), 'Kebijakan kampus hanya mengizinkan satu UKM aktif.');
                $application = ClubApplication::where('recruitment_id', $id)->where('user_id', $user->id)->first();
                $this->ensure(! $application || $application->status === 'revision', 'Anda sudah mengajukan pendaftaran pada periode ini.');
                $this->ensure(! $recruitment->requires_document || $stored || $application?->document_path, 'Dokumen persyaratan UKM wajib diunggah.');
                $from = $application?->status;
                $application = ClubApplication::updateOrCreate(
                    ['recruitment_id' => $id, 'user_id' => $user->id],
                    ['motivation' => $motivation, 'status' => 'submitted', 'reason' => null] + ($stored ? ['document_path' => $stored[0], 'document_disk' => $stored[1]] : []),
                );
                $this->history($application, $from, 'submitted', $user);
                $this->audit($user, 'club.submitted', $application);

                return $application;
            }, 3);
        } catch (\Throwable $e) {
            $this->files->delete($stored[0] ?? null, $stored[1] ?? null);
            throw $e;
        }
    }

    public function reviewClub(User $actor, int $id, string $decision, ?string $reason): void
    {
        DB::transaction(function () use ($actor, $id, $decision, $reason) {
            $reference = ClubApplication::findOrFail($id);
            $recruitment = Recruitment::lockForUpdate()->findOrFail($reference->recruitment_id);
            $application = ClubApplication::lockForUpdate()->findOrFail($id);
            abort_unless($actor->active && $actor->managesClub($recruitment->club_id), 403);
            abort_if($application->user_id === $actor->id, 403, 'Tidak dapat meninjau pengajuan sendiri.');
            $this->ensure(in_array($decision, ['accepted', 'rejected', 'revision', 'waitlisted'], true), 'Keputusan tidak valid.');
            $this->ensure(in_array($application->status, ['submitted', 'waitlisted'], true), 'Pengajuan ini sudah ditinjau.');
            $this->requireReason($decision, $reason);
            if ($decision === 'accepted') {
                $student = User::whereKey($application->user_id)->lockForUpdate()->firstOrFail();
                $this->student($student);
                $this->ensure($recruitment->applications()->where('status', 'accepted')->count() < $recruitment->quota, 'Kuota UKM sudah penuh.');
                $members = Membership::where('user_id', $application->user_id)->where('status', 'active');
                $this->ensure(! (clone $members)->where('club_id', $recruitment->club_id)->exists(), 'Mahasiswa sudah menjadi anggota aktif.');
                $this->ensure(config('brudel.multiple_clubs') || ! $recruitment->club->isUkm() || ! $this->hasActiveUkm($members), 'Mahasiswa sudah memiliki UKM aktif.');
                $membership = Membership::where('club_id', $recruitment->club_id)->where('user_id', $application->user_id)->first();
                $membership = Membership::updateOrCreate(
                    ['club_id' => $recruitment->club_id, 'user_id' => $application->user_id],
                    ['club_application_id' => $application->id, 'status' => 'active', 'joined_at' => now(), 'left_at' => null, 'status_reason' => null],
                );
                $this->history($membership, $membership->wasRecentlyCreated ? null : 'left', 'active', $actor, 'Diterima melalui '.$recruitment->name);
            }
            $from = $application->status;
            $application->update(['status' => $decision, 'reason' => $reason, 'reviewed_by' => $actor->id, 'reviewed_at' => now()]);
            $this->history($application, $from, $decision, $actor, $reason);
            $this->audit($actor, 'club.'.$decision, $application, ['reason' => $reason]);
            $application->user->notify(new BrudelNotice(
                'Pendaftaran '.$recruitment->club->name.' diperbarui',
                'Status: '.$this->label($decision).($reason ? '. Catatan: '.$reason : '.'),
                '/pendaftaran',
            ));
        }, 3);
    }

    /**
     * @param  array{motivation: string, team_name?: ?string}  $data
     */
    public function draft(User $user, int $id, array $data): Registration
    {
        return DB::transaction(function () use ($user, $id, $data) {
            $this->student($user);
            $competition = Competition::lockForUpdate()->findOrFail($id);
            $this->ensure($competition->isOpen(), 'Pendaftaran lomba tidak dibuka.');
            $registration = Registration::where('competition_id', $id)->where('user_id', $user->id)->first();
            $this->ensure(! $registration || in_array($registration->status, ['draft', 'revision', 'cancelled'], true), 'Pendaftaran sudah terkunci.');
            $this->ensure(! Participant::where('competition_id', $id)->where('user_id', $user->id)
                ->when($registration, fn ($q) => $q->where('registration_id', '!=', $registration->id))->exists(), 'Anda sudah terdaftar atau diundang dalam tim lain pada nomor ini.');
            $from = $registration?->status;
            $registration = Registration::updateOrCreate(
                ['competition_id' => $id, 'user_id' => $user->id],
                [
                    'team_name' => $competition->format === 'team' ? $data['team_name'] : null,
                    'motivation' => $data['motivation'],
                    'status' => $from === 'revision' ? 'revision' : 'draft',
                ],
            );
            Participant::firstOrCreate(['registration_id' => $registration->id, 'competition_id' => $id, 'user_id' => $user->id], ['status' => 'confirmed']);
            if ($from === null || $from === 'cancelled') {
                $this->history($registration, $from, 'draft', $user);
            }
            $this->audit($user, 'registration.draft', $registration);

            return $registration;
        }, 3);
    }

    public function invite(User $actor, int $id, string $nim): void
    {
        DB::transaction(function () use ($actor, $id, $nim) {
            $reference = Registration::findOrFail($id);
            $competition = Competition::lockForUpdate()->findOrFail($reference->competition_id);
            $registration = Registration::lockForUpdate()->findOrFail($id);
            abort_unless($actor->active && $registration->user_id === $actor->id, 403);
            $this->ensure($registration->isEditable() && $competition->format === 'team', 'Susunan tim terkunci.');
            $this->ensure(now()->between($competition->opens_at, $competition->closes_at), 'Periode pendaftaran tidak dibuka.');
            $member = User::where('nim', $nim)->where('active', true)->whereNotNull('email_verified_at')->first();
            $this->ensure((bool) $member, 'NIM tidak tersedia sebagai anggota aktif.');
            $this->ensure($registration->participants()->count() < $competition->max_members, 'Jumlah maksimum anggota tercapai.');
            $this->ensure(! Participant::where('competition_id', $competition->id)->where('user_id', $member->id)->exists(), 'Mahasiswa sudah terdaftar atau mendapat undangan pada nomor ini.');
            $participant = Participant::create(['registration_id' => $registration->id, 'competition_id' => $competition->id, 'user_id' => $member->id]);
            $this->audit($actor, 'team.invited', $participant);
            $member->notify(new BrudelNotice('Undangan tim '.$registration->team_name, $actor->name.' mengundang Anda ke '.$competition->name.'. Konfirmasi melalui Pendaftaran Saya.', '/pendaftaran'));
        }, 3);
    }

    public function respond(User $actor, int $id, bool $accept): void
    {
        DB::transaction(function () use ($actor, $id, $accept) {
            $reference = Participant::findOrFail($id);
            Competition::whereKey($reference->competition_id)->lockForUpdate()->firstOrFail();
            $registration = Registration::lockForUpdate()->findOrFail($reference->registration_id);
            $participant = Participant::lockForUpdate()->findOrFail($id);
            abort_unless($participant->user_id === $actor->id, 403);
            $this->student($actor);
            $this->ensure($registration->isEditable() && $participant->status === 'invited', 'Undangan sudah diproses atau tim terkunci.');
            $this->audit($actor, $accept ? 'team.confirmed' : 'team.declined', $participant);
            $accept ? $participant->update(['status' => 'confirmed']) : $participant->delete();
            $registration->user->notify(new BrudelNotice('Tanggapan undangan tim', $actor->name.($accept ? ' menerima' : ' menolak').' undangan '.$registration->team_name.'.', '/pendaftaran#registration-'.$registration->id));
        }, 3);
    }

    /**
     * Leader removes an invited or confirmed member while the roster is still editable.
     */
    public function removeMember(User $actor, int $registrationId, int $participantId): void
    {
        DB::transaction(function () use ($actor, $registrationId, $participantId) {
            $reference = Registration::findOrFail($registrationId);
            Competition::whereKey($reference->competition_id)->lockForUpdate()->firstOrFail();
            $registration = Registration::lockForUpdate()->findOrFail($registrationId);
            $participant = Participant::lockForUpdate()->where('registration_id', $registration->id)->findOrFail($participantId);
            abort_unless($registration->user_id === $actor->id, 403);
            $this->ensure($registration->isEditable(), 'Susunan tim terkunci.');
            $this->ensure($participant->user_id !== $actor->id, 'Ketua tidak dapat menghapus dirinya. Batalkan draf bila perlu.');
            $this->audit($actor, 'team.removed', $participant, ['user_id' => $participant->user_id]);
            $participant->delete();
            $participant->user->notify(new BrudelNotice('Keikutsertaan tim diperbarui', 'Anda tidak lagi tercantum pada tim '.$registration->team_name.'.', '/pendaftaran'));
        }, 3);
    }

    /**
     * Cancel a draft and release every participant reservation on the competition.
     */
    public function cancel(User $actor, int $id): void
    {
        DB::transaction(function () use ($actor, $id) {
            $reference = Registration::findOrFail($id);
            Competition::whereKey($reference->competition_id)->lockForUpdate()->firstOrFail();
            $registration = Registration::lockForUpdate()->findOrFail($id);
            abort_unless($registration->user_id === $actor->id, 403);
            $this->ensure($registration->status === 'draft', 'Hanya draf yang belum dikirim yang dapat dibatalkan.');
            $released = $registration->participants()->where('user_id', '!=', $actor->id)->with('user')->get();
            $registration->participants()->delete();
            $registration->update(['status' => 'cancelled']);
            $this->history($registration, 'draft', 'cancelled', $actor);
            $this->audit($actor, 'registration.cancelled', $registration);
            Notification::send($released->pluck('user'), new BrudelNotice('Draf tim dibatalkan', 'Draf '.$registration->team_name.' dibatalkan oleh ketua.', '/pendaftaran'));
        }, 3);
    }

    public function submit(User $actor, int $id): void
    {
        DB::transaction(function () use ($actor, $id) {
            $this->student($actor);
            $reference = Registration::findOrFail($id);
            $competition = Competition::lockForUpdate()->findOrFail($reference->competition_id);
            $registration = Registration::lockForUpdate()->findOrFail($id);
            abort_unless($registration->user_id === $actor->id, 403);
            $this->ensure($registration->isEditable(), 'Pendaftaran sudah dikirim.');
            $this->ensure($competition->isOpen(), 'Periode pendaftaran lomba ditutup.');
            $members = $registration->participants()->with('user')->get();
            $this->ensure($members->count() >= $competition->min_members && $members->count() <= $competition->max_members, 'Jumlah anggota belum sesuai persyaratan.');
            foreach ($members as $participant) {
                $this->student($participant->user);
                $this->ensure($participant->status === 'confirmed', 'Semua anggota wajib mengonfirmasi undangan.');
            }
            $this->ensure(! $competition->requires_document || (bool) $registration->document_path, 'Dokumen wajib belum diunggah.');
            $this->ensure($competition->registrations()->whereIn('status', Registration::HOLDING)->count() < $competition->quota, 'Kuota lomba sudah penuh.');

            $fee = $competition->fee * ($competition->fee_basis === 'participant' ? $members->count() : 1);
            $this->ensure($fee === 0 || PaymentAccount::where('active', true)->exists(), 'Rekening resmi kampus belum dikonfigurasi. Lomba berbayar belum dapat dikirim.');
            $paymentStatus = match (true) {
                $fee === 0 => 'not_required',
                $registration->payment_status === 'paid' && $registration->fee_snapshot === $fee => 'paid',
                $registration->payment_status === 'pending' => 'pending',
                default => 'unpaid',
            };

            $status = 'submitted';
            if ($competition->requires_program_approval) {
                $departments = $members->map(fn ($participant) => $participant->user->departmentId());
                $this->ensure($departments->every(fn ($department) => $department !== null), 'Setiap anggota harus terdaftar pada jurusan untuk persetujuan jurusan.');
                $registration->approvals()->delete();
                foreach ($departments->unique() as $department) {
                    DepartmentApproval::create(['registration_id' => $registration->id, 'department_id' => $department, 'status' => 'pending']);
                }
                $status = 'program_review';
            }

            $from = $registration->status;
            $registration->update(['status' => $status, 'reason' => null, 'fee_snapshot' => $fee, 'payment_status' => $paymentStatus, 'submitted_at' => now()]);
            $this->history($registration, $from, $status, $actor);
            $this->audit($actor, 'registration.submitted', $registration, ['fee' => $fee, 'program_review' => $status === 'program_review']);
        }, 3);
    }

    /**
     * A jurusan reviewer decides on the members of their jurusan. A cross-jurusan team needs every jurusan.
     * A rejection returns the team for revision and closes the other jurusan's pending approvals;
     * resubmitting creates a fresh approval for every jurusan.
     */
    public function decideDepartment(User $actor, int $approvalId, string $decision, ?string $reason): void
    {
        DB::transaction(function () use ($actor, $approvalId, $decision, $reason) {
            $reference = DepartmentApproval::findOrFail($approvalId);
            $registrationRef = Registration::findOrFail($reference->registration_id);
            Competition::whereKey($registrationRef->competition_id)->lockForUpdate()->firstOrFail();
            $registration = Registration::lockForUpdate()->findOrFail($registrationRef->id);
            $approval = DepartmentApproval::lockForUpdate()->findOrFail($approvalId);
            abort_unless($actor->active && $actor->reviewsDepartment($approval->department_id), 403);
            abort_if($registration->participants()->where('user_id', $actor->id)->exists(), 403, 'Tidak dapat menyetujui pendaftaran sendiri.');
            $this->ensure(in_array($decision, ['approved', 'rejected'], true), 'Keputusan tidak valid.');
            $this->ensure($registration->status === 'program_review' && $approval->status === 'pending', 'Persetujuan ini sudah diproses.');
            $this->requireReason($decision, $reason);
            $approval->update(['status' => $decision, 'reason' => $reason, 'decided_by' => $actor->id, 'decided_at' => now()]);
            $this->audit($actor, 'department.'.$decision, $approval, ['reason' => $reason]);

            $department = 'Jurusan '.$approval->department->name;
            if ($decision === 'rejected') {
                $closing = 'Ditutup karena '.$department.' meminta perbaikan.';
                foreach ($registration->approvals()->where('status', 'pending')->get() as $other) {
                    $other->update(['status' => 'cancelled', 'reason' => $closing, 'decided_at' => now()]);
                    $this->audit($actor, 'department.cancelled', $other, ['reason' => $closing]);
                }
                $registration->update(['status' => 'revision', 'reason' => $department.': '.$reason]);
                $this->history($registration, 'program_review', 'revision', $actor, $department.': '.$reason);
            } elseif (! $registration->approvals()->where('status', '!=', 'approved')->exists()) {
                $registration->update(['status' => 'submitted']);
                $this->history($registration, 'program_review', 'submitted', $actor, 'Disetujui seluruh jurusan terkait.');
            }
            $registration->user->notify(new BrudelNotice('Persetujuan '.$department, $decision === 'approved' ? 'Jurusan menyetujui keikutsertaan.' : 'Jurusan meminta perbaikan: '.$reason, '/pendaftaran#registration-'.$registration->id));
        }, 3);
    }

    public function reviewRegistration(User $actor, int $id, string $decision, ?string $reason): void
    {
        DB::transaction(function () use ($actor, $id, $decision, $reason) {
            $reference = Registration::findOrFail($id);
            $competition = Competition::lockForUpdate()->findOrFail($reference->competition_id);
            $registration = Registration::lockForUpdate()->findOrFail($id);
            abort_unless($actor->active && $actor->managesCompetition($competition), 403);
            abort_if($registration->participants()->where('user_id', $actor->id)->exists(), 403, 'Tidak dapat meninjau diri atau tim sendiri.');
            $this->ensure($registration->status === 'submitted', 'Pendaftaran sudah ditinjau atau masih menunggu persetujuan prodi.');
            $this->ensure(in_array($decision, ['accepted', 'rejected', 'revision'], true), 'Keputusan tidak valid.');
            $this->requireReason($decision, $reason);
            if ($decision === 'accepted') {
                foreach ($registration->participants()->with('user')->get() as $participant) {
                    $this->student($participant->user);
                }
                $this->ensure(in_array($registration->payment_status, ['not_required', 'paid'], true), 'Pembayaran belum diverifikasi.');
            }
            $registration->update(['status' => $decision, 'reason' => $reason, 'reviewed_by' => $actor->id, 'reviewed_at' => now()]);
            $this->history($registration, 'submitted', $decision, $actor, $reason);
            $this->audit($actor, 'registration.'.$decision, $registration, ['reason' => $reason]);
            Notification::send(
                $registration->participants()->with('user')->get()->pluck('user'),
                new BrudelNotice('Pendaftaran '.$competition->name, 'Status: '.$this->label($decision).($reason ? '. Catatan: '.$reason : '.'), '/pendaftaran#registration-'.$registration->id),
            );
        }, 3);
    }
}
