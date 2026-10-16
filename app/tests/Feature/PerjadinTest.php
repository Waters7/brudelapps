<?php

namespace Tests\Feature;

use App\Models\Delegation;
use App\Models\DelegationMember;

class PerjadinTest extends DemoTestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    private function delegation(array $overrides = []): Delegation
    {
        $delegation = Delegation::create($overrides + [
            'title' => 'Delegasi Baca Puisi Nasional', 'destination_event' => 'Festival Baca Puisi Nasional', 'organizer' => 'Penyelenggara nasional',
            'level' => 5, 'field' => 'Seni', 'starts_at' => now()->addMonth(), 'ends_at' => now()->addMonth()->addDays(3), 'location' => 'Jakarta',
            'reason' => 'Nilai tertinggi pada seleksi internal.', 'status' => 'approved', 'proposed_by' => $this->user('admin')->id,
            'decided_by' => $this->user('pimpinan')->id, 'decided_at' => now(),
        ]);
        DelegationMember::create(['delegation_id' => $delegation->id, 'user_id' => $this->user('mahasiswa')->id, 'role' => 'peserta']);
        DelegationMember::create(['delegation_id' => $delegation->id, 'user_id' => $this->user('pembina')->id, 'role' => 'pendamping']);

        return $delegation;
    }

    public function test_only_direktorat_opens_the_perjadin_menu(): void
    {
        $delegation = $this->delegation();
        foreach (['pimpinan', 'jurusan', 'pengurus', 'mahasiswa', 'event'] as $role) {
            $this->actingAs($this->user($role))->get('/kelola/perjadin')->assertForbidden();
            $this->get('/kelola/perjadin/'.$delegation->id.'/data.json')->assertForbidden();
        }
        $this->actingAs($this->user('admin'))->get('/')->assertSee('Perjadin Delegasi (Pangi)');
        $this->get('/delegasi/'.$delegation->id)->assertSee('/kelola/perjadin/'.$delegation->id, false);
    }

    public function test_travel_data_is_checked_and_exported_for_pangi(): void
    {
        $ready = $this->delegation();
        $this->delegation(['title' => 'Delegasi tanpa tanggal kembali', 'ends_at' => null]);
        $this->delegation(['title' => 'Delegasi menunggu pimpinan', 'status' => 'submitted', 'decided_by' => null, 'decided_at' => null]);

        $this->actingAs($this->user('admin'))->get('/kelola/perjadin')->assertOk()
            ->assertSee('Delegasi Baca Puisi Nasional')->assertDontSee('Delegasi menunggu pimpinan')->assertSee('Lengkap')->assertSee('Kurang: Tanggal berangkat dan kembali')->assertSee('belum terhubung');
        $this->get('/kelola/perjadin/'.$ready->id)->assertOk()
            ->assertSee('Bintang Mandagi')->assertSee('DEMO001')->assertSee('Keperawatan')->assertSee('Surat perintah perjalanan dinas (SPPD)')->assertSee('Menunggu integrasi Pangi')->assertSee('Dokumen pendaftaran lomba');

        $json = $this->get('/kelola/perjadin/'.$ready->id.'/data.json')->assertOk()->json();
        $this->assertSame('brudel.perjadin-mahasiswa.v1', $json['schema']);
        $this->assertTrue($json['ready']);
        $this->assertSame(['DEMO001', 'Keperawatan', 'Nasional'], [$json['participants'][0]['nim'], $json['participants'][0]['department'], $json['activity']['level']]);
        $this->assertSame('Pelatih Badminton', $json['companions'][0]['name']);
        $this->assertSame(['surat_tugas', 'sppd', 'dispensasi', 'laporan'], $json['letters']);
        $this->assertContains('keterangan_aktif', array_column($json['registration_documents'], 'code'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'perjadin.exported', 'subject_id' => $ready->id]);

        $csv = $this->get('/kelola/perjadin/ekspor')->assertOk()->streamedContent();
        $this->assertStringContainsString('DEMO001', $csv);
        $this->assertStringContainsString('Belum lengkap', $csv);

        config(['services.pangi' => ['base_url' => 'https://pangi.test', 'token' => 'token-uji']]);
        $this->get('/kelola/perjadin')->assertSee('alamat dan token Pangi sudah diisi');
    }
}
