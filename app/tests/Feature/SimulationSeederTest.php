<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Matchup;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\User;
use Database\Seeders\SimulationSeeder;

class SimulationSeederTest extends DemoTestCase
{
    private function addStudents(int $total): void
    {
        foreach (range(1, $total) as $number) {
            User::create(['name' => 'Mahasiswa Simulasi '.$number, 'nim' => 'SIMTES'.$number, 'email' => 'simtes'.$number.'@contoh.test', 'password' => 'SandiUji12345', 'active' => true]);
        }
    }

    public function test_it_builds_a_single_and_a_team_bracket_from_existing_students(): void
    {
        $this->addStudents(6);
        $accounts = User::count();

        $this->seed(SimulationSeeder::class);

        $this->assertSame($accounts, User::count(), 'Simulasi memakai mahasiswa yang sudah ada.');
        $single = $this->competition(SimulationSeeder::SINGLE);
        $team = $this->competition(SimulationSeeder::TEAM);
        $this->assertSame(['individual', 'bracket'], [$single->format, $single->assessment_type]);
        $this->assertSame(['team', 'bracket'], [$team->format, $team->assessment_type]);

        $this->assertSame(8, $single->registrations()->where('status', 'accepted')->count());
        $this->assertSame(7, Matchup::where('competition_id', $single->id)->count());
        $this->assertSame(4, Matchup::where('competition_id', $single->id)->where('status', 'finished')->count());
        $this->assertSame(2, Matchup::where('competition_id', $single->id)->where('round', 2)->where('status', 'scheduled')->count(), 'Pemenang babak pertama maju ke semifinal.');

        $this->assertSame(['Tim Elang', 'Tim Garuda', 'Tim Merpati', 'Tim Rajawali'], $team->registrations()->orderBy('team_name')->pluck('team_name')->all());
        $this->assertSame(12, Participant::where('competition_id', $team->id)->where('status', 'confirmed')->distinct()->count('user_id'));
        $this->assertSame(3, Matchup::where('competition_id', $team->id)->count());
        $this->assertSame(1, Matchup::where('competition_id', $team->id)->where('status', 'finished')->count());
    }

    public function test_the_brackets_open_for_the_operator_and_on_live_score(): void
    {
        $this->addStudents(6);
        $this->seed(SimulationSeeder::class);
        $team = $this->competition(SimulationSeeder::TEAM);

        $this->actingAs($this->user('admin'))->get('/kelola/lomba/'.$team->id)->assertOk()->assertSee('Tim Elang');
        $this->get('/live/'.$team->id)->assertOk()->assertSee('Tim Elang');
        $this->get('/live/'.$this->competition(SimulationSeeder::SINGLE)->id)->assertOk();
    }

    public function test_seeding_again_changes_nothing(): void
    {
        $this->addStudents(6);
        $this->seed(SimulationSeeder::class);
        $counts = [Event::where('name', SimulationSeeder::EVENT)->count(), Registration::count(), Matchup::count()];

        $this->seed(SimulationSeeder::class);

        $this->assertSame($counts, [Event::where('name', SimulationSeeder::EVENT)->count(), Registration::count(), Matchup::count()]);
    }

    public function test_it_stops_with_a_clear_message_when_students_are_too_few(): void
    {
        User::whereNotNull('nim')->update(['active' => false]);

        $this->expectExceptionMessage('Butuh minimal 12 mahasiswa aktif');
        $this->seed(SimulationSeeder::class);
    }

    public function test_it_refuses_to_run_on_production(): void
    {
        $this->addStudents(6);
        $this->app->detectEnvironment(fn () => 'production');

        try {
            $this->app->call([new SimulationSeeder, 'run']);
            $this->fail('Seeder seharusnya menolak lingkungan produksi.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('hanya untuk lingkungan local/testing', $exception->getMessage());
        } finally {
            $this->app->detectEnvironment(fn () => 'testing');
        }
        $this->assertFalse(Event::where('name', SimulationSeeder::EVENT)->exists());
    }
}
