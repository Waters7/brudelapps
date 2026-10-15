<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AwardResult;
use App\Models\Certificate;
use App\Models\Competition;
use App\Models\Delegation;
use App\Models\Participant;
use App\Models\Registration;
use App\Services\Report\ReportService;
use Illuminate\Support\Facades\Storage;

class RecognitionTest extends DemoTestCase
{
    private function acceptedEntry(Competition $competition, string $userKey): Registration
    {
        $user = $this->user($userKey);
        $registration = Registration::create(['competition_id' => $competition->id, 'user_id' => $user->id, 'motivation' => 'Pendaftaran untuk pengujian otomatis.', 'status' => 'accepted', 'submitted_at' => now()]);
        Participant::create(['registration_id' => $registration->id, 'competition_id' => $competition->id, 'user_id' => $user->id, 'status' => 'confirmed']);

        return $registration;
    }

    public function test_certificates_are_private_versioned_and_publicly_verifiable(): void
    {
        Storage::fake('local');
        $poster = $this->competition('Lomba Poster Kesehatan');
        $this->acceptedEntry($poster, 'mahasiswa');
        $student = $this->user('mahasiswa');
        $issue = ['competition_id' => $poster->id, 'user_id' => $student->id, 'number' => 'BRUDEL/2026/001', 'award_type' => 'Peserta', 'issued_on' => now()->toDateString()];

        $this->actingAs($this->user('event'))->post('/kelola/sertifikat', $issue + ['file' => $this->pdf()])->assertSessionHasErrors('action');
        $poster->update(['results_published_at' => now()]);
        $this->post('/kelola/sertifikat', ['user_id' => $this->user('atlet1')->id] + $issue + ['file' => $this->pdf()])->assertSessionHasErrors('action');
        $this->actingAs($this->user('pengurus'))->post('/kelola/sertifikat', $issue + ['file' => $this->pdf()])->assertForbidden();
        $this->actingAs($this->user('event'))->post('/kelola/sertifikat', $issue + ['file' => $this->pdf()])->assertSessionHasNoErrors();
        $certificate = Certificate::first();

        $this->actingAs($student)->get('/sertifikat')->assertOk()->assertSee('BRUDEL/2026/001');
        $this->get('/sertifikat/'.$certificate->id.'/unduh')->assertOk();
        $this->actingAs($this->user('anggota'))->get('/sertifikat/'.$certificate->id.'/unduh')->assertForbidden();
        $this->actingAs($this->user('pengurus'))->get('/sertifikat/'.$certificate->id.'/unduh')->assertForbidden();

        $this->actingAs($this->user('event'))->post('/kelola/sertifikat/'.$certificate->id.'/ganti', ['file' => $this->pdf(), 'reason' => 'Ejaan nama diperbaiki'])->assertSessionHasNoErrors();
        $this->assertSame(2, $certificate->fresh()->current_version);
        $this->assertSame(2, $certificate->versions()->count());

        auth()->logout();
        $this->get('/verifikasi/'.$certificate->verification_token)->assertOk()->assertSee('Sertifikat sah')->assertSee('Bintang Mandagi')->assertDontSee('DEMO001')->assertDontSee($student->email);
        $this->get('/verifikasi/token-tidak-ada')->assertOk()->assertSee('Tidak ditemukan');

        $this->actingAs($this->user('event'))->post('/kelola/sertifikat/'.$certificate->id.'/cabut', ['reason' => 'Diterbitkan untuk lomba yang salah'])->assertSessionHasNoErrors();
        $this->assertSame('revoked', $certificate->fresh()->status);
        $this->actingAs($student)->get('/sertifikat/'.$certificate->id.'/unduh')->assertForbidden();
        auth()->logout();
        $this->get('/verifikasi/'.$certificate->verification_token)->assertSee('Sertifikat telah dicabut');
        $this->assertDatabaseHas('audit_logs', ['action' => 'certificate.revoked', 'subject_id' => $certificate->id]);
    }

