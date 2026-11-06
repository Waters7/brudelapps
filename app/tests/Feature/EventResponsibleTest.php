<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Competition;
use App\Models\Event;
use App\Models\User;
use App\Services\Campus\MasterData;
use Illuminate\Testing\TestResponse;

/**
 * Every event answers to a Ketua, an admin jurusan or admin kemahasiswaan, who appoints the people that run it.
 */
class EventResponsibleTest extends DemoTestCase
{
    private const PROPOSAL = 'Badminton Open Internal 2026 · Demo';

    private function proposal(): Event
    {
        return Event::where('name', self::PROPOSAL)->firstOrFail();
    }

    private function approved(): Event
    {
        $event = $this->proposal();
        $this->actingAs($this->user('admin'))->post('/kelola/event/'.$event->id.'/keputusan', ['decision' => 'approved'])->assertSessionHasNoErrors();

        return $event->fresh();
    }

    private function doubles(): Competition
    {
        return $this->competition('Ganda Campuran');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function appoint(Event $event, array $data): TestResponse
    {
        return $this->post('/kelola/event/'.$event->id.'/tim', $data + ['starts_at' => now()->subHour()->format('Y-m-d H:i'), 'ends_at' => now()->addMonth()->format('Y-m-d H:i')]);
    }

    private function stepDown(User $chair, Event $event): User
    {
        Assignment::where('user_id', $chair->id)->where('role', 'pengurus')->where('club_id', $event->club_id)->update(['ends_at' => now()->subMinute()]);

        return User::findOrFail($chair->id);
    }

    public function test_the_creator_answers_for_a_new_event(): void
    {
        $event = $this->proposal();
        $chair = $this->user('pengurus');
        $this->actingAs($chair)->post('/kelola/event', ['name' => 'Kejuaraan Baru', 'organizer' => 'UKM Olahraga', 'pathway' => 'internal', 'club_id' => $event->club_id])->assertSessionHasNoErrors();
        $this->assertSame($chair->id, Event::where('name', 'Kejuaraan Baru')->value('responsible_id'));

        $admin = $this->user('admin');
        $this->actingAs($admin)->post('/kelola/event', ['name' => 'Kegiatan Kemahasiswaan', 'organizer' => 'Kemahasiswaan', 'pathway' => 'internal'])->assertSessionHasNoErrors();
        $this->assertSame($admin->id, Event::where('name', 'Kegiatan Kemahasiswaan')->value('responsible_id'));
    }

    public function test_an_admin_jurusan_proposes_an_event_and_answers_for_it(): void
    {
        $admin = $this->user('jurusan');
        $this->actingAs($admin)->get('/kelola/event')->assertOk()->assertSee('Jurusan (tanpa organisasi)');
        $this->post('/kelola/event', ['name' => 'Lomba Jurusan', 'organizer' => 'Jurusan Keperawatan', 'pathway' => 'internal'])->assertSessionHasNoErrors();
        $event = Event::where('name', 'Lomba Jurusan')->firstOrFail();
        $this->assertSame(['draft', $admin->id], [$event->status, $event->responsible_id]);

        $copy = $this->doubles()->replicate();
        $copy->event_id = $event->id;
        $copy->save();
        $this->post('/kelola/event/'.$event->id.'/ajukan')->assertSessionHasNoErrors();
        $this->assertSame('submitted', $event->fresh()->status);

        $this->actingAs($this->user('admin'))->post('/kelola/event/'.$event->id.'/keputusan', ['decision' => 'approved'])->assertSessionHasNoErrors();
        $this->assertTrue($admin->notifications()->get()->contains(fn ($n) => str_contains($n->data['title'], 'Lomba Jurusan')));
        $this->actingAs($admin)->get('/kelola/event/'.$event->id)->assertOk()->assertSee('Tunjuk anggota tim');
    }

    public function test_an_admin_jurusan_sees_events_of_the_organizations_it_runs(): void
    {
        $event = $this->proposal();
        $admin = $this->user('jurusan');
        $this->actingAs($admin)->get('/kelola/event')->assertOk()->assertDontSee(self::PROPOSAL);

        Assignment::create(['user_id' => $admin->id, 'role' => 'pembina', 'club_id' => $event->club_id, 'starts_at' => now()->subDay(), 'ends_at' => now()->addYear()]);
        $this->actingAs(User::findOrFail($admin->id))->get('/kelola/event')->assertOk()->assertSee(self::PROPOSAL);
        $this->get('/kelola/event/'.$event->id)->assertOk();
    }

    public function test_only_those_with_a_post_may_create_an_event(): void
    {
        $club = $this->proposal()->club_id;
        $this->actingAs($this->user('jurusan'))->post('/kelola/event', ['name' => 'Bukan Organisasinya', 'organizer' => 'UKM', 'pathway' => 'internal', 'club_id' => $club])->assertForbidden();
        $this->actingAs($this->user('mahasiswa'))->post('/kelola/event', ['name' => 'Tanpa Jabatan', 'organizer' => 'Mahasiswa', 'pathway' => 'internal'])->assertForbidden();
        $this->assertDatabaseMissing('events', ['name' => 'Tanpa Jabatan']);
    }

    public function test_the_responsible_ketua_appoints_who_runs_the_competition_without_running_it(): void
    {
        $event = $this->approved();
        $doubles = $this->doubles();
        $chair = $this->user('pengurus');
        $this->actingAs($chair)->get('/kelola/event/'.$event->id)->assertOk()->assertSee('Tunjuk anggota tim');

        $this->appoint($event, ['user' => 'mahasiswa@demo.brudel.test', 'role' => 'operator', 'competition_id' => $doubles->id])->assertSessionHasNoErrors();
        $this->appoint($event, ['user' => 'anggota@demo.brudel.test', 'role' => 'pengelola_lomba', 'competition_id' => $doubles->id])->assertSessionHasNoErrors();
        $this->appoint($event, ['user' => 'atlet1@demo.brudel.test', 'role' => 'admin_event'])->assertSessionHasNoErrors();
        $this->appoint($event, ['user' => 'juri@demo.brudel.test', 'role' => 'penilai', 'competition_id' => $doubles->id])->assertSessionHasNoErrors();

        $this->assertTrue($this->user('mahasiswa')->operates($doubles));
        $this->assertTrue($this->user('anggota')->managesCompetition($doubles));
        $this->assertTrue($this->user('atlet1')->managesEvent($event->id));
        $this->assertTrue($this->user('juri')->judges($doubles));
        $this->assertFalse($this->user('pengurus')->managesCompetition($doubles), 'Penanggung jawab tidak otomatis mengelola lomba.');

        $this->get('/kelola/event/'.$event->id)->assertSee($this->user('anggota')->name)->assertSee('Pengelola lomba');
    }

    public function test_an_appointment_needs_an_approved_event_and_a_competition_of_that_event(): void
    {
        $event = $this->proposal();
        $this->actingAs($this->user('pengurus'));
        $this->appoint($event, ['user' => 'mahasiswa@demo.brudel.test', 'role' => 'operator', 'competition_id' => $this->doubles()->id])->assertForbidden();

        $event = $this->approved();
        $this->actingAs($this->user('pengurus'));
        $this->appoint($event, ['user' => 'mahasiswa@demo.brudel.test', 'role' => 'operator', 'competition_id' => $this->competition('Tenis Meja Tunggal')->id])->assertSessionHasErrors('competition_id');
        $this->appoint($event, ['user' => 'mahasiswa@demo.brudel.test', 'role' => 'operator'])->assertSessionHasErrors('competition_id');
        $this->appoint($event, ['user' => 'mahasiswa@demo.brudel.test', 'role' => 'pimpinan', 'competition_id' => $this->doubles()->id])->assertSessionHasErrors('role');
    }

    public function test_nobody_else_appoints_and_the_responsible_person_cannot_appoint_themselves(): void
    {
        $event = $this->approved();
        $doubles = $this->doubles();
        foreach (['mahasiswa', 'event', 'jurusan'] as $key) {
            $this->actingAs($this->user($key));
            $this->appoint($event, ['user' => 'anggota@demo.brudel.test', 'role' => 'operator', 'competition_id' => $doubles->id])->assertForbidden();
        }
        $this->actingAs($this->user('pengurus'));
        $this->appoint($event, ['user' => 'pengurus@demo.brudel.test', 'role' => 'pengelola_lomba', 'competition_id' => $doubles->id])->assertForbidden();
    }

    public function test_a_competition_manager_appoints_operators_but_not_other_managers(): void
    {
        $event = $this->approved();
        $doubles = $this->doubles();
        $this->actingAs($this->user('pengurus'));
        $this->appoint($event, ['user' => 'anggota@demo.brudel.test', 'role' => 'pengelola_lomba', 'competition_id' => $doubles->id])->assertSessionHasNoErrors();

        $window = ['starts_at' => now()->format('Y-m-d H:i'), 'ends_at' => now()->addMonth()->format('Y-m-d H:i')];
        $this->actingAs($this->user('anggota'))->post('/kelola/penugasan', $window + ['user' => 'mahasiswa@demo.brudel.test', 'role' => 'operator', 'scope_id' => $doubles->id])->assertSessionHasNoErrors();
        $this->post('/kelola/penugasan', $window + ['user' => 'mahasiswa@demo.brudel.test', 'role' => 'pengelola_lomba', 'scope_id' => $doubles->id])->assertForbidden();
    }

    public function test_the_responsible_person_ends_an_appointment_of_any_team_role(): void
    {
        $event = $this->approved();
        $doubles = $this->doubles();
        $this->actingAs($this->user('pengurus'));
        $this->appoint($event, ['user' => 'mahasiswa@demo.brudel.test', 'role' => 'operator', 'competition_id' => $doubles->id])->assertSessionHasNoErrors();
        $this->appoint($event, ['user' => 'anggota@demo.brudel.test', 'role' => 'admin_event'])->assertSessionHasNoErrors();

        foreach ([['operator', 'mahasiswa'], ['admin_event', 'anggota']] as [$role, $key]) {
            $assignment = Assignment::where('role', $role)->where('user_id', $this->user($key)->id)->firstOrFail();
            $this->post('/kelola/penugasan/'.$assignment->id.'/akhiri', ['reason' => 'Berhalangan hadir.'])->assertSessionHasNoErrors();
            $this->assertFalse($assignment->fresh()->ends_at->isFuture());
        }
        $this->assertFalse($this->user('mahasiswa')->operates($doubles));
        $this->assertFalse($this->user('anggota')->managesEvent($event->id));
    }

    public function test_the_post_is_handed_over_only_to_someone_eligible(): void
    {
        $event = $this->approved();
        $chair = $this->user('pengurus');
        $admin = $this->user('jurusan');
        $this->actingAs($chair);
        $this->post('/kelola/event/'.$event->id.'/penanggung-jawab', ['user_id' => $this->user('mahasiswa')->id])->assertSessionHasErrors('action');
        $this->assertSame($chair->id, $event->fresh()->responsible_id);

        $this->post('/kelola/event/'.$event->id.'/penanggung-jawab', ['user_id' => $admin->id])->assertSessionHasNoErrors();
        $this->assertSame($admin->id, $event->fresh()->responsible_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'event.responsible_changed', 'subject_id' => $event->id]);
        $this->assertTrue($admin->notifications()->get()->contains(fn ($n) => $n->data['title'] === 'Penanggung jawab event'));

        $this->appoint($event, ['user' => 'mahasiswa@demo.brudel.test', 'role' => 'operator', 'competition_id' => $this->doubles()->id])->assertForbidden();
        $this->get('/kelola/lomba/'.$this->doubles()->id.'/akun')->assertForbidden();
        $this->actingAs($admin)->get('/kelola/lomba/'.$this->doubles()->id.'/akun')->assertOk();
    }

