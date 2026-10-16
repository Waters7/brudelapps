<?php

namespace Tests\Feature;

use App\Livewire\Discovery;
use App\Models\Assignment;
use App\Models\Club;
use App\Models\ClubApplication;
use App\Models\Competition;
use App\Models\Event;
use App\Models\Membership;
use App\Models\Participant;
use App\Models\Recruitment;
use App\Models\Registration;
use App\Models\User;
use App\Services\Registration\EnrollmentService;
use Database\Seeders\DemoSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class EnrollmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $student;

    protected User $member;

    protected User $manager;

    protected User $admin;

    protected EnrollmentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config(['brudel.demo' => true]);
        $this->seed(DemoSeeder::class);
        $this->student = User::where('nim', 'DEMO001')->first();
        $this->member = User::where('nim', 'DEMO002')->first();
        $this->manager = User::where('nim', 'DEMO003')->first();
        $this->admin = User::where('email', 'admin@demo.brudel.test')->first();
        $this->service = app(EnrollmentService::class);
    }

    private function draft(?User $user = null, bool $team = false): Registration
    {
        $c = Competition::where('name', $team ? 'Karya Tulis Ilmiah' : 'Badminton Tunggal')->first();

        return $this->service->draft($user ?? $this->student, $c->id, ['motivation' => 'Saya siap berlatih dan mengikuti kompetisi kampus.', 'team_name' => 'Tim Sehat']);
    }

    public function test_all_student_pages_render_and_private_pages_are_not_cacheable(): void
    {
        foreach (['/', '/ukm', '/lomba', '/pendaftaran', '/ukm-saya', '/rekomendasi', '/profil', '/ukm/'.Club::orderBy('id')->value('id'), '/lomba/'.Competition::orderBy('id')->value('id')] as $url) {
            $r = $this->actingAs($this->student)->get($url);
            $r->assertOk();
            $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
        }
        $this->actingAs($this->admin)->get('/tinjauan')->assertOk();
    }

    public function test_login_and_inactive_account_rejection(): void
    {
        $this->post('/login', ['email' => $this->student->email, 'password' => 'BrudelDemo!2026'])->assertRedirect('/');
        $this->assertAuthenticatedAs($this->student);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->student->update(['active' => false]);
        $this->post('/login', ['email' => $this->student->email, 'password' => 'BrudelDemo!2026'])->assertSessionHasErrors('email');
    }

    public function test_ukm_acceptance_creates_membership_and_audit(): void
    {
        $r = Recruitment::first();
        $this->actingAs($this->student)->post('/rekrutmen/'.$r->id, ['motivation' => 'Saya ingin berlatih badminton dan berkembang bersama komunitas.'])->assertRedirect('/pendaftaran');
        $a = ClubApplication::where('recruitment_id', $r->id)->first();
        $this->actingAs($this->manager)->post('/tinjauan/ukm/'.$a->id, ['decision' => 'accepted'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('memberships', ['user_id' => $this->student->id, 'club_id' => $r->club_id, 'status' => 'active']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'club.accepted', 'subject_id' => $a->id]);
    }

    public function test_deactivated_session_can_logout_but_cannot_access_private_pages(): void
    {
        $this->actingAs($this->student);
        $this->student->update(['active' => false]);
        $this->get('/')->assertForbidden();
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_duplicate_ukm_and_existing_active_members_are_rejected(): void
    {
        $r = Recruitment::first();
        $data = ['motivation' => 'Motivasi bergabung dengan UKM pilihan untuk pengembangan diri.'];
        $this->actingAs($this->student)->post('/rekrutmen/'.$r->id, $data)->assertSessionHasNoErrors();
        $this->post('/rekrutmen/'.$r->id, $data)->assertSessionHasErrors('action');
        $existing = Recruitment::where('club_id', Membership::where('user_id', $this->student->id)->whereHas('club', fn ($q) => $q->where('category', 'ukm'))->first()->club_id)->first();
        $this->post('/rekrutmen/'.$existing->id, $data)->assertSessionHasErrors('action');
    }

    public function test_last_ukm_place_cannot_be_accepted_twice_sequentially(): void
    {
        $r = Recruitment::first();
        $r->update(['quota' => 1]);
        $a = $this->service->applyClub($this->student, $r->id, 'Saya ingin bergabung dan belajar bersama komunitas.');
        $b = $this->service->applyClub($this->member, $r->id, 'Saya ingin bergabung dan belajar bersama komunitas.');
        $this->service->reviewClub($this->manager, $a->id, 'accepted', null);
        $this->actingAs($this->manager)->post('/tinjauan/ukm/'.$b->id, ['decision' => 'accepted'])->assertSessionHasErrors('action');
        $this->assertEquals('submitted', $b->fresh()->status);
        $this->assertEquals(1, Membership::where('club_id', $r->club_id)->whereIn('user_id', [$this->student->id, $this->member->id])->count());
    }

    public function test_ukm_scope_self_approval_expiration_and_event_separation(): void
    {
        $r = Recruitment::whereHas('club', fn ($q) => $q->where('name', 'UKM Manado Poltekkes Choir'))->first();
        $a = $this->service->applyClub($this->student, $r->id, 'Saya ingin bergabung dan belajar bersama komunitas.');
        $this->actingAs($this->manager)->post('/tinjauan/ukm/'.$a->id, ['decision' => 'accepted'])->assertForbidden();
        $this->get('/tinjauan')->assertDontSee($a->motivation);
        Membership::where('user_id', $this->manager->id)->where('club_id', Recruitment::first()->club_id)->update(['status' => 'left']);
        $self = $this->service->applyClub($this->manager, Recruitment::first()->id, 'Saya ingin bergabung dan belajar bersama komunitas.');
        $this->post('/tinjauan/ukm/'.$self->id, ['decision' => 'accepted'])->assertForbidden();
        $reg = $this->draft();
        $this->service->submit($this->student, $reg->id);
        $this->post('/tinjauan/lomba/'.$reg->id, ['decision' => 'accepted'])->assertForbidden();
        Assignment::where('user_id', $this->manager->id)->update(['ends_at' => now()->subMinute()]);
        $this->get('/tinjauan')->assertForbidden();
    }

    public function test_revision_requires_reason_and_can_be_resubmitted(): void
    {
        $a = $this->service->applyClub($this->student, Recruitment::first()->id, 'Saya ingin bergabung dan belajar bersama komunitas.');
        $this->actingAs($this->manager)->post('/tinjauan/ukm/'.$a->id, ['decision' => 'revision'])->assertSessionHasErrors('action');
        $this->post('/tinjauan/ukm/'.$a->id, ['decision' => 'revision', 'reason' => 'Jelaskan pengalaman bermain sebelumnya.'])->assertSessionHasNoErrors();
        $this->actingAs($this->student)->post('/rekrutmen/'.$a->recruitment_id, ['motivation' => 'Saya memiliki pengalaman bermain badminton sejak SMA.'])->assertSessionHasNoErrors();
        $this->assertEquals('submitted', $a->fresh()->status);
    }

    public function test_individual_flow_and_quota_validation(): void
    {
        $r = $this->draft();
        $r->competition->update(['quota' => 1]);
        $this->actingAs($this->student)->post('/pendaftaran/'.$r->id.'/submit')->assertSessionHasNoErrors();
        $this->post('/pendaftaran/'.$r->id.'/submit')->assertSessionHasErrors('action');
        $r2 = $this->draft($this->member);
        $this->actingAs($this->member)->post('/pendaftaran/'.$r2->id.'/submit')->assertSessionHasErrors('action');
        $this->actingAs($this->admin)->post('/tinjauan/lomba/'.$r->id, ['decision' => 'accepted'])->assertSessionHasNoErrors();
        $this->assertEquals('accepted', $r->fresh()->status);
    }

    public function test_team_needs_confirmation_and_roster_locks_after_submit(): void
    {
        $r = $this->draft(team: true);
        $this->service->invite($this->student, $r->id, $this->member->nim);
        $this->actingAs($this->student)->post('/pendaftaran/'.$r->id.'/submit')->assertSessionHasErrors('action');
        $p = Participant::where('user_id', $this->member->id)->first();
        $this->post('/undangan/'.$p->id, ['decision' => 'accept'])->assertForbidden();
        $this->actingAs($this->member)->post('/undangan/'.$p->id, ['decision' => 'accept'])->assertSessionHasNoErrors();
        $this->actingAs($this->student)->post('/pendaftaran/'.$r->id.'/submit')->assertSessionHasNoErrors();
        $this->post('/pendaftaran/'.$r->id.'/invite', ['nim' => $this->manager->nim])->assertSessionHasErrors('action');
        $this->assertEquals('submitted', $r->fresh()->status);
    }

    public function test_pending_invitee_cannot_register_twice_and_decline_releases_slot(): void
    {
        $r = $this->draft(team: true);
        $this->service->invite($this->student, $r->id, $this->member->nim);
        $this->actingAs($this->member)->post('/lomba/'.$r->competition_id.'/draft', ['team_name' => 'Tim lain', 'motivation' => 'Motivasi panjang untuk membuat tim lainnya.'])->assertSessionHasErrors('action');
        $p = Participant::where('user_id', $this->member->id)->first();
        $this->post('/undangan/'.$p->id, ['decision' => 'decline'])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('participants', ['id' => $p->id]);
        $this->draft($this->member, true);
        $this->assertEquals(2, Registration::where('competition_id', $r->competition_id)->count());
    }

    public function test_team_member_cannot_review_their_own_team(): void
    {
        $r = $this->draft(team: true);
        $this->service->invite($this->student, $r->id, $this->member->nim);
        $p = Participant::where('user_id', $this->member->id)->first();
        $this->service->respond($this->member, $p->id, true);
        $this->service->submit($this->student, $r->id);
        Assignment::create(['user_id' => $this->member->id, 'role' => 'admin_event', 'event_id' => $r->competition->event_id, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
        $this->actingAs($this->member)->post('/tinjauan/lomba/'.$r->id, ['decision' => 'accepted'])->assertForbidden();
    }

    public function test_document_owner_and_scoped_reviewer_only(): void
    {
        Storage::fake('local');
        config(['brudel.document_disk' => 'local']);
        $r = $this->draft();
        $this->actingAs($this->student)->post('/pendaftaran/'.$r->id.'/document', ['document' => UploadedFile::fake()->createWithContent('karya.pdf', "%PDF-1.4\n%BRUDEL test\n%%EOF\n")])->assertSessionHasNoErrors();
        $r->refresh();
        Storage::disk('local')->assertExists($r->document_path);
        $this->get('/pendaftaran/'.$r->id.'/document')->assertOk();
        $this->actingAs($this->member)->get('/pendaftaran/'.$r->id.'/document')->assertForbidden();
        $this->actingAs($this->manager)->get('/pendaftaran/'.$r->id.'/document')->assertForbidden();
        $this->actingAs($this->admin)->get('/pendaftaran/'.$r->id.'/document')->assertOk();
        $this->actingAs($this->student)->post('/pendaftaran/'.$r->id.'/document', ['document' => UploadedFile::fake()->create('bad.html', 10, 'text/html')])->assertSessionHasErrors('document');
    }

    public function test_event_scope_is_enforced_for_review_and_export(): void
    {
        $other = Event::create(['name' => 'Event berbeda', 'organizer' => 'Kampus', 'status' => 'approved']);
        $eventAdmin = User::where('email', 'event@demo.brudel.test')->first();
        $r = $this->draft();
        $r->competition->update(['event_id' => $other->id]);
        $this->service->submit($this->student, $r->id);
        $this->actingAs($eventAdmin)->post('/tinjauan/lomba/'.$r->id, ['decision' => 'accepted'])->assertForbidden();
        $this->get('/tinjauan')->assertDontSee($r->motivation);
        $response = $this->get('/ekspor/lomba')->assertOk();
        $this->assertStringNotContainsString($this->student->name, $response->streamedContent());
        $this->actingAs($this->student)->get('/ekspor/ukm')->assertForbidden();
    }

    public function test_closed_competition_required_document_and_unsupported_flows(): void
    {
        $r = $this->draft();
        $r->competition->update(['requires_document' => true]);
        $this->actingAs($this->student)->post('/pendaftaran/'.$r->id.'/submit')->assertSessionHasErrors('action');
        $r->competition->update(['requires_document' => false, 'closes_at' => now()->subMinute()]);
        $this->post('/pendaftaran/'.$r->id.'/submit')->assertSessionHasErrors('action');
        $r->competition->update(['closes_at' => now()->addDay(), 'fee' => 10000]);
        $this->post('/pendaftaran/'.$r->id.'/submit')->assertSessionHasErrors('action');
    }

    public function test_database_prevents_duplicate_participant_and_livewire_filters_work(): void
    {
        $this->actingAs($this->student);
        Livewire::test(Discovery::class, ['kind' => 'ukm'])->set('category', 'himaju')->assertSee('HIMAJU Farmasi')->assertDontSee('UKM Olahraga')->assertDontSee('Badan Eksekutif Mahasiswa')
            ->set('category', '')->set('search', 'choir')->assertSee('UKM Manado Poltekkes Choir')->assertDontSee('HIMAJU Farmasi');
        Livewire::test(Discovery::class, ['kind' => 'lomba'])->set('field', 'Seni')->assertSee('Lomba Poster Kesehatan')->assertDontSee('Badminton Tunggal');
        $r = $this->draft();
        $this->expectException(UniqueConstraintViolationException::class);
        Participant::create(['registration_id' => $r->id, 'competition_id' => $r->competition_id, 'user_id' => $this->student->id]);
    }
}