    public function test_team_award_counts_once_with_unique_achievers(): void
    {
        $reports = app(ReportService::class);
        $all = $reports->summary([]);
        $this->assertSame(2, $all['award_count'], 'Penghargaan pending tidak dihitung.');
        $this->assertSame(1, $all['team_awards']);
        $this->assertSame(3, $all['recipient_rows']);
        $this->assertSame(3, $all['unique_achievers']);
        $this->assertSame(1, $all['by_level'][3]);

        $province = $reports->summary(['level' => 3]);
        $this->assertSame([1, 2], [$province['award_count'], $province['unique_achievers']]);
        $this->assertSame(1, $reports->summary(['team' => 'individu'])['award_count']);
        $nutrition = $reports->summary(['program_id' => 2]);
        $this->assertSame([1, 1], [$nutrition['award_count'], $nutrition['unique_achievers']], 'Penghargaan tim dihitung sekali; hanya penerima prodi terpilih yang dihitung unik.');

        $this->actingAs($this->user('pimpinan'))->get('/laporan?level=3')->assertOk()->assertSee('Lomba Karya Tulis Ilmiah Kesehatan Provinsi');
        $csv = $this->get('/laporan/ekspor/prestasi')->assertOk()->streamedContent();
        $this->assertStringContainsString('Kejuaraan Badminton Kota Manado', $csv);
        $this->assertStringNotContainsString('Lomba Poster Regional', $csv);
        $this->assertDatabaseHas('audit_logs', ['action' => 'report.exported']);
    }

    public function test_exports_follow_assignment_scope(): void
    {
        $this->actingAs($this->user('pengurus'))->get('/laporan/ekspor/prestasi')->assertForbidden();
        $members = $this->get('/laporan/ekspor/keanggotaan')->assertOk()->streamedContent();
        $this->assertStringNotContainsString('Karya Tulis Ilmiah', $members);
        $this->actingAs($this->user('event'))->get('/laporan/ekspor/keanggotaan')->assertForbidden();
        $this->get('/laporan/ekspor/pendaftaran')->assertOk();
        $this->actingAs($this->user('mahasiswa'))->get('/laporan/ekspor/pendaftaran')->assertForbidden();
    }