    public function test_only_kemahasiswaan_or_the_responsible_person_hands_the_post_over(): void
    {
        $event = $this->approved();
        foreach (['jurusan', 'event', 'mahasiswa'] as $key) {
            $this->actingAs($this->user($key))->post('/kelola/event/'.$event->id.'/penanggung-jawab', ['user_id' => $this->user('jurusan')->id])->assertForbidden();
        }
        $this->actingAs($this->user('admin'))->post('/kelola/event/'.$event->id.'/penanggung-jawab', ['user_id' => $this->user('jurusan')->id])->assertSessionHasNoErrors();
    }

    public function test_a_ketua_who_stepped_down_loses_the_post_and_the_new_ketua_takes_it_on(): void
    {
        $event = $this->approved();
        $former = $this->stepDown($this->user('pengurus'), $event);
        $this->assertFalse($former->answersForEvent($event));
        $this->actingAs($former);
        $this->appoint($event, ['user' => 'mahasiswa@demo.brudel.test', 'role' => 'operator', 'competition_id' => $this->doubles()->id])->assertForbidden();

        $successor = $this->user('anggota');
        Assignment::create(['user_id' => $successor->id, 'role' => 'pengurus', 'club_id' => $event->club_id, 'position' => app(MasterData::class)->chairPositions()[0], 'starts_at' => now()->subDay(), 'ends_at' => now()->addYear()]);
        $this->actingAs(User::findOrFail($successor->id))->get('/kelola/event/'.$event->id)->assertOk()->assertSee('tidak lagi memegang jabatan')->assertSee('Ganti penanggung jawab');
        $this->post('/kelola/event/'.$event->id.'/penanggung-jawab', ['user_id' => $successor->id])->assertSessionHasNoErrors();
        $this->appoint($event, ['user' => 'mahasiswa@demo.brudel.test', 'role' => 'operator', 'competition_id' => $this->doubles()->id])->assertSessionHasNoErrors();
    }

