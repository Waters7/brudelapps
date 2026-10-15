<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Competition;
use App\Models\CompetitionAccount;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\ScoreLog;
use App\Models\User;
use App\Services\Event\EventService;
use App\Services\Wita;

class CompetitionAccountTest extends DemoTestCase
{
    /**
     * @return array<string, mixed>
     */
    private function form(Competition $competition, array $changes = []): array
    {
        return array_merge([
            'name' => $competition->name, 'field' => $competition->field, 'level' => 1, 'description' => $competition->description, 'requirements' => $competition->requirements,
            'format' => $competition->format, 'min_members' => $competition->min_members, 'max_members' => $competition->max_members, 'quota' => $competition->quota,
            'fee' => $competition->fee, 'fee_basis' => $competition->fee_basis, 'opens_at' => Wita::input($competition->opens_at), 'closes_at' => Wita::input($competition->closes_at),
            'starts_at' => Wita::input($competition->starts_at), 'location' => $competition->location, 'assessment_type' => $competition->assessment_type,
            'judge_count' => $competition->judge_count ?: 1, 'requires_program_approval' => $competition->requires_program_approval,
        ], $changes);
    }

    public function test_every_competition_of_an_approved_event_gets_generated_logins(): void
    {
        $tennis = $this->competition('Tenis Meja Tunggal');
        $debate = $this->competition('Debat Bahasa Inggris');
        $this->assertSame(['operator', 'pengelola_lomba'], $tennis->accounts()->pluck('role')->sort()->values()->all());
        $this->assertSame(4, $debate->accounts()->count());
        $account = $debate->accounts()->where('role', 'penilai')->first();
        $this->assertTrue($account->user->judges($debate));
        $this->assertStringEndsWith('@'.config('brudel.competition_account_domain'), $account->user->email);
        $this->post('/login', ['email' => $account->user->email, 'password' => $account->secret])->assertRedirect('/');
        $this->assertFalse(Competition::where('name', 'Ganda Campuran')->first()->accounts()->exists(), 'Belum ada akun sebelum event disetujui.');
    }

