<?php

namespace App\Services\Scoring;

use App\Models\AwardResult;
use App\Models\Certificate;
use App\Models\Competition;
use App\Models\CompetitionResult;
use App\Models\Matchup;
use App\Models\Participant;
use App\Models\User;
use App\Notifications\BrudelNotice;
use App\Services\DomainService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Official results (pengesahan). Results are only published when every required score or match is complete;
 * reopening requires kemahasiswaan, a reason, and no dependent awards or certificates.
 */
class ResultService extends DomainService
{
    public function __construct(private ScoringService $scoring) {}

    public function publish(User $actor, Competition $competition): void
    {
        abort_unless($actor->managesCompetition($competition), 403);

        DB::transaction(function () use ($actor, $competition) {
            $competition = Competition::lockForUpdate()->findOrFail($competition->id);
            $this->ensure(! $competition->resultsPublished(), 'Hasil sudah disahkan.');
            $this->ensure(in_array($competition->assessment_type, ['rubric', 'bracket'], true), 'Nomor lomba tanpa format penilaian tidak memiliki hasil untuk disahkan.');
            $rows = $competition->assessment_type === 'rubric' ? $this->rubricRows($competition) : $this->bracketRows($competition);
            CompetitionResult::where('competition_id', $competition->id)->delete();
            foreach ($rows as $row) {
                CompetitionResult::create([
                    'competition_id' => $competition->id,
                    'registration_id' => $row['registration_id'],
                    'rank' => $row['rank'],
                    'total' => $row['total'],
                    'note' => $row['note'],
                ]);
            }
            $competition->update(['results_published_at' => now(), 'results_published_by' => $actor->id]);
            $this->audit($actor, 'results.published', $competition, ['rows' => $rows]);

            $users = User::whereIn('id', Participant::whereIn('registration_id', array_column($rows, 'registration_id'))->pluck('user_id'))->get();
            Notification::send($users, new BrudelNotice('Hasil resmi '.$competition->name, 'Hasil telah disahkan. Lihat peringkat di halaman live/hasil.', '/live/'.$competition->id));
        });
    }

    public function reopen(User $actor, Competition $competition, string $reason): void
    {
        abort_unless($actor->isStudentAffairs(), 403);
        $this->ensure(mb_strlen(trim($reason)) >= 10, 'Alasan koreksi minimal 10 karakter.');

        DB::transaction(function () use ($actor, $competition, $reason) {
            $competition = Competition::lockForUpdate()->findOrFail($competition->id);
            $this->ensure($competition->resultsPublished(), 'Hasil belum disahkan.');
            $awards = AwardResult::where('competition_id', $competition->id)->count();
            $certificates = Certificate::where('competition_id', $competition->id)->where('status', 'valid')->count();
            $this->ensure($awards === 0 && $certificates === 0,
                'Koreksi berdampak pada '.$awards.' penghargaan dan '.$certificates.' sertifikat sah. Cabut atau tangani keduanya terlebih dahulu.');
            $snapshot = $competition->results()->get(['registration_id', 'rank', 'total', 'note'])->toArray();
            CompetitionResult::where('competition_id', $competition->id)->delete();
            $competition->update(['results_published_at' => null, 'results_published_by' => null]);
            $this->audit($actor, 'results.reopened', $competition, ['reason' => $reason, 'previous' => $snapshot]);
        });
    }

    /**
     * @return list<array{registration_id: int, rank: int, total: ?float, note: ?string}>
     */
    private function rubricRows(Competition $competition): array
    {
        $missing = $this->scoring->missing($competition);
        $this->ensure($missing === [], 'Penilaian belum lengkap: '.implode(' ', array_slice($missing, 0, 5)).(count($missing) > 5 ? ' (dan '.(count($missing) - 5).' lainnya)' : ''));
        $ranking = $this->scoring->ranking($competition);
        $this->ensure($ranking !== [], 'Belum ada nilai untuk disahkan.');

        return array_map(fn ($row) => ['registration_id' => $row['registration']->id, 'rank' => $row['rank'], 'total' => $row['total'], 'note' => $row['note']], $ranking);
    }

    /**
     * Final winner = 1, finalist = 2, semi-final losers share 3, earlier losers share rank by round.
     *
     * @return list<array{registration_id: int, rank: int, total: null, note: ?string}>
     */
    private function bracketRows(Competition $competition): array
    {
        $matches = Matchup::where('competition_id', $competition->id)->get();
        $this->ensure($matches->isNotEmpty(), 'Bagan belum dibuat.');
        $this->ensure($matches->every(fn ($match) => $match->status === 'finished'), 'Masih ada pertandingan tanpa hasil resmi.');
        $rounds = $matches->max('round');
        $final = $matches->firstWhere('round', $rounds);

        $rows = [['registration_id' => $final->winner_registration_id, 'rank' => 1, 'total' => null, 'note' => 'Juara']];
        foreach ($matches->where('is_bye', false)->sortByDesc('round') as $match) {
            $loser = $match->winner_registration_id === $match->registration_a_id ? $match->registration_b_id : $match->registration_a_id;
            $rank = $match->round === $rounds ? 2 : (2 ** ($rounds - $match->round)) + 1;
            $rows[] = ['registration_id' => $loser, 'rank' => $rank, 'total' => null, 'note' => $match->round === $rounds ? 'Finalis' : 'Kalah di babak '.$match->round];
        }

        return $rows;
    }
}
