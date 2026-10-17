<?php

namespace App\Services\Delegation;

use App\Models\Assignment;
use App\Models\AwardRecipient;
use App\Models\AwardResult;
use App\Models\Competition;
use App\Models\Delegation;
use App\Models\DelegationMember;
use App\Models\User;
use App\Notifications\BrudelNotice;
use App\Services\DomainService;
use App\Services\PrivateFiles;
use App\Services\Wita;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Delegation module: selection result → proposal → leadership decision → assignment letter → participation → outcome.
 * Passing a selection never approves a delegation by itself.
 */
class DelegationService extends DomainService
{
    public function __construct(private PrivateFiles $files) {}

    public function canManage(User $user, Delegation $delegation): bool
    {
        return $user->isStudentAffairs() || ($delegation->competition && $user->managesCompetition($delegation->competition));
    }

    /**
     * Kemahasiswaan jurusan follows (read-only) every delegation that includes a student of its jurusan.
     */
    public function canView(User $user, Delegation $delegation): bool
    {
        $departments = $user->scopeIds('jurusan', 'department_id');

        return $this->canManage($user, $delegation) || $user->isLeadership() || $delegation->members()->where('user_id', $user->id)->exists()
            || ($departments->isNotEmpty() && $delegation->members()->whereHas('user.program', fn ($q) => $q->whereIn('department_id', $departments))->exists());
    }

