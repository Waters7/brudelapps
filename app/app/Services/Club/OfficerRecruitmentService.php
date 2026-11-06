<?php

namespace App\Services\Club;

use App\Models\Club;
use App\Models\Membership;
use App\Models\OfficerCandidate;
use App\Models\OfficerRecruitment;
use App\Models\User;
use App\Notifications\BrudelNotice;
use App\Services\Account\AssignmentService;
use App\Services\Campus\MasterData;
use App\Services\DomainService;
use App\Services\Wita;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * New officers for BEM, MPM and HIMAJU. The outgoing officers set the criteria, students stand for a post, and the
 * outgoing officers validate each candidate. An accepted candidate takes office when the new term starts.
 */
class OfficerRecruitmentService extends DomainService
{
    public function __construct(private AssignmentService $assignments) {}

    /**
     * Ketua, Wakil Ketua and Sekretaris, or whoever else may maintain the officer roster of the organization.
     */
    public function canManage(User $user, Club $club): bool
    {
        return ! $club->isUkm() && $user->managesOfficers($club->id);
    }

    public function canView(User $user, Club $club): bool
    {
        return ! $club->isUkm() && $user->coachesClub($club->id);
    }

    /**
     * Why this student cannot stand, or null when the form may be shown.
     */
    public function blockedReason(User $student, OfficerRecruitment $recruitment): ?string
    {
        $club = $recruitment->club;

        return match (true) {
            ! $student->active || ! $student->nim => 'Hanya mahasiswa aktif yang dapat mengajukan diri.',
            ! $recruitment->isOpen() => 'Pendaftaran pengurus sedang tidak dibuka.',
            $club->category === 'himaju' && $student->departmentId() !== $club->department_id => 'Khusus mahasiswa Jurusan '.$club->department?->name.'.',
            default => null,
        };
    }

    /**
     * @param  array{title: string, period: string, description?: ?string, positions: list<string>, criteria: string, opens_at: string, closes_at: string, term_starts_at: string, term_ends_at: string}  $data
     */
    public function save(User $actor, Club $club, ?OfficerRecruitment $recruitment, array $data): OfficerRecruitment
    {
        abort_unless($this->canManage($actor, $club), 403);
        $opens = Wita::toUtc($data['opens_at']);
        $closes = Wita::toUtc($data['closes_at']);
        $starts = Wita::toUtc($data['term_starts_at']);
        $ends = Wita::toUtc($data['term_ends_at']);
        $this->ensure($opens && $closes && $closes->gt($opens), 'Penutupan pendaftaran harus setelah pembukaan.');
        $this->ensure($starts && $ends && $ends->gt($starts), 'Masa jabatan berakhir harus setelah dimulai.');
        $this->ensure($starts->gte($closes), 'Masa jabatan dimulai setelah pendaftaran ditutup.');
        $this->ensure($ends->lte($starts->copy()->addYears(5)), 'Masa jabatan maksimal 5 tahun.');

        $positions = array_values(array_intersect(app(MasterData::class)->officerPositions(), $data['positions']));
        $this->ensure($positions !== [] && count($positions) === count(array_unique($data['positions'])), 'Pilih jabatan yang dibuka dari daftar jabatan pengurus.');
        $criteria = array_values(array_filter(array_map('trim', preg_split('/\R/', $data['criteria']))));
        $this->ensure($criteria !== [] && count($criteria) <= 15, 'Tulis 1 sampai 15 kriteria, satu kriteria per baris.');

        return DB::transaction(function () use ($actor, $club, $recruitment, $data, $opens, $closes, $starts, $ends, $positions, $criteria) {
            Club::whereKey($club->id)->lockForUpdate()->first();
            $overlap = OfficerRecruitment::where('club_id', $club->id)->when($recruitment, fn ($q) => $q->whereKeyNot($recruitment->id))
                ->where('opens_at', '<', $closes)->where('closes_at', '>', $opens)->exists();
            $this->ensure(! $overlap, 'Jadwal ini bertabrakan dengan rekrutmen pengurus lain.');
            if ($recruitment?->candidates()->exists()) {
                $this->ensure($recruitment->criteria === $criteria && $recruitment->positions === $positions, 'Kriteria dan jabatan terkunci setelah ada kandidat.');
            }

            $recruitment ??= new OfficerRecruitment(['club_id' => $club->id, 'created_by' => $actor->id]);
            $recruitment->fill([
                'title' => trim($data['title']), 'period' => trim($data['period']), 'description' => $data['description'] ?? null, 'positions' => $positions, 'criteria' => $criteria,
                'opens_at' => $opens, 'closes_at' => $closes, 'term_starts_at' => $starts, 'term_ends_at' => $ends,
            ])->save();
            $this->audit($actor, 'officer_recruitment.saved', $recruitment);

            return $recruitment;
        });
    }