    public function test_only_the_proposer_and_superadmin_can_see_and_reset_competition_logins(): void
    {
        $proposal = Event::where('name', 'Badminton Open Internal 2026 · Demo')->first();
        $doubles = Competition::where('name', 'Ganda Campuran')->first();
        $this->actingAs($this->user('admin'))->post('/kelola/event/'.$proposal->id.'/keputusan', ['decision' => 'approved'])->assertSessionHasNoErrors();
        $this->assertSame(2, $doubles->accounts()->count());

        $chair = $this->user('pengurus');
        $this->assertTrue($chair->notifications()->get()->contains(fn ($n) => $n->data['title'] === 'Akun lomba dibuat'));
        $operator = $doubles->accounts()->where('role', 'operator')->first();
        $this->actingAs($chair)->get('/kelola/lomba/'.$doubles->id.'/akun')->assertOk()->assertSee($operator->user->email)->assertSee($operator->secret);
        $this->assertDatabaseHas('audit_logs', ['action' => 'competition_accounts.viewed', 'user_id' => $chair->id]);
        $this->get('/kelola/event/'.$proposal->id)->assertSee('/kelola/lomba/'.$doubles->id.'/akun', false);
        $this->assertFalse($chair->managesCompetition($doubles), 'Pengaju tidak otomatis menjadi pengelola lomba.');

        $this->actingAs($this->user('event'))->get('/kelola/lomba/'.$doubles->id.'/akun')->assertForbidden();
        $this->actingAs($this->user('mahasiswa'))->get('/kelola/lomba/'.$doubles->id.'/akun')->assertForbidden();
        $this->actingAs($this->user('event'))->post('/kelola/lomba/'.$doubles->id.'/akun/'.$operator->id.'/reset')->assertForbidden();

        $old = $operator->secret;
        $this->actingAs($chair)->post('/kelola/lomba/'.$doubles->id.'/akun/'.$operator->id.'/reset')->assertSessionHasNoErrors();
        $this->assertNotSame($old, $operator->fresh()->secret);
        $this->assertDatabaseHas('audit_logs', ['action' => 'competition_accounts.rotated', 'subject_id' => $operator->id]);
        $this->post('/logout');
        $this->post('/login', ['email' => $operator->user->email, 'password' => $old])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $operator->user->email, 'password' => $operator->fresh()->secret])->assertRedirect('/');
    }

    public function test_generated_pengelola_manages_only_its_own_competition(): void
    {
        $tennis = $this->competition('Tenis Meja Tunggal');
        $poster = $this->competition('Lomba Poster Kesehatan');
        $manager = $tennis->accounts()->where('role', 'pengelola_lomba')->first()->user;
        $this->assertTrue($manager->managesCompetition($tennis));
        $this->assertFalse($manager->managesCompetition($poster));
        $this->actingAs($manager)->get('/kelola/lomba/'.$tennis->id)->assertOk();
        $this->get('/kelola/lomba/'.$poster->id)->assertForbidden();
        $this->get('/kelola/event')->assertOk()->assertSee('Lomba yang Anda kelola')->assertSee('Tenis Meja Tunggal');
        $this->get('/pembayaran')->assertOk();
        $this->get('/kelola/mahasiswa')->assertForbidden();
        $this->post('/kelola/lomba/'.$tennis->id.'/bagan')->assertSessionHasNoErrors();
        $this->assertTrue($tennis->matchups()->exists());
        $this->post('/kelola/penugasan', ['user' => 'DEMO002', 'role' => 'operator', 'scope_id' => $poster->id, 'starts_at' => now()->format('Y-m-d\TH:i'), 'ends_at' => now()->addMonth()->format('Y-m-d\TH:i')])->assertForbidden();
    }

    public function test_judges_are_limited_to_five_per_competition(): void
    {
        $debate = $this->competition('Debat Bahasa Inggris');
        $events = app(EventService::class);
        $admin = $this->user('admin');
        $this->actingAs($admin)->post('/kelola/event/'.$debate->event_id.'/lomba/'.$debate->id, $this->form($debate, ['judge_count' => 6]))->assertSessionHasErrors('judge_count');
        $events->saveCompetition($admin, $debate->event, $debate, $this->form($debate, ['judge_count' => 5]));
        $this->assertSame(5, $debate->accounts()->where('role', 'penilai')->count());

        $window = ['starts_at' => now()->format('Y-m-d\TH:i'), 'ends_at' => now()->addMonth()->format('Y-m-d\TH:i')];
        $this->post('/kelola/penugasan', ['user' => 'juri@demo.brudel.test', 'role' => 'penilai', 'scope_id' => $debate->id] + $window)->assertSessionHasErrors('action');

        $events->saveCompetition($admin, $debate->event, $debate, $this->form($debate, ['judge_count' => 2]));
        $retired = $debate->accounts()->where('role', 'penilai')->where('slot', 5)->first();
        $this->assertFalse($retired->user->fresh()->active);
        $this->assertFalse($retired->user->fresh()->judges($debate));
        $this->post('/kelola/penugasan', ['user' => 'juri@demo.brudel.test', 'role' => 'penilai', 'scope_id' => $debate->id] + $window)->assertSessionHasNoErrors();
        $this->expectExceptionMessage('maksimal 5');
        $events->saveCompetition($admin, $debate->event, $debate, $this->form($debate, ['judge_count' => 5]));
    }

    public function test_every_score_change_is_logged_for_accountability(): void
    {
        $poster = $this->competition('Lomba Poster Kesehatan');
        $student = $this->user('mahasiswa');
        $entry = Registration::create(['competition_id' => $poster->id, 'user_id' => $student->id, 'motivation' => 'Poster cuci tangan.', 'status' => 'accepted', 'submitted_at' => now()]);
        Participant::create(['registration_id' => $entry->id, 'competition_id' => $poster->id, 'user_id' => $student->id, 'status' => 'confirmed']);
        $criteria = $poster->criteria()->get();
        $scores = fn (array $values) => $criteria->values()->mapWithKeys(fn ($c, $i) => [$c->id => ['score' => $values[$i], 'note' => $i === 0 ? 'Pesan jelas' : null]])->all();

        $this->actingAs($this->user('juri'))->post('/penilaian/'.$poster->id.'/'.$entry->id, ['scores' => $scores([80, 70, 60])])->assertSessionHasNoErrors();
        $this->assertSame(3, ScoreLog::count());
        $this->post('/penilaian/'.$poster->id.'/'.$entry->id, ['scores' => $scores([80, 70, 60])])->assertSessionHasNoErrors();
        $this->assertSame(3, ScoreLog::count(), 'Nilai yang tidak berubah tidak menambah log.');
        $this->post('/penilaian/'.$poster->id.'/'.$entry->id, ['scores' => $scores([85, 70, 60])])->assertSessionHasNoErrors();
        $change = ScoreLog::latest('id')->first();
        $this->assertSame([80.0, 85.0], [$change->old_score, $change->new_score]);

        $this->actingAs($this->user('event'))->get('/kelola/lomba/'.$poster->id)->assertOk()->assertSee('log penilaian')->assertSee('Juri Poster Kesehatan');
        $csv = $this->get('/kelola/lomba/'.$poster->id.'/log-nilai')->assertOk()->streamedContent();
        $this->assertStringContainsString('Juri Poster Kesehatan', $csv);
        $this->assertStringContainsString('80,00', $csv);
        $this->actingAs($this->user('juri'))->get('/kelola/lomba/'.$poster->id.'/log-nilai')->assertForbidden();
        $this->assertFalse(User::where('email', 'like', 'lomba%')->where('active', true)->get()->contains(fn ($u) => $u->isStudent()));
        $this->assertSame(0, Assignment::where('role', 'penilai')->where('competition_id', $poster->id)->whereIn('user_id', CompetitionAccount::pluck('user_id'))->count());
    }
}