    public function test_external_achievement_needs_evidence_and_independent_verification(): void
    {
        Storage::fake('local');
        $student = $this->user('mahasiswa');
        $claim = ['title' => 'Lomba Esai Nasional', 'organizer' => 'Kementerian (contoh)', 'field' => 'Akademik', 'level' => 5, 'award' => 'Juara 1', 'awarded_on' => now()->subWeek()->toDateString(), 'members' => 'DEMO002', 'team_name' => 'Tim Esai'];
        $this->actingAs($student)->post('/prestasi', $claim)->assertSessionHasErrors('evidence');
        $this->post('/prestasi', array_merge($claim, ['members' => 'TIDAKADA', 'evidence' => $this->pdf()]))->assertSessionHasErrors('action');
        $this->post('/prestasi', $claim + ['evidence' => $this->pdf()])->assertSessionHasNoErrors();
        $award = AwardResult::where('title', 'Lomba Esai Nasional')->first();
        $this->assertSame(['pending', true, 2], [$award->status, $award->is_team, $award->recipients()->count()]);
        $this->assertSame(2, app(ReportService::class)->summary([])['award_count']);

        $this->actingAs($this->user('anggota'))->get('/prestasi/'.$award->id.'/bukti')->assertOk();
        $this->actingAs($this->user('atlet1'))->get('/prestasi/'.$award->id.'/bukti')->assertForbidden();

        Assignment::create(['user_id' => $this->user('anggota')->id, 'role' => 'kemahasiswaan', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
        $this->actingAs($this->user('anggota'))->post('/kelola/prestasi/'.$award->id, ['decision' => 'verified'])->assertForbidden();
        $this->actingAs($this->user('admin'))->post('/kelola/prestasi/'.$award->id, ['decision' => 'rejected'])->assertSessionHasErrors('action');
        $this->post('/kelola/prestasi/'.$award->id, ['decision' => 'verified'])->assertSessionHasNoErrors();
        $this->assertSame(3, app(ReportService::class)->summary([])['award_count']);
        $this->assertSame(1, app(ReportService::class)->summary(['level' => 5])['award_count']);
    }

    public function test_delegation_requires_leadership_decision_before_assignment_letter(): void
    {
        Storage::fake('local');
        $selection = $this->competition('Seleksi Baca Puisi');
        $this->acceptedEntry($selection, 'mahasiswa');
        $proposal = ['competition_id' => $selection->id, 'title' => 'Delegasi Baca Puisi', 'destination_event' => 'Festival Puisi Nasional', 'organizer' => 'Penyelenggara contoh', 'level' => 5, 'field' => 'Seni',
            'starts_at' => now()->addMonth()->format('Y-m-d\TH:i'), 'location' => 'Jakarta', 'reason' => 'Peringkat terbaik pada seleksi internal.', 'companions' => 'pembina@demo.brudel.test'];

        $this->actingAs($this->user('event'))->post('/delegasi', $proposal + ['participants' => [$this->user('anggota')->id]])->assertSessionHasErrors('action');
        $this->post('/delegasi', $proposal + ['participants' => [$this->user('mahasiswa')->id]])->assertSessionHasNoErrors();
        $delegation = Delegation::first();
        $this->assertSame(['draft', 2], [$delegation->status, $delegation->members()->count()]);
        $this->assertSame(0, AwardResult::where('delegation_id', $delegation->id)->count());

        $this->actingAs($this->user('pimpinan'))->post('/delegasi/'.$delegation->id.'/keputusan', ['decision' => 'approved'])->assertSessionHasErrors('action');
        $this->actingAs($this->user('event'))->post('/delegasi/'.$delegation->id.'/ajukan')->assertSessionHasNoErrors();
        $this->post('/delegasi/'.$delegation->id.'/keputusan', ['decision' => 'approved'])->assertForbidden();
        $this->actingAs($this->user('admin'))->post('/delegasi/'.$delegation->id.'/surat-tugas', ['letter_number' => 'ST/01/2026', 'file' => $this->pdf()])->assertSessionHasErrors('action');

        Assignment::create(['user_id' => $this->user('pembina')->id, 'role' => 'pimpinan', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
        $this->actingAs($this->user('pembina'))->post('/delegasi/'.$delegation->id.'/keputusan', ['decision' => 'approved'])->assertForbidden();
        $this->actingAs($this->user('pimpinan'))->post('/delegasi/'.$delegation->id.'/keputusan', ['decision' => 'rejected'])->assertSessionHasErrors('action');
        $this->post('/delegasi/'.$delegation->id.'/keputusan', ['decision' => 'approved'])->assertSessionHasNoErrors();

        $this->actingAs($this->user('event'))->post('/delegasi/'.$delegation->id.'/surat-tugas', ['letter_number' => 'ST/01/2026', 'file' => $this->pdf()])->assertForbidden();
        $this->actingAs($this->user('admin'))->post('/delegasi/'.$delegation->id.'/surat-tugas', ['letter_number' => 'ST/01/2026', 'file' => $this->pdf()])->assertSessionHasNoErrors();
        $this->actingAs($this->user('mahasiswa'))->get('/delegasi/'.$delegation->id)->assertOk()->assertSee('ST/01/2026');
        $this->get('/delegasi/'.$delegation->id.'/surat-tugas')->assertOk();
        $this->actingAs($this->user('anggota'))->get('/delegasi/'.$delegation->id)->assertForbidden();
        $this->get('/delegasi/'.$delegation->id.'/surat-tugas')->assertForbidden();

        $this->actingAs($this->user('event'))->post('/delegasi/'.$delegation->id.'/hasil', ['outcome' => 'Meraih juara 2 nasional.', 'award' => 'Juara 2'])->assertSessionHasErrors('action');
        $this->post('/delegasi/'.$delegation->id.'/hasil', ['outcome' => 'Meraih juara 2 nasional.', 'award' => 'Juara 2', 'evidence' => $this->pdf()])->assertSessionHasNoErrors();
        $this->assertSame('completed', $delegation->fresh()->status);
        $award = AwardResult::where('delegation_id', $delegation->id)->first();
        $this->assertSame(['pending', 5], [$award->status, $award->level]);
        $this->assertSame(1, $award->recipients()->count(), 'Pendamping bukan penerima prestasi.');
    }
}
