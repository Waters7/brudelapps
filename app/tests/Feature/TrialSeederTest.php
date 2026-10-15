<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Club;
use App\Models\Delegation;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\User;
use App\Services\Scoring\ScoringService;
use Database\Seeders\TrialSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TrialSeederTest extends DemoTestCase
{
    public function test_trial_data_covers_every_workflow_state_and_role_pages_render(): void
    {
        Storage::fake('local');
        $this->seed(TrialSeeder::class);

        $this->assertSame(TrialSeeder::STUDENTS, User::where('email', 'like', 'trial%')->count());
        $states = [
            'club_applications' => ['submitted', 'accepted', 'waitlisted', 'revision', 'rejected'],
            'memberships' => ['active', 'inactive', 'left'],
            'registrations' => ['draft', 'program_review', 'submitted', 'accepted', 'revision', 'rejected'],
            'award_results' => ['pending', 'verified', 'rejected'],
            'certificates' => ['valid', 'revoked'],
            'delegations' => ['draft', 'submitted', 'completed'],
        ];
        foreach ($states as $table => $statuses) {
            $present = DB::table($table)->distinct()->pluck('status')->all();
            foreach ($statuses as $status) {
                $this->assertContains($status, $present, $table);
            }
        }
        $this->assertTrue(Registration::where('payment_status', 'pending')->exists());
        $this->assertTrue(Registration::where('payment_status', 'paid')->exists());
        $this->assertTrue(Participant::where('status', 'invited')->exists());
        $this->assertSame(3, DB::table('competitions')->whereNotNull('results_published_at')->count());
        $this->assertTrue(DB::table('certificate_versions')->where('version', 2)->exists());
        $poster = $this->competition('Lomba Poster Kesehatan');
        $this->assertNotSame([], app(ScoringService::class)->missing($poster), 'Penilaian poster sengaja belum lengkap.');
        $this->assertFalse($this->competition('Tenis Meja Tunggal')->matchups()->exists(), 'Bagan Tenis Meja disisakan untuk dicoba.');

        $winner = User::findOrFail(Certificate::where('status', 'valid')->orderBy('id')->value('user_id'));
        $chess = $this->competition('Catur Cepat');
        $pages = [
            'admin' => ['/tinjauan', '/pembayaran', '/prodi', '/kelola/prestasi', '/laporan', '/kelola/mahasiswa', '/kelola/sertifikat?lomba='.$this->competition('Cerdas Cermat Kesehatan')->id,
                '/kelola/lomba/'.$chess->id, '/kelola/lomba/'.$this->competition('Debat Bahasa Inggris')->id, '/delegasi', '/delegasi/'.Delegation::where('status', 'completed')->value('id')],
            'event' => ['/tinjauan', '/pembayaran', '/operator', '/kelola/lomba/'.$poster->id],
            'prodi' => ['/prodi'],
            'juri' => ['/penilaian', '/penilaian/'.$poster->id],
            'pimpinan' => ['/delegasi', '/laporan'],
            'pengurus' => ['/tinjauan', '/kelola/ukm/'.Club::where('name', 'Badminton')->value('id')],
        ];
        foreach ($pages as $role => $urls) {
            foreach ($urls as $url) {
                $this->assertSame(200, $this->actingAs($this->user($role))->get($url)->status(), $role.' '.$url);
            }
        }
        foreach (['/pendaftaran', '/ukm-saya', '/sertifikat', '/prestasi', '/notifikasi', '/live/'.$chess->id] as $url) {
            $this->assertSame(200, $this->actingAs($winner)->get($url)->status(), 'mahasiswa '.$url);
        }

        $this->expectExceptionMessage('Data trial sudah ada');
        $this->seed(TrialSeeder::class);
    }

    public function test_trial_seeder_refuses_to_run_outside_demo_mode(): void
    {
        config(['brudel.demo' => false]);
        $this->expectExceptionMessage('TrialSeeder hanya untuk');
        $this->seed(TrialSeeder::class);
    }
}