    /**
     * @param  array{competition_id?: ?int, title: string, destination_event: string, organizer: string, level: int, field: string, starts_at: string, ends_at?: ?string, location: string, reason: string, participants: list<int>, companions?: ?string}  $data
     */
    public function propose(User $actor, array $data): Delegation
    {
        $competition = isset($data['competition_id']) ? Competition::find($data['competition_id']) : null;
        abort_unless($actor->isStudentAffairs() || ($competition && $actor->managesCompetition($competition)), 403);
        $this->ensure(isset(config('brudel.levels')[(int) $data['level']]), 'Tingkat tidak valid.');
        $participants = User::whereIn('id', $data['participants'] ?? [])->where('active', true)->whereNotNull('nim')->get();
        $this->ensure($participants->isNotEmpty() && $participants->count() === count(array_unique($data['participants'] ?? [])), 'Pilih minimal satu peserta mahasiswa aktif.');
        if ($competition) {
            $eligible = $competition->registrations()->where('status', 'accepted')->with('participants')->get()->flatMap->participants->pluck('user_id');
            $this->ensure($participants->every(fn ($user) => $eligible->contains($user->id)), 'Peserta harus berasal dari pendaftaran seleksi yang diterima.');
        }
        $identifiers = collect(preg_split('/[\s,;]+/', (string) ($data['companions'] ?? ''), -1, PREG_SPLIT_NO_EMPTY))->unique();
        $companions = User::where('active', true)->where(fn ($q) => $q->whereIn('email', $identifiers->map(fn ($v) => mb_strtolower($v)))->orWhereIn('nim', $identifiers))->get();
        $this->ensure($companions->count() === $identifiers->count(), 'Pendamping tidak ditemukan. Gunakan email atau NIM terdaftar.');
        $starts = Wita::toUtc($data['starts_at']);
        $ends = Wita::toUtc($data['ends_at'] ?? null);
        $this->ensure($starts !== null && (! $ends || $ends->gte($starts)), 'Jadwal delegasi tidak valid.');

        return DB::transaction(function () use ($actor, $data, $competition, $participants, $companions, $starts, $ends) {
            $delegation = Delegation::create([
                'competition_id' => $competition?->id,
                'title' => $data['title'],
                'destination_event' => $data['destination_event'],
                'organizer' => $data['organizer'],
                'level' => (int) $data['level'],
                'field' => $data['field'],
                'starts_at' => $starts,
                'ends_at' => $ends,
                'location' => $data['location'],
                'reason' => $data['reason'],
                'proposed_by' => $actor->id,
            ]);
            foreach ($participants as $user) {
                $registration = $competition?->registrations()->where('status', 'accepted')->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))->first();
                DelegationMember::create(['delegation_id' => $delegation->id, 'user_id' => $user->id, 'role' => 'peserta', 'registration_id' => $registration?->id]);
            }
            foreach ($companions->whereNotIn('id', $participants->pluck('id')) as $user) {
                DelegationMember::create(['delegation_id' => $delegation->id, 'user_id' => $user->id, 'role' => 'pendamping']);
            }
            $this->history($delegation, null, 'draft', $actor);
            $this->audit($actor, 'delegation.proposed', $delegation);

            return $delegation;
        });
    }

    public function submit(User $actor, Delegation $delegation): void
    {
        abort_unless($this->canManage($actor, $delegation), 403);
        DB::transaction(function () use ($actor, $delegation) {
            $delegation = Delegation::lockForUpdate()->findOrFail($delegation->id);
            $this->ensure(in_array($delegation->status, ['draft', 'revision'], true), 'Usulan sudah dikirim.');
            $from = $delegation->status;
            $delegation->update(['status' => 'submitted', 'submitted_at' => now()]);
            $this->history($delegation, $from, 'submitted', $actor);
            $this->audit($actor, 'delegation.submitted', $delegation);
            $leaders = User::whereIn('id', Assignment::where('role', 'pimpinan')->where('starts_at', '<=', now())->where('ends_at', '>', now())->pluck('user_id'))->get();
            Notification::send($leaders, new BrudelNotice('Usulan delegasi menunggu keputusan', $delegation->title, '/delegasi/'.$delegation->id));
        });
    }

    public function decide(User $actor, Delegation $delegation, string $decision, ?string $note): void
    {
        abort_unless($actor->isLeadership(), 403);
        abort_if($delegation->members()->where('user_id', $actor->id)->exists() || $delegation->proposed_by === $actor->id, 403, 'Tidak dapat memutuskan usulan yang melibatkan diri sendiri.');
        $this->ensure(in_array($decision, ['approved', 'revision', 'rejected'], true), 'Keputusan tidak valid.');
        $this->requireReason($decision, $note);

        DB::transaction(function () use ($actor, $delegation, $decision, $note) {
            $delegation = Delegation::lockForUpdate()->findOrFail($delegation->id);
            $this->ensure($delegation->status === 'submitted', 'Usulan tidak sedang menunggu keputusan.');
            $delegation->update(['status' => $decision, 'decided_by' => $actor->id, 'decided_at' => now(), 'decision_note' => $note]);
            $this->history($delegation, 'submitted', $decision, $actor, $note);
            $this->audit($actor, 'delegation.'.$decision, $delegation, ['note' => $note]);
            $users = User::whereIn('id', $delegation->members()->pluck('user_id')->push($delegation->proposed_by))->get();
            Notification::send($users, new BrudelNotice('Keputusan delegasi '.$delegation->title, $this->label($decision).($note ? ': '.$note : '.'), '/delegasi/'.$delegation->id));
        });
    }

    public function uploadLetter(User $actor, Delegation $delegation, string $number, UploadedFile $file): void
    {
        abort_unless($actor->isStudentAffairs(), 403);
        $stored = $this->files->storePdf($file, 'assignment-letters/'.$delegation->id);

        try {
            DB::transaction(function () use ($actor, $delegation, $number, $stored) {
                $delegation = Delegation::lockForUpdate()->findOrFail($delegation->id);
                $this->ensure($delegation->status === 'approved', 'Surat tugas hanya untuk delegasi yang disetujui pimpinan.');
                $previous = $delegation->only(['letter_number', 'letter_path']);
                $delegation->update(['letter_number' => $number, 'letter_path' => $stored[0], 'letter_disk' => $stored[1], 'letter_uploaded_by' => $actor->id, 'letter_uploaded_at' => now()]);
                $this->audit($actor, 'delegation.letter', $delegation, ['number' => $number, 'previous' => $previous]);
                Notification::send(User::whereIn('id', $delegation->members()->pluck('user_id'))->get(), new BrudelNotice('Surat tugas tersedia', $delegation->title.' · '.$number, '/delegasi/'.$delegation->id));
            });
        } catch (\Throwable $e) {
            $this->files->delete($stored[0], $stored[1]);
            throw $e;
        }
    }

    /**
     * @param  array{outcome: string, award?: ?string, awarded_on?: ?string}  $data
     */
    public function recordOutcome(User $actor, Delegation $delegation, array $data, ?UploadedFile $evidence): void
    {
        abort_unless($this->canManage($actor, $delegation), 403);
        $this->ensure(! ($data['award'] ?? null) || $evidence !== null, 'Unggah bukti penghargaan (PDF) untuk dicatat sebagai prestasi.');
        $stored = $evidence ? $this->files->storePdf($evidence, 'achievements/delegation-'.$delegation->id) : null;

        try {
            DB::transaction(function () use ($actor, $delegation, $data, $stored) {
                $delegation = Delegation::lockForUpdate()->findOrFail($delegation->id);
                $this->ensure($delegation->status === 'approved' && $delegation->letter_path, 'Hasil dicatat setelah delegasi disetujui dan surat tugas diunggah.');
                $delegation->update(['outcome' => $data['outcome'], 'outcome_recorded_at' => now(), 'status' => 'completed']);
                $this->history($delegation, 'approved', 'completed', $actor, $data['outcome']);
                if ($data['award'] ?? null) {
                    $participants = $delegation->members()->where('role', 'peserta')->get();
                    $award = AwardResult::create([
                        'source' => 'external',
                        'delegation_id' => $delegation->id,
                        'title' => $delegation->destination_event,
                        'organizer' => $delegation->organizer,
                        'field' => $delegation->field,
                        'level' => $delegation->level,
                        'award' => $data['award'],
                        'awarded_on' => $data['awarded_on'] ?? now()->toDateString(),
                        'is_team' => $participants->count() > 1,
                        'evidence_path' => $stored[0],
                        'evidence_disk' => $stored[1],
                        'status' => 'pending',
                        'submitted_by' => $actor->id,
                    ]);
                    foreach ($participants as $member) {
                        AwardRecipient::create(['award_result_id' => $award->id, 'user_id' => $member->user_id, 'team_role' => $participants->count() > 1 ? 'anggota' : 'individu']);
                    }
                    $this->audit($actor, 'award.submitted', $award, ['delegation_id' => $delegation->id]);
                }
                $this->audit($actor, 'delegation.completed', $delegation, ['outcome' => $data['outcome']]);
            });
        } catch (\Throwable $e) {
            $this->files->delete($stored[0] ?? null, $stored[1] ?? null);
            throw $e;
        }
    }
}
