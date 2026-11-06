<?php

namespace Tests\Feature;

use App\Models\AwardResult;
use App\Models\Certificate;
use App\Models\DepartmentApproval;
use App\Models\Event;
use App\Models\Matchup;
use App\Models\Participant;
use App\Models\PaymentProof;
use App\Models\Program;
use App\Models\Registration;
use App\Models\Score;
use App\Models\User;
use App\Services\Scoring\ResultService;
use App\Services\Scoring\ScoringService;
use Database\Seeders\SimulationSeeder;
use Illuminate\Support\Facades\Storage;

/**
 * The simulation gives every competition process an example that opens on its real page.
 */
class SimulationSeederTest extends DemoTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function addStudents(int $total = 14): void
    {
        $program = Program::whereNotNull('department_id')->value('id');
        foreach (range(1, $total) as $number) {
            User::create(['name' => 'Mahasiswa Simulasi '.$number, 'nim' => 'SIMTES'.$number, 'email' => 'simtes'.$number.'@contoh.test', 'password' => 'SandiUji12345', 'active' => true, 'program_id' => $program]);
        }
    }

    private function simulate(): void
    {
        $this->addStudents();
        $this->seed(SimulationSeeder::class);
    }

    public function test_it_builds_a_single_and_a_team_bracket_from_existing_students(): void
    {
        $this->addStudents();
        $students = User::whereNotNull('nim')->count();

        $this->seed(SimulationSeeder::class);

        $this->assertSame($students, User::whereNotNull('nim')->count(), 'Simulasi memakai mahasiswa yang sudah ada.');
        $single = $this->competition(SimulationSeeder::SINGLE);
        $team = $this->competition(SimulationSeeder::TEAM);
        $this->assertSame(['individual', 'bracket'], [$single->format, $single->assessment_type]);
        $this->assertSame(['team', 'bracket'], [$team->format, $team->assessment_type]);

        $this->assertSame(8, $single->registrations()->where('status', 'accepted')->count());
        $this->assertSame(7, Matchup::where('competition_id', $single->id)->count());
        $this->assertSame(4, Matchup::where('competition_id', $single->id)->where('status', 'finished')->count());
        $this->assertSame(1, Matchup::where('competition_id', $single->id)->where('status', 'live')->count(), 'Satu semifinal sedang berlangsung.');

        $this->assertSame(['Tim Elang', 'Tim Garuda', 'Tim Merpati', 'Tim Rajawali'], $team->registrations()->orderBy('team_name')->pluck('team_name')->all());
        $this->assertSame(12, Participant::where('competition_id', $team->id)->where('status', 'confirmed')->distinct()->count('user_id'));
        $this->assertSame(3, Matchup::where('competition_id', $team->id)->count());
        $this->assertSame(1, Matchup::where('competition_id', $team->id)->where('status', 'finished')->count());
    }

    public function test_every_registration_step_has_an_example(): void
    {
        $this->simulate();
        $flow = $this->competition(SimulationSeeder::FLOW);

        $this->assertEqualsCanonicalizing(['draft', 'program_review', 'revision', 'submitted', 'accepted', 'rejected', 'cancelled'], $flow->registrations()->distinct()->pluck('status')->all());
        $this->assertEqualsCanonicalizing(['not_required', 'unpaid', 'pending', 'rejected', 'paid'], $flow->registrations()->distinct()->pluck('payment_status')->all());
        $this->assertTrue(Participant::where('competition_id', $flow->id)->where('status', 'invited')->exists(), 'Ada undangan anggota tim yang belum dijawab.');
        $this->assertEqualsCanonicalizing(['pending', 'approved', 'rejected'], DepartmentApproval::whereIn('registration_id', $flow->registrations()->select('id'))->distinct()->pluck('status')->all());
        $this->assertEqualsCanonicalizing(['pending', 'accepted', 'rejected'], PaymentProof::whereIn('registration_id', $flow->registrations()->select('id'))->distinct()->pluck('status')->all());

        $this->assertSame(['submitted', 'revision', 'rejected'], Event::where('name', 'like', 'Simulasi Pengajuan%')->orderBy('id')->pluck('status')->all());
    }

    public function test_judging_is_left_halfway_and_the_finished_competition_reaches_a_certificate(): void
    {
        $this->simulate();
        $rubric = $this->competition(SimulationSeeder::RUBRIC);
        $this->assertSame(3, $rubric->criteria()->count());
        $this->assertSame(18, Score::where('competition_id', $rubric->id)->count(), 'Juri pertama menilai empat peserta, juri kedua baru dua.');
        $this->assertNull($rubric->results_published_at);

        $finished = $this->competition(SimulationSeeder::FINISHED);
        $this->assertNotNull($finished->results_published_at);
        $this->assertSame(0, Matchup::where('competition_id', $finished->id)->where('status', '!=', 'finished')->count());
        $this->assertSame(['Juara 1', 'Juara 2'], AwardResult::where('competition_id', $finished->id)->orderBy('award')->pluck('award')->all());
        $this->assertSame(1, Certificate::where('competition_id', $finished->id)->count());
    }

    public function test_singing_is_scored_in_full_and_ready_to_publish(): void
    {
        $this->simulate();
        $singing = $this->competition(SimulationSeeder::SINGING);
        $scoring = app(ScoringService::class);

        $this->assertSame(['rubric', 'Seni'], [$singing->assessment_type, $singing->field]);
        $this->assertEqualsCanonicalizing(['Teknik vokal', 'Intonasi dan ritme', 'Penghayatan lagu', 'Penampilan panggung'], $singing->criteria()->pluck('name')->all());
        $this->assertSame(40, Score::where('competition_id', $singing->id)->count(), 'Lima peserta, dua juri, empat kriteria.');
        $this->assertSame([], $scoring->missing($singing));
        $this->assertSame([1, 2, 3, 4, 5], array_column($scoring->ranking($singing), 'rank'));
        $this->assertNull($singing->results_published_at);

        $admin = $this->user('admin');
        $this->actingAs($admin)->get('/kelola/lomba/'.$singing->id)->assertOk();
        app(ResultService::class)->publish($admin, $singing);
        $this->assertNotNull($singing->fresh()->results_published_at);
    }

    public function test_each_process_opens_on_its_own_page(): void
    {
        $this->simulate();
        $flow = $this->competition(SimulationSeeder::FLOW);
        $single = $this->competition(SimulationSeeder::SINGLE);
        $team = $this->competition(SimulationSeeder::TEAM);
        $finished = $this->competition(SimulationSeeder::FINISHED);
        $rubric = $this->competition(SimulationSeeder::RUBRIC);

        $this->actingAs($this->user('admin'));
        $this->get('/kelola/event')->assertOk()->assertSee('Simulasi Pengajuan Menunggu Keputusan');
        $this->get('/kelola/lomba/'.$flow->id)->assertOk()->assertSee('Tim Siap Ditinjau');
        $this->get('/tinjauan')->assertOk()->assertSee('Tim Siap Ditinjau');
        $this->get('/pembayaran')->assertOk()->assertSee('Tim Bukti Diperiksa');
        $this->get('/jurusan')->assertOk();
        $this->get('/kelola/lomba/'.$rubric->id)->assertOk();
        $this->get('/operator?lomba='.$single->id)->assertOk();
        $this->get('/live/'.$single->id)->assertOk();
        $this->get('/live/'.$team->id)->assertOk()->assertSee('Tim Elang');
        $this->get('/live/'.$finished->id)->assertOk();
        $this->get('/kelola/prestasi')->assertOk();
        $this->get('/kelola/sertifikat?lomba='.$finished->id)->assertOk()->assertSee('SIMULASI/'.$finished->id.'/001');
    }

    public function test_seeding_again_changes_nothing(): void
    {
        $this->simulate();
        $counts = fn () => [Event::where('name', 'like', 'Simulasi%')->count(), Registration::count(), Matchup::count(), Score::count(), AwardResult::count(), Certificate::count()];
        $before = $counts();

        $this->seed(SimulationSeeder::class);

        $this->assertSame($before, $counts());
    }

    public function test_it_stops_with_a_clear_message_when_students_are_too_few(): void
    {
        User::whereNotNull('nim')->update(['active' => false]);

        $this->expectExceptionMessage('Butuh minimal 20 mahasiswa aktif');
        $this->seed(SimulationSeeder::class);
    }

    public function test_it_refuses_to_run_on_production(): void
    {
        $this->addStudents();
        $this->app->detectEnvironment(fn () => 'production');

        try {
            $this->app->make(SimulationSeeder::class)->run();
            $this->fail('Seeder seharusnya menolak lingkungan produksi.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('hanya untuk lingkungan local/testing', $exception->getMessage());
        } finally {
            $this->app->detectEnvironment(fn () => 'testing');
        }
        $this->assertFalse(Event::where('name', SimulationSeeder::EVENT)->exists());
    }
}
