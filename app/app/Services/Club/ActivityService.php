<?php

namespace App\Services\Club;

use App\Models\ActivityAttendance;
use App\Models\Club;
use App\Models\ClubActivity;
use App\Models\Membership;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\BrudelNotice;
use App\Services\DomainService;
use App\Services\PrivateFiles;
use App\Services\Wita;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Club activities (latihan, pertandingan persahabatan, meeting internal) run by any pengurus or the pelatih/pembina.
 * Borrowing a room or meeting outside campus hours needs a permit from kemahasiswaan jurusan (HIMAJU) or direktorat.
 * Members check in with a short-lived code generated from the coach's account; every activity ends with a
 * type-specific report: attendance and absentees, match score and follow-up notes, or meeting minutes.
 */
class ActivityService extends DomainService
{
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(private PrivateFiles $files) {}

    public function canManage(User $user, ClubActivity|Club $subject): bool
    {
        return $user->coachesClub($subject instanceof Club ? $subject->id : $subject->club_id);
    }

    public function canView(User $user, ClubActivity $activity): bool
    {
        if ($this->canManage($user, $activity) || $user->managesClub($activity->club_id) || $this->canDecidePermit($user, $activity)) {
            return true;
        }
        $confirmed = $activity->permit_status === null || $activity->permit_status === 'approved';

        return $confirmed && ($activity->is_public || Membership::where('club_id', $activity->club_id)->where('user_id', $user->id)->where('status', 'active')->exists());
    }

    /**
     * Why an activity needs a permit: borrowing a room, or any part of it outside campus operating hours (WITA).
     *
     * @return list<string>
     */
    public function permitReasons(bool $needsRoom, CarbonInterface $starts, CarbonInterface $ends): array
    {
        $timezone = config('brudel.timezone');
        [$start, $end] = [$starts->copy()->timezone($timezone), $ends->copy()->timezone($timezone)];
        $days = array_map('intval', explode(',', (string) Setting::value('operating_days')));
        $outside = ! $start->isSameDay($end) || ! in_array($start->dayOfWeekIso, $days, true)
            || $start->format('H:i') < Setting::value('operating_open') || $end->format('H:i') > Setting::value('operating_close');

        return array_values(array_filter([$needsRoom ? 'Peminjaman ruangan' : null, $outside ? 'Di luar jam operasional kampus' : null]));
    }

    /**
     * HIMAJU asks its kemahasiswaan jurusan; BEM, MPM and every UKM ask kemahasiswaan direktorat. Direktorat only
     * steps in for a HIMAJU when that jurusan has no active kemahasiswaan jurusan.
     */
    public function canDecidePermit(User $user, ClubActivity $activity): bool
    {
        if ($activity->permit_status === null || $activity->created_by === $user->id) {
            return false;
        }
        if ($activity->permit_level !== 'jurusan') {
            return $user->isStudentAffairs();
        }
        $department = $activity->club->department_id;

        return $user->assigned('jurusan', department: $department) || ($user->isStudentAffairs() && ! $this->hasDepartmentAdmin($department));
    }

    /**
     * @return Collection<int, User>
     */
    public function permitApprovers(ClubActivity $activity): Collection
    {
        $direktorat = fn () => User::whereHas('currentAssignments', fn ($q) => $q->where('role', 'kemahasiswaan'))->get();
        if ($activity->permit_level !== 'jurusan') {
            return $direktorat();
        }
        $jurusan = User::whereHas('currentAssignments', fn ($q) => $q->where('role', 'jurusan')->where('department_id', $activity->club->department_id))->get();

        return $jurusan->isNotEmpty() ? $jurusan : $direktorat();
    }

    private function hasDepartmentAdmin(?int $department): bool
    {
        return $department !== null && User::whereHas('currentAssignments', fn ($q) => $q->where('role', 'jurusan')->where('department_id', $department))->exists();
    }

