<?php

namespace Tests\Feature;

use App\Models\ClubApplication;
use App\Models\Competition;
use App\Models\CompetitionResult;
use App\Models\Matchup;
use App\Models\Membership;
use App\Models\Recruitment;
use App\Models\Registration;
use App\Models\ScoreUpdate;
use App\Models\StatusHistory;
use App\Models\User;
use App\Services\Match\BracketService;
use App\Services\Registration\EnrollmentService;
use App\Services\Scoring\ResultService;
use Closure;
use Illuminate\Console\Application;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\ValidationException;
use Laravel\SerializableClosure\SerializableClosure;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresEnvironmentVariable;

/**
 * Real races between two PHP processes on PostgreSQL, each with its own connection and transaction.
 *
 * Workers run through `php artisan invoke-serialized-closure` (the worker of Laravel's process concurrency driver).
 * The test holds the first row of the service's lock order until every worker is waiting on it, so the transactions
 * provably overlap, then releases it: exactly one may win. Other processes only see committed rows, so these tests
 * run outside the per-test transaction and migrate fresh before and after each test.
 */
#[Group('pgsql')]
#[RequiresEnvironmentVariable('DB_CONNECTION', 'pgsql')]
class ConcurrencyTest extends DemoTestCase
{
    /** @var list<string> */
    protected array $connectionsToTransact = [];

    protected function setUp(): void
    {
        RefreshDatabaseState::$migrated = false;
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        RefreshDatabaseState::$migrated = false;
    }

    public function test_last_competition_place_goes_to_exactly_one_of_two_concurrent_submissions(): void
    {
        $competition = $this->competition('Badminton Tunggal');
        $competition->update(['quota' => 1]);
        $students = ['mahasiswa' => $this->user('mahasiswa')->id, 'anggota' => $this->user('anggota')->id];
        $drafts = array_map(fn (int $id) => app(EnrollmentService::class)->draft(User::findOrFail($id), $competition->id, [
            'motivation' => 'Saya siap berlatih dan mengikuti kompetisi kampus.',
        ])->id, $students);

        $results = $this->raceOn('competitions', $competition->id, [
            'mahasiswa' => static fn () => app(EnrollmentService::class)->submit(User::findOrFail($students['mahasiswa']), $drafts['mahasiswa']),
            'anggota' => static fn () => app(EnrollmentService::class)->submit(User::findOrFail($students['anggota']), $drafts['anggota']),
        ]);

        [$winner, $loser] = $this->assertExactlyOneWinner($results, 'Kuota lomba sudah penuh.');
        $this->assertSame([$drafts[$winner]], Registration::where('competition_id', $competition->id)->whereIn('status', Registration::HOLDING)->pluck('id')->all());
        $this->assertSame('draft', Registration::findOrFail($drafts[$loser])->status);
        $this->assertSame(1, $this->audits('registration.submitted'));
        $this->assertSame(1, StatusHistory::where('subject_type', 'registrations')->where('to_status', 'submitted')->count());
    }

    public function test_last_club_place_is_accepted_for_exactly_one_of_two_concurrent_reviews(): void
    {
        $recruitment = Recruitment::orderBy('id')->firstOrFail();
        $recruitment->update(['quota' => 1]);
        $students = ['mahasiswa' => $this->user('mahasiswa')->id, 'anggota' => $this->user('anggota')->id];
        $applications = array_map(fn (int $id) => app(EnrollmentService::class)->applyClub(User::findOrFail($id), $recruitment->id,
            'Saya ingin bergabung dan belajar bersama komunitas.')->id, $students);
        $reviewer = $this->user('pengurus')->id;

        $results = $this->raceOn('recruitments', $recruitment->id, [
            'mahasiswa' => static fn () => app(EnrollmentService::class)->reviewClub(User::findOrFail($reviewer), $applications['mahasiswa'], 'accepted', null),
            'anggota' => static fn () => app(EnrollmentService::class)->reviewClub(User::findOrFail($reviewer), $applications['anggota'], 'accepted', null),
        ]);

        [$winner, $loser] = $this->assertExactlyOneWinner($results, 'Kuota UKM sudah penuh.');
        $this->assertSame('accepted', ClubApplication::findOrFail($applications[$winner])->status);
        $this->assertSame('submitted', ClubApplication::findOrFail($applications[$loser])->status);
        $this->assertSame([$students[$winner]], Membership::where('club_id', $recruitment->club_id)->whereIn('user_id', $students)->pluck('user_id')->all());
        $this->assertSame(1, $this->audits('club.accepted'));
        $this->assertSame(1, DB::table('notifications')->where('data', 'like', '%diperbarui%')->count());
    }

    public function test_concurrent_winner_entries_with_the_same_version_record_one_official_result(): void
    {
        $tennis = $this->competition('Tenis Meja Tunggal');
        app(BracketService::class)->generate($this->user('event'), $tennis);
        $match = Matchup::where('competition_id', $tennis->id)->where('round', 1)->where('is_bye', false)->firstOrFail();
        $next = Matchup::findOrFail($match->next_matchup_id);
        $sides = ['operator' => $match->registration_a_id, 'event' => $match->registration_b_id];
        $officers = ['operator' => $this->user('operator')->id, 'event' => $this->user('event')->id];
        [$matchId, $version] = [$match->id, $match->version];

        $results = $this->raceOn('competitions', $tennis->id, [
            'operator' => static fn () => app(BracketService::class)->recordWinner(User::findOrFail($officers['operator']), Matchup::findOrFail($matchId), $sides['operator'], 3, 1, null, $version),
            'event' => static fn () => app(BracketService::class)->recordWinner(User::findOrFail($officers['event']), Matchup::findOrFail($matchId), $sides['event'], 1, 3, null, $version),
        ]);

        [$winner] = $this->assertExactlyOneWinner($results, 'Hasil sudah resmi. Gunakan koreksi beralasan.');
        $match->refresh();
        $this->assertSame($sides[$winner], $match->winner_registration_id);
        $this->assertSame($officers[$winner], $match->updated_by);
        $this->assertSame($version + 1, $match->version);
        $this->assertSame(1, ScoreUpdate::where('matchup_id', $matchId)->where('action', 'winner')->count());
        $this->assertSame(1, $this->audits('match.winner'));
        $slot = $match->next_slot === 'a' ? 'registration_a_id' : 'registration_b_id';
        $this->assertSame($sides[$winner], $next->fresh()->{$slot});
        $this->assertSame($next->version + 1, $next->fresh()->version, 'Pemenang hanya dipropagasikan sekali.');
    }

