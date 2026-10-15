<?php

namespace App\Services\Event;

use App\Models\Competition;
use App\Models\Event;
use App\Models\User;
use App\Notifications\BrudelNotice;
use App\Services\DomainService;
use App\Services\Wita;
use Illuminate\Support\Facades\DB;

/**
 * Event module. A Ketua UKM proposes an event with its competitions; kemahasiswaan approves, requests revision
 * or rejects with a reason. Approved events are run by assigned event admins (never automatically the proposer).
 */
class EventService extends DomainService
{
    public function canEdit(User $actor, Event $event): bool
    {
        if ($actor->isStudentAffairs() || ($event->status === 'approved' && $actor->managesEvent($event->id))) {
            return true;
        }

        return $event->isEditableProposal() && $event->club_id !== null && $actor->leadsClub($event->club_id);
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
            abort_unless($actor->isStudentAffairs() || ($clubId && $actor->leadsClub((int) $clubId)), 403);
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

    public function submit(User $actor, Event $event): void
    {
        DB::transaction(function () use ($actor, $event) {
            $event = Event::lockForUpdate()->findOrFail($event->id);
            abort_unless($event->club_id && $actor->leadsClub($event->club_id), 403);
            $this->ensure($event->isEditableProposal(), 'Pengajuan sudah dikirim.');
            $this->ensure($event->competitions()->exists(), 'Tambahkan minimal satu nomor lomba sebelum mengajukan.');
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
            $event->update(['status' => $decision, 'reason' => $reason, 'decided_by' => $actor->id, 'decided_at' => now()]);
            $this->history($event, 'submitted', $decision, $actor, $reason);
            $this->audit($actor, 'event.'.$decision, $event, ['reason' => $reason]);
            $event->proposer?->notify(new BrudelNotice('Pengajuan event '.$event->name, 'Keputusan kemahasiswaan: '.$this->label($decision).($reason ? '. '.$reason : '.'), '/kelola/event/'.$event->id));
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

        return DB::transaction(function () use ($actor, $event, $competition, $data, $opens, $closes, $starts, $ends, $team, $min, $max) {
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
            ])->save();
            $this->audit($actor, 'competition.saved', $competition, ['fee' => $competition->fee, 'quota' => $competition->quota]);

            return $competition;
        });
    }
}