    /**
     * Any officer or coach schedules an activity. It is published at once unless it borrows a room or falls
     * outside campus operating hours; then it waits for the permit.
     *
     * @param  array{type: string, title: string, description?: ?string, location: string, starts_at: string, ends_at: string, is_public?: ?bool, needs_room?: ?bool, room?: ?string}  $data
     */
    public function create(User $actor, Club $club, array $data): ClubActivity
    {
        abort_unless($this->canManage($actor, $club), 403);
        $this->ensure(array_key_exists($data['type'], ClubActivity::TYPES), 'Jenis kegiatan tidak valid.');
        $starts = Wita::toUtc($data['starts_at']);
        $ends = Wita::toUtc($data['ends_at']);
        $this->ensure($starts && $ends && $ends->gt($starts), 'Waktu selesai harus setelah waktu mulai.');
        $needsRoom = (bool) ($data['needs_room'] ?? false);
        $this->ensure(! $needsRoom || filled($data['room'] ?? null), 'Nama ruangan yang akan dipinjam wajib diisi.');
        $reasons = $this->permitReasons($needsRoom, $starts, $ends);

        return DB::transaction(function () use ($actor, $club, $data, $starts, $ends, $needsRoom, $reasons) {
            $activity = ClubActivity::create([
                'club_id' => $club->id,
                'type' => $data['type'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'location' => $data['location'],
                'starts_at' => $starts,
                'ends_at' => $ends,
                'is_public' => $data['type'] === 'meeting' ? false : (bool) ($data['is_public'] ?? true),
                'needs_room' => $needsRoom,
                'room' => $needsRoom ? $data['room'] : null,
                'permit_status' => $reasons ? 'pending' : null,
                'permit_level' => $reasons ? ($club->category === 'himaju' ? 'jurusan' : 'direktorat') : null,
                'permit_reasons' => $reasons ? implode('; ', $reasons) : null,
                'created_by' => $actor->id,
            ]);
            $this->audit($actor, 'activity.created', $activity, ['type' => $activity->type, 'permit' => $reasons]);
            if ($reasons) {
                Notification::send($this->permitApprovers($activity), new BrudelNotice('Izin kegiatan '.$club->name, $activity->title.' · '.implode(', ', $reasons).'. Menunggu keputusan Anda.', '/kegiatan/izin'));
            } else {
                $this->announce($actor, $activity);
            }

            return $activity;
        });
    }

    public function decidePermit(User $actor, ClubActivity $activity, string $decision, ?string $note): void
    {
        abort_unless($this->canDecidePermit($actor, $activity), 403);
        $this->ensure(in_array($decision, ['approved', 'rejected'], true), 'Keputusan tidak valid.');
        $this->ensure($decision === 'approved' || mb_strlen(trim((string) $note)) >= 5, 'Alasan penolakan minimal 5 karakter.');

        DB::transaction(function () use ($actor, $activity, $decision, $note) {
            $activity = ClubActivity::with('club')->lockForUpdate()->findOrFail($activity->id);
            $this->ensure($activity->awaitsPermit(), 'Izin kegiatan ini sudah diputuskan.');
            $activity->update(['permit_status' => $decision, 'permit_decided_by' => $actor->id, 'permit_decided_at' => now(), 'permit_note' => $note]
                + ($decision === 'rejected' ? ['status' => 'cancelled', 'notes' => 'Izin ditolak: '.$note] : []));
            $this->audit($actor, 'activity.permit_'.$decision, $activity, ['note' => $note]);
            $activity->creator->notify(new BrudelNotice(
                'Izin kegiatan '.($decision === 'approved' ? 'disetujui' : 'ditolak'),
                $activity->title.($note ? ' · '.$note : ''),
                '/kegiatan/'.$activity->id,
            ));
            if ($decision === 'approved') {
                $this->announce($activity->creator, $activity);
            }
        });
    }

    private function announce(User $actor, ClubActivity $activity): void
    {
        $members = User::whereIn('id', Membership::where('club_id', $activity->club_id)->where('status', 'active')->pluck('user_id'))->where('id', '!=', $actor->id)->get();
        Notification::send($members, new BrudelNotice($activity->club->name.': '.$activity->typeLabel(), $activity->title.' · '.Wita::show($activity->starts_at).' WITA · '.$activity->location, '/kegiatan/'.$activity->id));
    }

    /**
     * Generate a fresh attendance code that stays valid for a limited time.
     */
    public function openCode(User $actor, ClubActivity $activity, ?int $minutes = null): string
    {
        abort_unless($this->canManage($actor, $activity), 403);
        $this->ensure($activity->status !== 'cancelled', 'Kegiatan dibatalkan.');
        $this->ensure(! $activity->awaitsPermit(), 'Kegiatan masih menunggu izin '.$activity->permitLevelLabel().'.');
        $this->ensure(now()->gte($activity->starts_at->copy()->subHour()), 'Kode kehadiran dapat dibuka paling cepat 1 jam sebelum kegiatan.');
        $minutes = max(5, min($minutes ?? (int) config('brudel.activity_code_minutes'), 480));

        return DB::transaction(function () use ($actor, $activity, $minutes) {
            do {
                $code = '';
                for ($i = 0; $i < 6; $i++) {
                    $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
                }
            } while (ClubActivity::where('attendance_code', $code)->exists());
            $activity->update(['attendance_code' => $code, 'code_expires_at' => now()->addMinutes($minutes)]);
            $this->audit($actor, 'activity.code_opened', $activity, ['minutes' => $minutes]);

            return $code;
        });
    }

    public function closeCode(User $actor, ClubActivity $activity): void
    {
        abort_unless($this->canManage($actor, $activity), 403);
        $activity->update(['attendance_code' => null, 'code_expires_at' => null]);
        $this->audit($actor, 'activity.code_closed', $activity);
    }

    /**
     * A member records attendance with the code shown by the coach.
     */
    public function checkIn(User $student, string $code): ClubActivity
    {
        $this->ensure($student->isStudent(), 'Hanya mahasiswa aktif yang dapat mengisi kehadiran.');
        $activity = ClubActivity::with('club')->where('attendance_code', mb_strtoupper(trim($code)))->first();
        $this->ensure($activity !== null && $activity->codeIsOpen(), 'Kode kehadiran tidak valid atau sudah ditutup.');
        $this->ensure(Membership::where('club_id', $activity->club_id)->where('user_id', $student->id)->where('status', 'active')->exists(),
            'Kehadiran hanya untuk anggota aktif '.$activity->club->name.'.');

        return DB::transaction(function () use ($student, $activity) {
            ClubActivity::whereKey($activity->id)->lockForUpdate()->first();
            $this->ensure(! ActivityAttendance::where('club_activity_id', $activity->id)->where('user_id', $student->id)->exists(), 'Kehadiran Anda sudah tercatat.');
            ActivityAttendance::create(['club_activity_id' => $activity->id, 'user_id' => $student->id, 'method' => 'code', 'checked_in_at' => now()]);

            return $activity;
        });
    }

    public function markPresent(User $actor, ClubActivity $activity, int $userId): void
    {
        abort_unless($this->canManage($actor, $activity), 403);
        $this->ensure(Membership::where('club_id', $activity->club_id)->where('user_id', $userId)->where('status', 'active')->exists(), 'Hanya anggota aktif yang dapat dicatat hadir.');
        DB::transaction(function () use ($actor, $activity, $userId) {
            $attendance = ActivityAttendance::firstOrCreate(
                ['club_activity_id' => $activity->id, 'user_id' => $userId],
                ['method' => 'manual', 'recorded_by' => $actor->id, 'checked_in_at' => now()],
            );
            if ($attendance->wasRecentlyCreated) {
                $this->audit($actor, 'activity.marked_present', $activity, ['user_id' => $userId]);
            }
        });
    }

    /**
     * @param  array{opponent?: ?string, score_home?: ?int, score_away?: ?int, notes?: ?string}  $data
     */
    public function report(User $actor, ClubActivity $activity, array $data, ?UploadedFile $minutes): void
    {
        abort_unless($this->canManage($actor, $activity), 403);
        $this->ensure($activity->status !== 'cancelled', 'Kegiatan dibatalkan.');
        $this->ensure(! $activity->awaitsPermit(), 'Kegiatan belum mendapat izin.');
        $this->ensure(now()->gte($activity->starts_at), 'Laporan dibuat setelah kegiatan dimulai.');
        if ($activity->type === 'meeting') {
            $this->ensure($minutes !== null || $activity->minutes_path !== null, 'Meeting internal wajib melampirkan file notulen.');
        }
        if ($activity->type === 'persahabatan') {
            $this->ensure(filled($data['opponent'] ?? null) && isset($data['score_home'], $data['score_away']), 'Pertandingan persahabatan wajib mengisi lawan dan skor.');
            $this->ensure(mb_strlen(trim($data['notes'] ?? '')) >= 10, 'Isi catatan penting untuk latihan selanjutnya (minimal 10 karakter).');
        }
        $stored = $minutes ? $this->files->storeDocument($minutes, 'activities/'.$activity->id) : null;

        try {
            DB::transaction(function () use ($actor, $activity, $data, $minutes, $stored) {
                $activity = ClubActivity::lockForUpdate()->findOrFail($activity->id);
                $activity->update([
                    'opponent' => $activity->type === 'persahabatan' ? $data['opponent'] : null,
                    'score_home' => $activity->type === 'persahabatan' ? (int) $data['score_home'] : null,
                    'score_away' => $activity->type === 'persahabatan' ? (int) $data['score_away'] : null,
                    'notes' => $data['notes'] ?? null,
                    'status' => 'completed',
                    'attendance_code' => null,
                    'code_expires_at' => null,
                    'reported_by' => $actor->id,
                    'reported_at' => now(),
                ] + ($stored ? ['minutes_path' => $stored[0], 'minutes_disk' => $stored[1], 'minutes_name' => mb_substr($minutes->getClientOriginalName(), 0, 190)] : []));
                $this->audit($actor, 'activity.reported', $activity, ['present' => $activity->attendances()->count()]);
            });
        } catch (\Throwable $e) {
            $this->files->delete($stored[0] ?? null, $stored[1] ?? null);
            throw $e;
        }
    }

    public function cancel(User $actor, ClubActivity $activity, string $reason): void
    {
        abort_unless($this->canManage($actor, $activity), 403);
        $this->ensure(mb_strlen(trim($reason)) >= 5, 'Alasan pembatalan minimal 5 karakter.');
        $this->ensure($activity->status === 'scheduled', 'Kegiatan sudah selesai atau dibatalkan.');
        $activity->update(['status' => 'cancelled', 'notes' => 'Dibatalkan: '.$reason, 'attendance_code' => null, 'code_expires_at' => null]);
        $this->audit($actor, 'activity.cancelled', $activity, ['reason' => $reason]);
    }

    /**
     * Present members, absent active members and summary used by the downloadable report.
     *
     * @return array{present: Collection<int, ActivityAttendance>, absent: Collection<int, User>, members: int}
     */
    public function attendanceSummary(ClubActivity $activity): array
    {
        $present = $activity->attendances()->with('user.program')->orderBy('checked_in_at')->get();
        $members = Membership::with('user.program')->where('club_id', $activity->club_id)->where('status', 'active')
            ->where('joined_at', '<=', $activity->ends_at)->get();
        $absent = $members->pluck('user')->reject(fn ($user) => $present->contains('user_id', $user->id))->sortBy('name')->values();

        return ['present' => $present, 'absent' => $absent, 'members' => $members->count()];
    }
}
