<?php

namespace App\Services\Event;

use App\Models\Assignment;
use App\Models\Competition;
use App\Models\Event;
use App\Models\User;
use App\Notifications\BrudelNotice;
use App\Services\Campus\MasterData;
use App\Services\DomainService;
use App\Services\Wita;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Event module. A Ketua UKM or admin jurusan proposes an event with its competitions; kemahasiswaan approves,
 * requests revision or rejects with a reason. Every event has a responsible person who appoints its team and hands
 * out the generated pengelola, operator and juri logins, without becoming an event admin automatically.
 */
class EventService extends DomainService
{
    public function __construct(private CompetitionAccountService $accounts) {}

    public function canEdit(User $actor, Event $event): bool
    {
        if ($actor->isStudentAffairs() || ($event->status === 'approved' && $actor->managesEvent($event->id))) {
            return true;
        }

        return $event->isEditableProposal() && $this->mayPropose($actor, $event);
    }

    /**
     * The Ketua of the organizing club, or whoever answers for an event an admin jurusan proposed without a club.
     */
    public function mayPropose(User $actor, Event $event): bool
    {
        return ($event->club_id !== null && $actor->leadsClub($event->club_id)) || $actor->answersForEvent($event);
    }

    /**
     * The responsible person hands the event over; when that post has fallen vacant, whoever runs the club takes it on.
     */
    public function canSetResponsible(User $actor, Event $event): bool
    {
        if ($actor->isStudentAffairs() || $actor->answersForEvent($event)) {
            return true;
        }

        return $this->responsibleVacant($event) && $event->club_id !== null && ($actor->leadsClub($event->club_id) || $actor->administersClub($event->club_id));
    }

    public function responsibleVacant(Event $event): bool
    {
        return ! $event->responsible?->mayAnswerForEvent($event);
    }

    /**
     * Everyone who may answer for the event, labelled with the post that qualifies them.
     *
     * @return Collection<int, string>
     */
    public function responsibleCandidates(Event $event): Collection
    {
        $chairs = app(MasterData::class)->chairPositions();

        return Assignment::with('user', 'department', 'club')->whereHas('user', fn ($q) => $q->where('active', true))
            ->where('starts_at', '<=', now())->where('ends_at', '>', now())
            ->where(fn ($q) => $q->whereIn('role', ['kemahasiswaan', 'jurusan'])
                ->orWhere(fn ($chair) => $chair->where('role', 'pengurus')->where('club_id', $event->club_id)->whereIn('position', $chairs)))
            ->get()->sortBy(fn (Assignment $assignment) => $assignment->user->name)->unique('user_id')
            ->mapWithKeys(fn (Assignment $assignment) => [$assignment->user_id => $assignment->user->name.' · '.($assignment->role === 'pengurus' ? $assignment->position.' '.$assignment->club->name : $assignment->roleLabel())]);
    }

    public function setResponsible(User $actor, Event $event, int $userId): void
    {
        abort_unless($this->canSetResponsible($actor, $event), 403);
        $user = User::find($userId);
        $this->ensure($user !== null && $user->mayAnswerForEvent($event), 'Penanggung jawab harus Ketua organisasi penyelenggara, admin jurusan, atau admin kemahasiswaan.');

        DB::transaction(function () use ($actor, $event, $user) {
            $event = Event::lockForUpdate()->findOrFail($event->id);
            $event->update(['responsible_id' => $user->id]);
            $this->audit($actor, 'event.responsible_changed', $event, ['user_id' => $user->id]);
            if ($user->id !== $actor->id) {
                $user->notify(new BrudelNotice('Penanggung jawab event', 'Anda ditetapkan sebagai penanggung jawab '.$event->name.'.', '/kelola/event/'.$event->id));
            }
        });
    }