    public function test_an_event_without_an_eligible_responsible_person_is_not_approved(): void
    {
        $event = $this->proposal();
        $this->stepDown($this->user('pengurus'), $event);
        $this->actingAs($this->user('admin'))->post('/kelola/event/'.$event->id.'/keputusan', ['decision' => 'approved'])->assertSessionHasErrors('action');
        $this->assertSame('submitted', $event->fresh()->status);

        $this->post('/kelola/event/'.$event->id.'/penanggung-jawab', ['user_id' => $this->user('jurusan')->id])->assertSessionHasNoErrors();
        $this->post('/kelola/event/'.$event->id.'/keputusan', ['decision' => 'approved'])->assertSessionHasNoErrors();
        $this->assertSame('approved', $event->fresh()->status);
    }

    public function test_the_team_list_leaves_out_generated_logins(): void
    {
        $event = $this->approved();
        $account = $this->doubles()->accounts()->where('role', 'operator')->firstOrFail();

        $this->actingAs($this->user('pengurus'))->get('/kelola/event/'.$event->id)->assertOk()->assertDontSee($account->user->name)->assertSee('Belum ada yang ditunjuk');
    }

    public function test_an_admin_jurusan_who_answers_for_the_event_can_lock_its_results(): void
    {
        $competition = $this->competition('Tenis Meja Tunggal');
        $admin = $this->user('jurusan');
        Event::whereKey($competition->event_id)->update(['responsible_id' => $admin->id]);

        $this->actingAs($admin)->post('/lomba/'.$competition->id.'/akses-hasil', ['access' => 'locked'])->assertSessionHasNoErrors();
        $this->assertTrue($competition->fresh()->resultsLocked());
    }
}
