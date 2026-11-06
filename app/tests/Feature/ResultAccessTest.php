<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Club;
use App\Models\Competition;
use App\Models\Department;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\User;
use App\Notifications\BrudelNotice;
use App\Services\Match\BracketService;
use App\Services\Scoring\ResultService;
use Illuminate\Support\Facades\Notification;

/**
 * Who may open a competition's live score and results: everyone, signed-in users, or admin jurusan and above.
 */
class ResultAccessTest extends DemoTestCase
{
    private function table(): Competition
    {
        return $this->competition('Tenis Meja Tunggal');
    }

    private function lock(Competition $competition): void
    {
        $this->actingAs($this->user('admin'))->post('/lomba/'.$competition->id.'/akses-hasil', ['access' => 'locked'])->assertSessionHasNoErrors();
        $this->post('/logout');
    }

    private function joinAs(string $key, Competition $competition): void
    {
        $user = $this->user($key);
        $registration = Registration::firstOrCreate(['competition_id' => $competition->id, 'user_id' => $user->id], ['motivation' => 'Pendaftaran uji akses hasil lomba.', 'status' => 'accepted', 'submitted_at' => now()]);
        Participant::firstOrCreate(['registration_id' => $registration->id, 'competition_id' => $competition->id, 'user_id' => $user->id], ['status' => 'confirmed']);
    }

    public function test_public_and_signed_in_results_work_as_before(): void
    {
        $competition = $this->table();
        $competition->update(['live_visibility' => 'public']);
        $this->get('/live/'.$competition->id)->assertOk();

        $competition->update(['live_visibility' => 'auth']);
        $this->get('/live/'.$competition->id)->assertRedirect('/login');
        $this->actingAs($this->user('mahasiswa'))->get('/live/'.$competition->id)->assertOk();
    }

    public function test_locked_results_stay_with_admin_jurusan_and_above(): void
    {
        $competition = $this->table();
        $competition->update(['live_visibility' => 'public']);
        $this->joinAs('mahasiswa', $competition);
        $this->lock($competition);

        $this->get('/live/'.$competition->id)->assertRedirect('/login');
        $this->get('/live')->assertDontSee('Tenis Meja Tunggal');
        foreach (['mahasiswa', 'anggota', 'pembina'] as $role) {
            $this->actingAs($this->user($role))->get('/live/'.$competition->id)->assertForbidden();
            $this->get('/live')->assertDontSee('Tenis Meja Tunggal');
            $this->get('/lomba/'.$competition->id)->assertOk()->assertSee('dikunci untuk admin jurusan ke atas');
        }
        foreach (['admin', 'jurusan', 'pimpinan', 'event'] as $role) {
            $this->actingAs($this->user($role))->get('/live/'.$competition->id)->assertOk();
            $this->get('/live')->assertSee('Tenis Meja Tunggal')->assertSee('Terkunci');
        }
    }

    public function test_the_lock_survives_an_edit_by_the_competition_manager_and_only_kemahasiswaan_lifts_it(): void
    {
        $competition = $this->table();
        $this->lock($competition);

        $this->actingAs($this->user('event'))->post('/lomba/'.$competition->id.'/akses-hasil', ['access' => 'public'])->assertForbidden();
        $this->assertTrue($competition->fresh()->resultsLocked());

        $competition->update(['live_visibility' => 'public']);
        $this->post('/logout');
        $this->get('/live/'.$competition->id)->assertRedirect('/login');

        $this->actingAs($this->user('admin'))->post('/lomba/'.$competition->id.'/akses-hasil', ['access' => 'public'])->assertSessionHasNoErrors();
        $this->assertFalse($competition->fresh()->resultsLocked());
        $this->assertDatabaseHas('audit_logs', ['action' => 'results.access_changed', 'subject_id' => $competition->id]);
        $this->post('/logout');
        $this->get('/live/'.$competition->id)->assertOk();
    }

    public function test_admin_jurusan_sets_access_only_for_competitions_it_is_part_of(): void
    {
        $competition = $this->table();
        Participant::where('competition_id', $competition->id)->delete();
        $this->actingAs($this->user('jurusan'))->get('/lomba/'.$competition->id)->assertOk()->assertDontSee('Akses hasil lomba');
        $this->post('/lomba/'.$competition->id.'/akses-hasil', ['access' => 'locked'])->assertForbidden();

        $this->joinAs('mahasiswa', $competition);
        $this->get('/lomba/'.$competition->id)->assertOk()->assertSee('Akses hasil lomba');
        $this->post('/lomba/'.$competition->id.'/akses-hasil', ['access' => 'locked'])->assertSessionHasNoErrors();
        $this->assertSame($this->user('jurusan')->id, $competition->fresh()->results_locked_by);
        $this->assertSame(Department::where('code', 'KEP')->value('id'), $this->user('mahasiswa')->departmentId());
    }

    public function test_an_admin_jurusan_who_coaches_the_organizer_can_set_access(): void
    {
        $competition = $this->table();
        $event = Event::findOrFail($competition->event_id);
        $club = Club::where('name', 'UKM Olahraga')->firstOrFail();
        $event->update(['club_id' => $club->id]);
        $admin = $this->user('jurusan');
        Assignment::create(['user_id' => $admin->id, 'role' => 'pembina', 'club_id' => $club->id, 'starts_at' => now()->subDay(), 'ends_at' => now()->addYear()]);

        $this->actingAs(User::find($admin->id))->post('/lomba/'.$competition->id.'/akses-hasil', ['access' => 'locked'])->assertSessionHasNoErrors();
        $this->assertTrue($competition->fresh()->resultsLocked());
    }

    public function test_students_and_unknown_choices_are_refused(): void
    {
        $competition = $this->table();
        $this->actingAs($this->user('mahasiswa'))->post('/lomba/'.$competition->id.'/akses-hasil', ['access' => 'locked'])->assertForbidden();
        $this->actingAs($this->user('admin'))->post('/lomba/'.$competition->id.'/akses-hasil', ['access' => 'rahasia'])->assertSessionHasErrors('access');
        $this->assertFalse($competition->fresh()->resultsLocked());
    }

    public function test_publishing_locked_results_does_not_tell_participants(): void
    {
        $competition = $this->table();
        $admin = $this->user('admin');
        $this->lock($competition);
        app(BracketService::class)->generate($admin, $competition);
        foreach ([1, 2, 3] as $round) {
            foreach ($competition->matchups()->where('round', $round)->where('status', 'scheduled')->get() as $match) {
                app(BracketService::class)->recordWinner($admin, $match, $match->registration_a_id, 3, 1, null, $match->version);
            }
        }
        Notification::fake();

        app(ResultService::class)->publish($admin, $competition->fresh());

        Notification::assertNothingSentTo($this->user('atlet1'), BrudelNotice::class);
    }
}