    /**
     * @param  array{name: string, organizer: string, pathway: string, field?: ?string, description?: ?string, external_organizer?: ?string, club_id?: ?int}  $data
     */
    public function saveEvent(User $actor, ?Event $event, array $data): Event
    {
        $clubId = $event?->club_id ?? ($data['club_id'] ?? null);
        if ($event) {
            abort_unless($this->canEdit($actor, $event), 403);
        } else {
            abort_unless($this->mayCreate($actor, $clubId ? (int) $clubId : null), 403);
        }
        $this->ensure(in_array($data['pathway'], ['internal', 'delegation'], true), 'Jalur kegiatan tidak valid.');

        return DB::transaction(function () use ($actor, $event, $data, $clubId) {
            $fields = [
                'name' => $data['name'],
                'organizer' => $data['organizer'],
                'pathway' => $data['pathway'],
                'field' => $data['field'] ?? null,
                'description' => $data['description'] ?? null,
                'external_organizer' => $data['external_organizer'] ?? null,
            ];
            if (! $event) {
                $direct = $actor->isStudentAffairs() && ! $clubId;
                $event = Event::create($fields + [
                    'club_id' => $clubId,
                    'proposed_by' => $actor->id,
                    'responsible_id' => $actor->id,
                    'status' => $direct ? 'approved' : 'draft',
                    'decided_by' => $direct ? $actor->id : null,
                    'decided_at' => $direct ? now() : null,
                ]);
                $this->history($event, null, $event->status, $actor, $direct ? 'Kegiatan resmi kemahasiswaan.' : null);
            } else {
                $event->update($fields);
            }
            $this->audit($actor, 'event.saved', $event);

            return $event;
        });
    }

    /**
     * An admin jurusan proposes for the jurusan itself or for an organization it runs; a Ketua only for their own club.
     */
    private function mayCreate(User $actor, ?int $clubId): bool
    {
        if ($actor->isStudentAffairs()) {
            return true;
        }

        return $clubId === null ? $actor->assigned('jurusan') : $actor->leadsClub($clubId) || $actor->administersClub($clubId);
    }

    public function submit(User $actor, Event $event): void
    {
        DB::transaction(function () use ($actor, $event) {
            $event = Event::lockForUpdate()->findOrFail($event->id);
            abort_unless($this->mayPropose($actor, $event), 403);
            $this->ensure($event->isEditableProposal(), 'Pengajuan sudah dikirim.');
            $this->ensure($event->competitions()->exists(), 'Tambahkan minimal satu nomor lomba sebelum mengajukan.');
            $this->ensure(! $this->responsibleVacant($event), 'Tetapkan penanggung jawab event sebelum mengajukan.');
            $from = $event->status;
            $event->update(['status' => 'submitted', 'submitted_at' => now(), 'reason' => null]);
            $this->history($event, $from, 'submitted', $actor);
            $this->audit($actor, 'event.submitted', $event);
        });
    }