    public function test_concurrent_publication_writes_official_results_once(): void
    {
        $tennis = $this->competition('Tenis Meja Tunggal');
        $admin = $this->user('event');
        $brackets = app(BracketService::class);
        $brackets->generate($admin, $tennis);
        foreach (Matchup::where('competition_id', $tennis->id)->orderBy('round')->orderBy('position')->pluck('id') as $id) {
            $match = Matchup::findOrFail($id);
            if ($match->status !== 'finished') {
                $brackets->recordWinner($admin, $match, $match->registration_a_id, 2, 0, null, $match->version);
            }
        }
        $publishers = ['event' => $admin->id, 'admin' => $this->user('admin')->id];
        $competitionId = $tennis->id;

        $results = $this->raceOn('competitions', $competitionId, [
            'event' => static fn () => app(ResultService::class)->publish(User::findOrFail($publishers['event']), Competition::findOrFail($competitionId)),
            'admin' => static fn () => app(ResultService::class)->publish(User::findOrFail($publishers['admin']), Competition::findOrFail($competitionId)),
        ]);

        [$winner] = $this->assertExactlyOneWinner($results, 'Hasil sudah disahkan.');
        $this->assertSame($publishers[$winner], $tennis->fresh()->results_published_by);
        $entrants = Registration::where('competition_id', $competitionId)->where('status', 'accepted')->count();
        $this->assertSame($entrants, CompetitionResult::where('competition_id', $competitionId)->count());
        $this->assertSame(1, CompetitionResult::where('competition_id', $competitionId)->where('rank', 1)->count());
        $this->assertSame(1, $this->audits('results.published'));
        $this->assertSame($entrants, DB::table('notifications')->where('data', 'like', '%Hasil resmi%')->count());
    }

    /**
     * Start every task in its own PHP process while this test holds a row lock on the given row, release the lock
     * once all workers are blocked on it, and return what each worker reported.
     *
     * @param  array<string, Closure(): mixed>  $tasks
     * @return array<string, array{successful: bool, exception?: class-string, message?: string}>
     */
    private function raceOn(string $table, int $id, array $tasks): array
    {
        config(['database.connections.gate' => config('database.connections.'.config('database.default'))]);
        $gate = DB::connection('gate');
        $gate->beginTransaction();
        $gate->table($table)->where('id', $id)->lockForUpdate()->first();

        $command = Application::formatCommandString('invoke-serialized-closure');
        $pool = Process::pool(function (Pool $pool) use ($tasks, $command) {
            foreach ($tasks as $key => $task) {
                $pool->as($key)->path(base_path())->timeout(60)
                    ->env(['LARAVEL_INVOKABLE_CLOSURE' => base64_encode(serialize(new SerializableClosure($task)))])
                    ->command($command);
            }
        })->start();

        $deadline = microtime(true) + 30;
        do {
            usleep(100_000);
            $waiting = $this->workersWaitingOn($table);
        } while ($waiting < count($tasks) && $pool->running()->isNotEmpty() && microtime(true) < $deadline);

        $gate->commit();
        DB::purge('gate');
        $finished = $pool->wait()->collect();

        $reports = $finished->map(fn ($process) => json_decode($process->output(), true))->all();
        $diagnostics = $finished->map(fn ($process) => $process->output().$process->errorOutput())->implode(PHP_EOL);
        $this->assertSame(count($tasks), $waiting, 'Setiap worker harus tertahan bersamaan pada row lock '.$table.'. '.$diagnostics);
        foreach ($reports as $report) {
            $this->assertIsArray($report, $diagnostics);
        }

        return $reports;
    }

    private function workersWaitingOn(string $table): int
    {
        return (int) DB::scalar(
            "select count(*) from pg_stat_activity where datname = current_database() and pid <> pg_backend_pid() and wait_event_type = 'Lock' and query like ?",
            ['%from "'.$table.'" where%for update'],
        );
    }

    /**
     * @param  array<string, array{successful: bool, exception?: class-string, message?: string}>  $results
     * @return array{0: string, 1: string} keys of the winning and the refused worker
     */
    private function assertExactlyOneWinner(array $results, string $refusal): array
    {
        $winners = array_keys(array_filter($results, fn (array $result) => $result['successful']));
        $this->assertCount(1, $winners, 'Tepat satu transaksi boleh berhasil: '.json_encode($results));
        $loser = array_key_first(array_filter($results, fn (array $result) => ! $result['successful']));
        $this->assertSame(ValidationException::class, $results[$loser]['exception']);
        $this->assertSame($refusal, $results[$loser]['message']);

        return [$winners[0], $loser];
    }

    private function audits(string $action): int
    {
        return DB::table('audit_logs')->where('action', $action)->count();
    }
}
