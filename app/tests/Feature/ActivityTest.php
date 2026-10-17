<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubActivity;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ActivityTest extends DemoTestCase
{
    private function activity(string $type): ClubActivity
    {
        return ClubActivity::where('type', $type)->firstOrFail();
    }

    public function test_coach_creates_activities_and_members_are_notified(): void
    {
        $badminton = Club::where('name', 'UKM Olahraga')->first();
        $monday = now(config('brudel.timezone'))->next('Monday')->setTime(9, 0);
        $data = ['club_id' => $badminton->id, 'type' => 'latihan', 'title' => 'Latihan fisik', 'location' => 'GOR', 'starts_at' => $monday->format('Y-m-d\TH:i'), 'ends_at' => $monday->copy()->addHours(2)->format('Y-m-d\TH:i'), 'is_public' => 1];
        $this->actingAs($this->user('mahasiswa'))->post('/kegiatan', $data)->assertForbidden();
        $this->actingAs($this->user('pembina'))->post('/kegiatan', $data)->assertRedirect();
        $this->actingAs($this->user('pengurus'))->post('/kegiatan', ['type' => 'meeting', 'title' => 'Rapat', 'is_public' => 1] + $data)->assertRedirect();
        $this->assertFalse(ClubActivity::where('title', 'Rapat')->first()->is_public, 'Meeting internal selalu tertutup.');
        $this->assertTrue($this->user('atlet1')->notifications()->get()->contains(fn ($n) => str_contains($n->data['body'], 'Latihan fisik')));
    }

    public function test_attendance_code_is_short_lived_and_only_for_active_members(): void
    {
        $training = $this->activity('latihan');
        $coach = $this->user('pembina');
        $this->actingAs($this->user('atlet1'))->post('/kegiatan/'.$training->id.'/kode')->assertForbidden();
        $this->actingAs($coach)->post('/kegiatan/'.$training->id.'/kode', ['minutes' => 30])->assertSessionHasNoErrors();
        $code = $training->fresh()->attendance_code;
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{6}$/', $code);
        $this->get('/kegiatan/'.$training->id)->assertSee($code);

        $this->actingAs($this->user('atlet1'))->post('/kegiatan/hadir', ['code' => strtolower($code)])->assertRedirect('/kegiatan/'.$training->id);
        $this->post('/kegiatan/hadir', ['code' => $code])->assertSessionHasErrors('action');
        $this->actingAs($this->user('mahasiswa'))->post('/kegiatan/hadir', ['code' => $code])->assertSessionHasErrors('action');
        $this->actingAs($this->user('atlet2'))->post('/kegiatan/hadir', ['code' => 'ZZZZZZ'])->assertSessionHasErrors('action');

        $this->actingAs($coach)->post('/kegiatan/'.$training->id.'/kode/tutup');
        $this->actingAs($this->user('atlet2'))->post('/kegiatan/hadir', ['code' => $code])->assertSessionHasErrors('action');
        $this->actingAs($coach)->post('/kegiatan/'.$training->id.'/hadir-manual', ['user_id' => $this->user('atlet2')->id])->assertSessionHasNoErrors();
        $this->post('/kegiatan/'.$training->id.'/hadir-manual', ['user_id' => $this->user('mahasiswa')->id])->assertSessionHasErrors('action');

        $friendly = $this->activity('persahabatan');
        $this->post('/kegiatan/'.$friendly->id.'/kode')->assertSessionHasErrors('action');
    }

    public function test_training_report_lists_present_and_absent_members(): void
    {
        $training = $this->activity('latihan');
        $coach = $this->user('pengurus');
        $this->actingAs($coach)->post('/kegiatan/'.$training->id.'/kode');
        $this->actingAs($this->user('atlet1'))->post('/kegiatan/hadir', ['code' => $training->fresh()->attendance_code]);
        $this->actingAs($coach)->post('/kegiatan/'.$training->id.'/laporan', ['notes' => 'Fokus footwork.'])->assertSessionHasNoErrors();
        $this->assertSame('completed', $training->fresh()->status);
        $this->assertNull($training->fresh()->attendance_code, 'Kode ditutup setelah laporan dibuat.');

        $report = $this->get('/kegiatan/'.$training->id.'/laporan')->assertOk()->assertSee('Rifky Lasut')->assertSee('Grace Wowor')->assertSee('Cetak / Simpan PDF');
        $this->assertSame(1, substr_count($report->getContent(), 'Rifky Lasut'));
        $csv = $this->get('/kegiatan/'.$training->id.'/laporan.csv')->assertOk()->streamedContent();
        $this->assertMatchesRegularExpression('/^Hadir,"?Rifky Lasut"?,/m', $csv);
        $this->assertMatchesRegularExpression('/^"?Tidak hadir"?,"?Grace Wowor"?,/m', $csv);
        $this->actingAs($this->user('atlet1'))->get('/kegiatan/'.$training->id.'/laporan')->assertForbidden();
    }

    public function test_meeting_needs_minutes_and_friendly_match_needs_score_and_notes(): void
    {
        Storage::fake('local');
        $meeting = $this->activity('meeting');
        $coach = $this->user('pengurus');
        $this->actingAs($coach)->post('/kegiatan/'.$meeting->id.'/laporan', ['notes' => 'Evaluasi'])->assertSessionHasErrors('action');
        $this->post('/kegiatan/'.$meeting->id.'/laporan', ['minutes' => UploadedFile::fake()->createWithContent('notulen.docx', 'bukan docx')])->assertSessionHasErrors();
        $docx = UploadedFile::fake()->createWithContent('notulen.docx', "PK\x03\x04".str_repeat('x', 100));
        $this->post('/kegiatan/'.$meeting->id.'/laporan', ['minutes' => $docx])->assertSessionHasNoErrors();
        $this->assertSame('completed', $meeting->fresh()->status);
        $this->actingAs($this->user('atlet3'))->get('/kegiatan/'.$meeting->id.'/notulen')->assertOk();
        $this->actingAs($this->user('mahasiswa'))->get('/kegiatan/'.$meeting->id)->assertForbidden();
        $this->get('/kegiatan/'.$meeting->id.'/notulen')->assertForbidden();

        $friendly = $this->activity('persahabatan');
        $friendly->update(['starts_at' => now()->subHours(2), 'ends_at' => now()->subHour()]);
        $this->actingAs($coach)->post('/kegiatan/'.$friendly->id.'/laporan', ['score_home' => 2, 'score_away' => 1, 'notes' => 'Perbaiki servis.'])->assertSessionHasErrors('action');
        $this->post('/kegiatan/'.$friendly->id.'/laporan', ['opponent' => 'UKM Unsrat', 'score_home' => 2, 'score_away' => 1, 'notes' => 'Kurang'])->assertSessionHasErrors('action');
        $this->post('/kegiatan/'.$friendly->id.'/laporan', ['opponent' => 'UKM Unsrat', 'score_home' => 2, 'score_away' => 1, 'notes' => 'Perbaiki servis pendek dan rotasi ganda.'])->assertSessionHasNoErrors();
        $this->get('/kegiatan/'.$friendly->id)->assertSee('UKM Unsrat')->assertSee('Perbaiki servis pendek');
        $this->get('/kegiatan/'.$friendly->id.'/laporan')->assertSee('2 : 1');
    }

    public function test_public_front_page_and_activity_explorer_hide_internal_meetings(): void
    {
        $this->get('/')->assertOk()->assertSee('Info lomba mahasiswa')->assertSee('Tenis Meja Tunggal')
            ->assertSee('Latihan rutin tunggal · Demo')->assertDontSee('Rapat evaluasi semester')
            ->assertSee('UKM buka pendaftaran anggota')->assertSee('UKM Manado Poltekkes Choir')->assertSee('href="/login"', false)
            ->assertDontSee('DEMO001')->assertDontSee('@demo.brudel.test');
        $this->actingAs($this->user('mahasiswa'))->get('/kegiatan')->assertOk()->assertSee('Latihan rutin tunggal')->assertDontSee('Rapat evaluasi semester')->assertSee('Kode kehadiran');
        $this->actingAs($this->user('atlet1'))->get('/kegiatan')->assertSee('Persahabatan vs UKM Badminton Unsrat');
        $this->actingAs($this->user('pembina'))->get('/kegiatan')->assertSee('Buat kegiatan UKM')->assertSee('Rapat evaluasi semester');
        $this->actingAs($this->user('mahasiswa'))->get('/')->assertViewIs('dashboard');
    }
}
