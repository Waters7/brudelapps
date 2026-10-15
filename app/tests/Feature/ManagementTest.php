<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Club;
use App\Models\ClubApplication;
use App\Models\Event;
use App\Models\Membership;
use App\Models\Participant;
use App\Models\PaymentAccount;
use App\Models\PaymentProof;
use App\Models\ProgramApproval;
use App\Models\Recruitment;
use App\Models\Registration;
use App\Services\Registration\EnrollmentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ManagementTest extends DemoTestCase
{
    public function test_club_officers_only_manage_their_own_club(): void
    {
        $badminton = Club::where('name', 'Badminton')->first();
        $choir = Club::where('name', 'Paduan Suara')->first();
        $recruitment = $badminton->recruitments()->first();
        $this->actingAs($this->user('pengurus'));

        $this->get('/kelola/ukm/'.$choir->id)->assertForbidden();
        $this->post('/kelola/ukm/'.$choir->id, ['field' => 'Seni', 'description' => 'Ubah', 'mentor' => 'X', 'contact' => 'Y'])->assertForbidden();
        $this->get('/kelola/ukm/'.$choir->id.'/ekspor')->assertForbidden();
        $this->post('/kelola/ukm/'.$badminton->id, ['name' => 'Nama Baru', 'field' => 'Olahraga', 'description' => 'Deskripsi baru UKM.', 'mentor' => 'Pembina', 'contact' => 'kontak@kampus.test'])->assertSessionHasNoErrors();
        $this->assertSame('Badminton', $badminton->fresh()->name, 'Pengurus tidak dapat mengganti nama UKM.');

        $window = ['opens_at' => now()->subDay()->format('Y-m-d\TH:i'), 'closes_at' => now()->addWeek()->format('Y-m-d\TH:i'), 'requirements' => 'Syarat'];
        app(EnrollmentService::class)->applyClub($this->user('mahasiswa'), $recruitment->id, 'Saya ingin bergabung dan berlatih badminton.');
        app(EnrollmentService::class)->reviewClub($this->user('pengurus'), ClubApplication::where('recruitment_id', $recruitment->id)->first()->id, 'accepted', null);
        $this->post('/kelola/ukm/'.$badminton->id.'/rekrutmen/'.$recruitment->id, ['name' => 'Periode', 'quota' => 0] + $window)->assertSessionHasErrors('quota');
        $this->post('/kelola/ukm/'.$choir->id.'/rekrutmen/'.$recruitment->id, ['name' => 'Periode', 'quota' => 5] + $window)->assertForbidden();
        $this->post('/kelola/ukm/'.$badminton->id.'/rekrutmen', ['name' => 'Gelombang 2', 'quota' => 10, 'requires_document' => 1] + $window)->assertSessionHasNoErrors();
        $this->assertTrue(Recruitment::where('name', 'Gelombang 2')->first()->requires_document);

        $membership = Membership::where('club_id', $badminton->id)->where('user_id', $this->user('mahasiswa')->id)->first();
        $this->post('/kelola/anggota/'.$membership->id, ['status' => 'alumni', 'division' => 'Ganda'])->assertSessionHasErrors('action');
        $this->post('/kelola/anggota/'.$membership->id, ['status' => 'alumni', 'division' => 'Ganda', 'reason' => 'Lulus tahun ini'])->assertSessionHasNoErrors();
        $this->assertSame(['alumni', 'Ganda'], [$membership->fresh()->status, $membership->fresh()->division]);
        $this->assertNotNull($membership->fresh()->left_at);
        $this->assertDatabaseHas('status_histories', ['subject_type' => 'memberships', 'subject_id' => $membership->id, 'from_status' => 'active', 'to_status' => 'alumni']);
        $csv = $this->get('/kelola/ukm/'.$badminton->id.'/ekspor')->assertOk()->streamedContent();
        $this->assertStringContainsString('Bintang Mandagi', $csv);
    }

    public function test_club_document_is_required_when_configured_and_private(): void
    {
        Storage::fake('local');
        $recruitment = Recruitment::whereHas('club', fn ($q) => $q->where('name', 'Seni Tari'))->first();
        $recruitment->update(['requires_document' => true]);
        $this->actingAs($this->user('mahasiswa'))->post('/rekrutmen/'.$recruitment->id, ['motivation' => 'Saya ingin belajar tari tradisional Minahasa.'])->assertSessionHasErrors('action');
        $this->post('/rekrutmen/'.$recruitment->id, ['motivation' => 'Saya ingin belajar tari tradisional Minahasa.', 'document' => $this->pdf()])->assertRedirect('/pendaftaran');
        $application = ClubApplication::where('recruitment_id', $recruitment->id)->first();
        $this->get('/ukm-pendaftaran/'.$application->id.'/document')->assertOk();
        $this->actingAs($this->user('anggota'))->get('/ukm-pendaftaran/'.$application->id.'/document')->assertForbidden();
        $this->actingAs($this->user('pengurus'))->get('/ukm-pendaftaran/'.$application->id.'/document')->assertForbidden();
        $this->actingAs($this->user('admin'))->get('/ukm-pendaftaran/'.$application->id.'/document')->assertOk();
        $this->actingAs($this->user('anggota'))->post('/rekrutmen/'.$recruitment->id, ['motivation' => 'Saya ingin belajar tari tradisional Minahasa.', 'document' => UploadedFile::fake()->createWithContent('palsu.pdf', '<html>bukan pdf</html>')])->assertSessionHasErrors();
    }

    public function test_ukm_chair_proposes_event_and_student_affairs_decides(): void
    {
        $badminton = Club::where('name', 'Badminton')->first();
        $chair = $this->user('pengurus');
        $this->actingAs($chair)->post('/kelola/event', ['name' => 'Kejuaraan Internal UKM', 'organizer' => 'UKM Badminton', 'pathway' => 'internal', 'club_id' => $badminton->id])->assertSessionHasNoErrors();
        $event = Event::where('name', 'Kejuaraan Internal UKM')->first();
        $this->assertSame('draft', $event->status);
        $this->post('/kelola/event/'.$event->id.'/ajukan')->assertSessionHasErrors('action');
        $this->post('/kelola/event/'.$event->id.'/lomba', [
            'name' => 'Tunggal Putra', 'field' => 'Olahraga', 'level' => 1, 'description' => 'Lomba internal', 'requirements' => 'Mahasiswa aktif',
            'format' => 'individual', 'quota' => 16, 'fee' => 0, 'fee_basis' => 'team', 'opens_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'closes_at' => now()->addDays(8)->format('Y-m-d\TH:i'), 'starts_at' => now()->addDays(10)->format('Y-m-d\TH:i'), 'location' => 'GOR', 'assessment_type' => 'bracket',
        ])->assertSessionHasNoErrors();
        $this->post('/kelola/event/'.$event->id.'/ajukan')->assertSessionHasNoErrors();
        $this->assertSame('submitted', $event->fresh()->status);
        $this->post('/kelola/event/'.$event->id.'/keputusan', ['decision' => 'approved'])->assertForbidden();

        $this->actingAs($this->user('admin'))->post('/kelola/event/'.$event->id.'/keputusan', ['decision' => 'revision'])->assertSessionHasErrors('action');
        $this->post('/kelola/event/'.$event->id.'/keputusan', ['decision' => 'approved'])->assertSessionHasNoErrors();
        $this->assertSame('approved', $event->fresh()->status);
        $this->assertDatabaseHas('status_histories', ['subject_type' => 'events', 'subject_id' => $event->id, 'to_status' => 'approved']);

        $competition = $event->competitions()->first();
        $this->assertFalse($chair->managesCompetition($competition), 'Ketua UKM tidak otomatis menjadi admin event.');
        $this->actingAs($chair)->get('/kelola/lomba/'.$competition->id)->assertForbidden();
        $this->post('/kelola/event/'.$event->id.'/lomba/'.$competition->id, ['name' => 'Ubah'])->assertForbidden();
    }

    /**
     * Cross-program team: each program reviews only its own unit; payment must be verified before acceptance.
     */
    public function test_program_approval_isolation_and_manual_payment_flow(): void
    {
        Storage::fake('local');
        $service = app(EnrollmentService::class);
        $debate = $this->competition('Debat Bahasa Inggris');
        $leader = $this->user('mahasiswa');
        $member = $this->user('anggota');
        $registration = $service->draft($leader, $debate->id, ['motivation' => 'Kami siap berdebat dengan argumen berbasis bukti.', 'team_name' => 'Tim Debat']);
        $service->invite($leader, $registration->id, $member->nim);
        $service->respond($member, Participant::where('user_id', $member->id)->where('registration_id', $registration->id)->first()->id, true);

        $this->actingAs($leader)->post('/pendaftaran/'.$registration->id.'/submit')->assertSessionHasErrors('action');
        $this->officialAccount();
        $this->post('/pendaftaran/'.$registration->id.'/submit')->assertSessionHasNoErrors();
        $registration->refresh();
        $this->assertSame(['program_review', 'unpaid', 25000], [$registration->status, $registration->payment_status, $registration->fee_snapshot]);
        $this->assertSame(2, $registration->approvals()->count());

        $mine = ProgramApproval::where('registration_id', $registration->id)->where('program_id', $leader->program_id)->first();
        $other = ProgramApproval::where('registration_id', $registration->id)->where('program_id', $member->program_id)->first();
        $this->actingAs($this->user('prodi'))->get('/prodi')->assertOk()->assertSee('Tim Debat');
        $this->post('/prodi/'.$other->id, ['decision' => 'approved'])->assertForbidden();
        $this->post('/prodi/'.$mine->id, ['decision' => 'approved'])->assertSessionHasNoErrors();
        $this->assertSame('program_review', $registration->fresh()->status);
        $this->actingAs($this->user('admin'))->post('/prodi/'.$other->id, ['decision' => 'approved'])->assertSessionHasNoErrors();
        $this->assertSame('submitted', $registration->fresh()->status);

        $this->actingAs($this->user('event'))->post('/tinjauan/lomba/'.$registration->id, ['decision' => 'accepted'])->assertSessionHasErrors('action');

        $account = PaymentAccount::first();
        $proof = ['payment_account_id' => $account->id, 'transferred_on' => now()->toDateString(), 'proof' => $this->pdf('bukti.pdf')];
        $this->actingAs($member)->post('/pendaftaran/'.$registration->id.'/pembayaran', $proof + ['amount' => 25000])->assertForbidden();
        $this->actingAs($leader)->post('/pendaftaran/'.$registration->id.'/pembayaran', $proof + ['amount' => 20000])->assertSessionHasErrors('action');
        $this->post('/pendaftaran/'.$registration->id.'/pembayaran', ['proof' => $this->pdf('bukti.pdf')] + $proof + ['amount' => 25000])->assertSessionHasNoErrors();
        $payment = PaymentProof::first();
        $this->assertSame('pending', $registration->fresh()->payment_status);

        $this->actingAs($this->user('atlet1'))->get('/pembayaran/'.$payment->id.'/bukti')->assertForbidden();
        Assignment::create(['user_id' => $member->id, 'role' => 'admin_event', 'event_id' => $debate->event_id, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
        $this->actingAs($member)->post('/pembayaran/'.$payment->id, ['decision' => 'accepted'])->assertForbidden();
        $this->actingAs($this->user('event'))->get('/pembayaran/'.$payment->id.'/bukti')->assertOk();
        $this->post('/pembayaran/'.$payment->id, ['decision' => 'rejected'])->assertSessionHasErrors('action');
        $this->post('/pembayaran/'.$payment->id, ['decision' => 'accepted'])->assertSessionHasNoErrors();
        $this->assertSame('paid', $registration->fresh()->payment_status);
        $this->post('/tinjauan/lomba/'.$registration->id, ['decision' => 'accepted'])->assertSessionHasNoErrors();
        $this->assertSame('accepted', $registration->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.accepted', 'subject_id' => $payment->id]);
    }

    public function test_student_affairs_can_add_a_program_after_the_demo_programs(): void
    {
        $this->actingAs($this->user('admin'))->post('/kelola/prodi', ['name' => 'D-III Kebidanan', 'department' => 'Kebidanan', 'code' => 'KEB'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('programs', ['code' => 'KEB', 'name' => 'D-III Kebidanan']);
    }

    public function test_program_rejection_returns_registration_for_revision(): void
    {
        $debate = $this->competition('Debat Bahasa Inggris');
        $debate->update(['fee' => 0]);
        $service = app(EnrollmentService::class);
        $leader = $this->user('mahasiswa');
        $registration = $service->draft($leader, $debate->id, ['motivation' => 'Kami siap berdebat dengan argumen berbasis bukti.', 'team_name' => 'Tim Satu']);
        $service->invite($leader, $registration->id, 'DEMO006');
        $service->respond($this->user('atlet3'), Participant::where('registration_id', $registration->id)->where('user_id', $this->user('atlet3')->id)->first()->id, true);
        $service->submit($leader, $registration->id);
        $approval = ProgramApproval::where('registration_id', $registration->id)->first();
        $this->assertSame(1, $registration->approvals()->count(), 'Satu prodi: satu persetujuan.');
        $this->actingAs($this->user('prodi'))->post('/prodi/'.$approval->id, ['decision' => 'rejected'])->assertSessionHasErrors('action');
        $this->post('/prodi/'.$approval->id, ['decision' => 'rejected', 'reason' => 'Bentrok jadwal praktik klinik'])->assertSessionHasNoErrors();
        $this->assertSame('revision', $registration->fresh()->status);
        $this->assertStringContainsString('praktik klinik', $registration->fresh()->reason);
    }

    public function test_unanswered_invitations_expire_after_registration_closes(): void
    {
        $service = app(EnrollmentService::class);
        $kti = $this->competition('Karya Tulis Ilmiah');
        $registration = $service->draft($this->user('mahasiswa'), $kti->id, ['motivation' => 'Kami meneliti gizi remaja di Manado.', 'team_name' => 'Tim Gizi']);
        $service->invite($this->user('mahasiswa'), $registration->id, 'DEMO002');
        $this->artisan('brudel:expire-invitations')->assertSuccessful();
        $this->assertSame(2, $registration->participants()->count(), 'Periode masih dibuka: undangan tetap.');
        $kti->update(['closes_at' => now()->subMinute()]);
        $this->artisan('brudel:expire-invitations')->expectsOutputToContain('1 undangan')->assertSuccessful();
        $this->assertSame(1, $registration->participants()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'team.invitation_expired']);
    }

    public function test_leader_can_remove_member_and_cancel_draft(): void
    {
        $service = app(EnrollmentService::class);
        $kti = $this->competition('Karya Tulis Ilmiah');
        $leader = $this->user('mahasiswa');
        $registration = $service->draft($leader, $kti->id, ['motivation' => 'Kami meneliti gizi remaja di Manado.', 'team_name' => 'Tim Gizi']);
        $service->invite($leader, $registration->id, 'DEMO002');
        $invited = Participant::where('registration_id', $registration->id)->where('user_id', $this->user('anggota')->id)->first();
        $this->actingAs($this->user('anggota'))->post('/pendaftaran/'.$registration->id.'/anggota/'.$invited->id.'/hapus')->assertForbidden();
        $this->actingAs($leader)->post('/pendaftaran/'.$registration->id.'/anggota/'.$invited->id.'/hapus')->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('participants', ['id' => $invited->id]);
        $service->invite($leader, $registration->id, 'DEMO002');
        $this->post('/pendaftaran/'.$registration->id.'/batal')->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $registration->fresh()->status);
        $this->assertSame(0, Participant::where('registration_id', $registration->id)->count());
        $again = $service->draft($this->user('anggota'), $kti->id, ['motivation' => 'Kami membuat tim baru setelah batal.', 'team_name' => 'Tim Baru']);
        $this->assertSame('draft', $again->status);
        $this->assertSame('draft', $service->draft($leader, $kti->id, ['motivation' => 'Kami mencoba kembali setelah batal.', 'team_name' => 'Tim Gizi'])->status);
        $this->assertSame(2, Registration::where('competition_id', $kti->id)->count());
    }
}