    public function apply(User $student, OfficerRecruitment $recruitment, string $position, string $statement): OfficerCandidate
    {
        $blocked = $this->blockedReason($student, $recruitment);
        $this->ensure($blocked === null, (string) $blocked);
        $this->ensure(in_array($position, $recruitment->positions, true), 'Jabatan ini tidak dibuka pada rekrutmen ini.');

        return DB::transaction(function () use ($student, $recruitment, $position, $statement) {
            User::whereKey($student->id)->lockForUpdate()->first();
            $candidate = OfficerCandidate::firstOrNew(['officer_recruitment_id' => $recruitment->id, 'user_id' => $student->id]);
            $this->ensure(! $candidate->exists || $candidate->status === 'cancelled', 'Kamu sudah mengajukan diri pada rekrutmen ini.');
            $from = $candidate->exists ? $candidate->status : null;
            $candidate->fill(['position' => $position, 'statement' => trim($statement), 'status' => 'submitted'])->save();
            $this->history($candidate, $from, 'submitted', $student);
            $this->audit($student, 'officer_candidate.submitted', $candidate, ['position' => $position]);
            Notification::send($this->outgoingOfficers($recruitment->club), new BrudelNotice('Kandidat pengurus '.$recruitment->club->name, $student->name.' mengajukan diri sebagai '.$position.'.', '/kelola/rekrutmen-pengurus/'.$recruitment->id));

            return $candidate;
        });
    }

    public function cancel(User $student, OfficerCandidate $candidate): void
    {
        abort_unless($candidate->user_id === $student->id, 403);
        DB::transaction(function () use ($student, $candidate) {
            $locked = OfficerCandidate::lockForUpdate()->findOrFail($candidate->id);
            $this->ensure($locked->status === 'submitted', 'Pengajuan ini sudah diputuskan.');
            $locked->update(['status' => 'cancelled']);
            $this->history($locked, 'submitted', 'cancelled', $student);
            $this->audit($student, 'officer_candidate.cancelled', $locked);
        });
    }

    /**
     * Records the criteria found met and the decision. Acceptance needs every criterion, a rejection needs a note.
     *
     * @param  list<int>  $met
     */
    public function decide(User $actor, OfficerCandidate $candidate, string $decision, array $met, ?string $note): void
    {
        $recruitment = $candidate->recruitment;
        abort_unless($this->canManage($actor, $recruitment->club), 403);
        abort_if($candidate->user_id === $actor->id, 403, 'Tidak dapat memvalidasi pengajuan sendiri.');
        $this->ensure(in_array($decision, ['accepted', 'rejected'], true), 'Keputusan tidak dikenal.');
        $note = trim((string) $note);
        $met = array_values(array_unique(array_intersect($met, array_keys($recruitment->criteria))));
        $this->ensure($decision === 'rejected' || count($met) === count($recruitment->criteria), 'Kandidat yang diterima harus memenuhi semua kriteria.');
        $this->ensure($decision === 'accepted' || mb_strlen($note) >= 5, 'Catatan minimal 5 karakter wajib diisi bila kandidat ditolak.');

        DB::transaction(function () use ($actor, $candidate, $recruitment, $decision, $met, $note) {
            $locked = OfficerCandidate::with('user')->lockForUpdate()->findOrFail($candidate->id);
            $this->ensure($locked->status === 'submitted', 'Pengajuan ini sudah diputuskan.');
            if ($decision === 'accepted') {
                $this->install($actor, $recruitment, $locked);
            }
            $locked->update(['status' => $decision, 'met_criteria' => $met, 'review_note' => $note ?: null, 'reviewed_by' => $actor->id, 'reviewed_at' => now()]);
            $this->history($locked, 'submitted', $decision, $actor, $note ?: null);
            $this->audit($actor, 'officer_candidate.'.$decision, $locked);
            if ($decision === 'rejected') {
                $locked->user->notify(new BrudelNotice('Pengajuan pengurus '.$recruitment->club->name, 'Pengajuanmu belum dapat diterima. '.$note, '/ukm-saya'));
            }
        });
    }

    private function install(User $actor, OfficerRecruitment $recruitment, OfficerCandidate $candidate): void
    {
        $this->ensure($recruitment->term_ends_at->isFuture(), 'Masa jabatan rekrutmen ini sudah berakhir.');
        $master = app(MasterData::class);
        $taken = in_array($candidate->position, array_merge($master->chairPositions(), $master->officerManagers()), true)
            && OfficerCandidate::where('officer_recruitment_id', $recruitment->id)->where('position', $candidate->position)->where('status', 'accepted')->exists();
        $this->ensure(! $taken, 'Jabatan '.$candidate->position.' sudah diisi kandidat lain.');

        $club = $recruitment->club;
        $membership = Membership::lockForUpdate()->firstOrNew(['club_id' => $club->id, 'user_id' => $candidate->user_id]);
        if (! ($membership->exists && $membership->status === 'active')) {
            $reason = 'Terpilih sebagai pengurus periode '.$recruitment->period;
            $from = $membership->exists ? $membership->status : null;
            $membership->fill(['status' => 'active', 'joined_at' => now(), 'left_at' => null, 'status_reason' => $reason])->save();
            $this->history($membership, $from, 'active', $actor, $reason);
        }

        $this->assignments->scheduleOfficer($actor, $club, $candidate->user, $candidate->position, $recruitment->period, $recruitment->term_starts_at->max(now()->startOfMinute()), $recruitment->term_ends_at);
    }

    /**
     * @return Collection<int, User>
     */
    private function outgoingOfficers(Club $club)
    {
        return User::where('active', true)->whereHas('currentAssignments', fn ($q) => $q->where('role', 'pengurus')->where('club_id', $club->id)
            ->whereIn('position', app(MasterData::class)->officerManagers()))->get();
    }
}
