<?php

namespace App\Services\Match;

use App\Models\Competition;
use App\Models\Matchup;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\ScoreUpdate;
use App\Models\User;
use App\Services\DomainService;
use App\Services\Scoring\ScoringService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Live score: provisional match scores and performance status, with optimistic versioning so concurrent
 * operators cannot silently overwrite each other. Official results come only from recorded winners or
 * published rankings.
 */
class LiveScoreService extends DomainService
{
    public function __construct(private BracketService $bracket, private ScoringService $scoring) {}

    /**
     * @param  array{score_a: ?int, score_b: ?int, score_detail?: ?string, period_label?: ?string, status: string, version: int}  $data
     */
    public function updateMatch(User $actor, Matchup $matchup, array $data): Matchup
    {
        $competition = $matchup->competition;
        abort_unless($actor->operates($competition), 403);
        $this->ensure($competition->live_score, 'Live score tidak diaktifkan untuk nomor lomba ini.');
        $this->ensure(in_array($data['status'], ['scheduled', 'live'], true), 'Status sementara tidak valid.');

        return DB::transaction(function () use ($actor, $matchup, $data) {
            $current = Matchup::lockForUpdate()->findOrFail($matchup->id);
            abort_if(Participant::whereIn('registration_id', array_filter([$current->registration_a_id, $current->registration_b_id]))->where('user_id', $actor->id)->exists(), 403, 'Operator tidak dapat memperbarui pertandingan yang diikutinya.');
            $this->ensure($current->registration_a_id && $current->registration_b_id, 'Peserta pertandingan belum lengkap.');
            $this->ensure($current->status !== 'finished', 'Pertandingan sudah memiliki hasil resmi.');

            $updated = Matchup::whereKey($current->id)->where('version', $data['version'])->update([
                'score_a' => $data['score_a'],
                'score_b' => $data['score_b'],
                'score_detail' => $data['score_detail'] ?? null,
                'period_label' => $data['period_label'] ?? null,
                'status' => $data['status'],
                'version' => $data['version'] + 1,
                'updated_by' => $actor->id,
                'updated_at' => now(),
            ]);
            $this->ensure($updated === 1, 'Skor sudah diperbarui petugas lain pukul '.$current->updated_at?->timezone(config('brudel.timezone'))->format('H:i:s').' WITA. Muat ulang sebelum menyimpan.');

            $fresh = $current->fresh();
            $this->bracket->log($actor, $fresh, 'score', ['score_a' => $data['score_a'], 'score_b' => $data['score_b'], 'detail' => $data['score_detail'] ?? null, 'period' => $data['period_label'] ?? null, 'status' => $data['status']]);

            return $fresh;
        }, 3);
    }

    public function setPerformance(User $actor, Registration $registration, string $status, ?int $order): void
    {
        $competition = $registration->competition;
        abort_unless($actor->operates($competition), 403);
        $this->ensure($competition->live_score, 'Live score tidak diaktifkan untuk nomor lomba ini.');
        $this->ensure($competition->assessment_type !== 'bracket', 'Status tampil hanya untuk lomba seni/akademik tanpa bagan.');
        $this->ensure(in_array($status, ['waiting', 'performing', 'done'], true), 'Status tampil tidak valid.');
        $this->ensure($registration->status === 'accepted', 'Hanya peserta diterima yang tampil.');

        DB::transaction(function () use ($actor, $registration, $status, $order) {
            $registration = Registration::lockForUpdate()->findOrFail($registration->id);
            $registration->update(['performance_status' => $status, 'performance_order' => $order]);
            ScoreUpdate::create([
                'competition_id' => $registration->competition_id,
                'registration_id' => $registration->id,
                'user_id' => $actor->id,
                'action' => 'performance',
                'payload' => ['status' => $status, 'order' => $order],
                'created_at' => now(),
            ]);
        });
    }

    public function canView(?User $user, Competition $competition): bool
    {
        if (! $competition->live_score || $competition->event?->status !== 'approved') {
            return false;
        }
        if ($competition->resultsLocked()) {
            return $user !== null && $this->seesLockedResults($user, $competition);
        }

        return $competition->live_visibility === 'public' || $user !== null;
    }

    /**
     * Locked results stay with admin jurusan and above, plus those running the competition, who see them anyway.
     */
    private function seesLockedResults(User $user, Competition $competition): bool
    {
        return $user->isStudentAffairs() || $user->isLeadership() || $user->assigned('jurusan') || $user->operates($competition);
    }

    /**
     * Direktorat decides for every competition; an admin jurusan for one that involves its jurusan or an organization it coaches.
     */
    public function canSetAccess(User $user, Competition $competition): bool
    {
        if ($user->isStudentAffairs()) {
            return true;
        }
        $departments = $user->scopeIds('jurusan', 'department_id');
        if ($departments->isEmpty()) {
            return false;
        }
        $club = $competition->event?->club_id;

        return $competition->event?->responsible_id === $user->id
            || ($club !== null && $user->coachesClub($club))
            || Participant::where('competition_id', $competition->id)->whereHas('user.program', fn ($q) => $q->whereIn('department_id', $departments))->exists();
    }

    public function setAccess(User $actor, Competition $competition, string $access): void
    {
        abort_unless($this->canSetAccess($actor, $competition), 403);
        $this->ensure(array_key_exists($access, Competition::RESULT_ACCESS), 'Pilihan akses tidak dikenal.');
        $locked = $access === 'locked';
        $competition->update([
            'results_locked_at' => $locked ? ($competition->results_locked_at ?? now()) : null,
            'results_locked_by' => $locked ? ($competition->results_locked_by ?? $actor->id) : null,
            'live_visibility' => $locked ? $competition->live_visibility : $access,
        ]);
        $this->audit($actor, 'results.access_changed', $competition, ['access' => $access]);
    }

    /**
     * Snapshot for the live board. Rankings are provisional unless results are published.
     *
     * @return array<string, mixed>
     */
    public function board(Competition $competition): array
    {
        $competition->loadMissing('event');
        $last = ScoreUpdate::where('competition_id', $competition->id)->max('created_at');
        $official = $competition->resultsPublished();
        $data = [
            'competition' => $competition,
            'updated_at' => $last ? Carbon::parse($last) : null,
            'official' => $official,
            'matches' => collect(),
            'performances' => collect(),
            'ranking' => collect(),
        ];

        if ($competition->assessment_type === 'bracket') {
            $data['matches'] = $competition->matchups()->with('sideA', 'sideB', 'winner')->get()->groupBy('round');
        } else {
            $data['performances'] = $competition->registrations()->where('status', 'accepted')->with('user')
                ->orderByRaw('performance_order IS NULL')->orderBy('performance_order')->orderBy('id')->get();
        }

        if ($official) {
            $data['ranking'] = $competition->results()->with('registration.user')->get()
                ->map(fn ($result) => ['registration' => $result->registration, 'total' => $result->total, 'rank' => $result->rank, 'note' => $result->note]);
        } elseif ($competition->assessment_type === 'rubric' && $competition->live_show_provisional) {
            $data['ranking'] = collect($this->scoring->ranking($competition));
        }

        return $data;
    }
}