    public function decide(User $actor, Event $event, string $decision, ?string $reason): void
    {
        abort_unless($actor->isStudentAffairs(), 403);
        abort_if($event->proposed_by === $actor->id, 403, 'Tidak dapat memutuskan pengajuan sendiri.');
        $this->ensure(in_array($decision, ['approved', 'revision', 'rejected'], true), 'Keputusan tidak valid.');
        $this->requireReason($decision, $reason);

        DB::transaction(function () use ($actor, $event, $decision, $reason) {
            $event = Event::lockForUpdate()->findOrFail($event->id);
            $this->ensure($event->status === 'submitted', 'Pengajuan tidak sedang menunggu keputusan.');
            $this->ensure($decision !== 'approved' || ! $this->responsibleVacant($event), 'Tetapkan penanggung jawab event sebelum menyetujui.');
            $event->update(['status' => $decision, 'reason' => $reason, 'decided_by' => $actor->id, 'decided_at' => now()]);
            $this->history($event, 'submitted', $decision, $actor, $reason);
            $this->audit($actor, 'event.'.$decision, $event, ['reason' => $reason]);
            collect([$event->proposer, $event->responsible])->filter()->unique('id')->each->notify(new BrudelNotice('Pengajuan event '.$event->name, 'Keputusan kemahasiswaan: '.$this->label($decision).($reason ? '. '.$reason : '.'), '/kelola/event/'.$event->id));
            if ($decision === 'approved') {
                foreach ($event->competitions as $competition) {
                    $this->accounts->provision($competition, $actor);
                }
            }
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveCompetition(User $actor, Event $event, ?Competition $competition, array $data): Competition
    {
        abort_unless($this->canEdit($actor, $event), 403);
        abort_if($competition && $competition->event_id !== $event->id, 404);

        $opens = Wita::toUtc($data['opens_at']);
        $closes = Wita::toUtc($data['closes_at']);
        $starts = Wita::toUtc($data['starts_at']);
        $ends = Wita::toUtc($data['ends_at'] ?? null);
        $this->ensure($opens && $closes && $closes->gt($opens), 'Penutupan pendaftaran harus setelah pembukaan.');
        $this->ensure($starts && $starts->gte($opens), 'Jadwal lomba harus setelah pendaftaran dibuka.');
        $this->ensure(! $ends || $ends->gte($starts), 'Jadwal selesai harus setelah jadwal mulai.');
        $team = $data['format'] === 'team';
        $min = $team ? (int) $data['min_members'] : 1;
        $max = $team ? (int) $data['max_members'] : 1;
        $this->ensure($min >= ($team ? 2 : 1) && $max >= $min, 'Jumlah anggota tim minimal 2 dan maksimum tidak boleh lebih kecil dari minimum.');
        $judges = $data['assessment_type'] === 'rubric' ? (int) ($data['judge_count'] ?? 3) : 0;
        $maxJudges = (int) config('brudel.max_judges');
        $manualJudges = $competition ? Assignment::where('role', 'penilai')->where('competition_id', $competition->id)->where('ends_at', '>', now())
            ->whereNotIn('user_id', $competition->accounts()->pluck('user_id'))->count() : 0;
        $this->ensure($judges + $manualJudges <= $maxJudges, 'Total juri per nomor lomba maksimal '.$maxJudges.' (saat ini '.$manualJudges.' juri ditugaskan manual).');
        $this->ensure($data['assessment_type'] !== 'rubric' || $judges + $manualJudges >= 1, 'Lomba dengan rubrik memerlukan minimal 1 juri.');

        $saved = DB::transaction(function () use ($actor, $event, $competition, $data, $opens, $closes, $starts, $ends, $team, $min, $max, $judges) {
            if ($competition) {
                $competition = Competition::lockForUpdate()->findOrFail($competition->id);
                $locked = $competition->registrations()->whereNotIn('status', ['draft', 'cancelled'])->exists();
                $this->ensure(! $locked || ($competition->format === $data['format'] && $competition->min_members === $min && $competition->max_members === $max),
                    'Format dan jumlah anggota tidak dapat diubah setelah ada pendaftaran terkirim.');
                $holding = $competition->registrations()->whereIn('status', ['program_review', 'submitted', 'accepted'])->count();
                $this->ensure((int) $data['quota'] >= $holding, 'Kuota tidak boleh lebih kecil dari '.$holding.' pendaftaran terkirim.');
                $this->ensure(! $competition->resultsPublished() || $competition->assessment_type === $data['assessment_type'], 'Format penilaian tidak dapat diubah setelah hasil disahkan.');
            }
            $competition ??= new Competition(['event_id' => $event->id]);
            $competition->fill([
                'name' => $data['name'],
                'field' => $data['field'],
                'level' => config('brudel.levels')[(int) $data['level']] ?? 'Internal Poltekkes',
                'description' => $data['description'],
                'requirements' => $data['requirements'],
                'format' => $team ? 'team' : 'individual',
                'min_members' => $min,
                'max_members' => $max,
                'quota' => (int) $data['quota'],
                'fee' => (int) $data['fee'],
                'fee_basis' => $data['fee_basis'],
                'fee_notes' => $data['fee_notes'] ?? null,
                'requires_document' => (bool) ($data['requires_document'] ?? false),
                'requires_program_approval' => (bool) ($data['requires_program_approval'] ?? false),
                'opens_at' => $opens,
                'closes_at' => $closes,
                'starts_at' => $starts,
                'ends_at' => $ends,
                'location' => $data['location'],
                'assessment_type' => $data['assessment_type'],
                'tie_break' => $data['tie_break'] ?? 'criteria',
                'live_score' => (bool) ($data['live_score'] ?? false),
                'live_visibility' => $data['live_visibility'] ?? 'auth',
                'live_show_provisional' => (bool) ($data['live_show_provisional'] ?? false),
                'external_info' => $data['external_info'] ?? null,
                'judge_count' => $judges,
            ])->save();
            $this->audit($actor, 'competition.saved', $competition, ['fee' => $competition->fee, 'quota' => $competition->quota, 'judges' => $judges]);

            return $competition;
        });
        $this->accounts->provision($saved, $actor);

        return $saved;
    }
}
