<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Club;
use App\Models\ClubActivity;
use App\Models\MasterOption;
use App\Services\Campus\MasterData;

class MasterDataTest extends DemoTestCase
{
    private function option(string $type, string $label): MasterOption
    {
        return MasterOption::where('type', $type)->where('label', $label)->firstOrFail();
    }

    public function test_only_direktorat_opens_master_data_with_a_sub_menu_per_list(): void
    {
        foreach (['jurusan', 'pengurus', 'pimpinan'] as $role) {
            $this->actingAs($this->user($role))->get('/kelola/master')->assertForbidden();
            $this->post('/kelola/master/field', ['label' => 'Coba'])->assertForbidden();
        }
        $page = $this->actingAs($this->user('admin'))->get('/kelola/master')->assertOk();
        foreach (MasterData::TYPES as $type => $spec) {
            $page->assertSee($spec['label'])->assertSee('/kelola/master/'.$type, false);
            $this->get('/kelola/master/'.$type)->assertOk()->assertSee($spec['label']);
        }
        $page->assertSee('Jurusan & program studi')->assertSee('Tingkat prestasi (IKU)');
        $this->get('/')->assertSee('MASTER DATA')->assertSee('Pengaturan Sistem');
        $this->get('/kelola/master/tidak-ada')->assertNotFound();
    }

    public function test_bidang_can_be_added_renamed_deactivated_and_protected_while_used(): void
    {
        $this->actingAs($this->user('admin'))->post('/kelola/master/field', ['label' => 'Kesehatan', 'meta' => ['single_choice' => '0']])->assertSessionHasNoErrors();
        $this->post('/kelola/master/field', ['label' => 'kesehatan'])->assertSessionHasErrors('action');
        $this->post('/kelola/master/field', ['label' => 'Olahraga, Seni'])->assertSessionHasErrors('action');
        $this->get('/lomba')->assertSee('Kesehatan');

        $sosial = $this->option('field', 'Sosial');
        $this->post('/kelola/master/field/'.$sosial->id, ['label' => 'Sosial & Kemanusiaan', 'position' => 5, 'active' => 1, 'meta' => ['single_choice' => '0']])->assertSessionHasNoErrors();
        $this->assertSame('Sosial & Kemanusiaan', Club::where('name', 'UKM Korps Sukarela PMI')->value('field'), 'Nama baru mengikuti data yang sudah ada.');
        $this->post('/kelola/master/field/'.$sosial->id.'/hapus')->assertSessionHasErrors('action');

        $seni = $this->option('field', 'Seni');
        $this->post('/kelola/master/field/'.$seni->id, ['label' => 'Seni', 'position' => 2, 'active' => 1, 'meta' => ['single_choice' => '1']])->assertSessionHasNoErrors();
        $this->assertContains('Seni', app(MasterData::class)->singleChoiceFields());

        $health = $this->option('field', 'Kesehatan');
        $this->post('/kelola/master/field/'.$health->id, ['label' => 'Kesehatan', 'active' => 0])->assertSessionHasNoErrors();
        $this->assertNotContains('Kesehatan', app(MasterData::class)->fields());
        $this->assertContains('Kesehatan', app(MasterData::class)->allFields());
        $this->post('/kelola/master/field/'.$health->id.'/hapus')->assertSessionHasNoErrors();
        $this->assertModelMissing($health);
        $this->assertDatabaseHas('audit_logs', ['action' => 'master.deleted']);
    }

    public function test_activity_types_positions_rooms_and_templates_feed_the_forms(): void
    {
        $this->actingAs($this->user('admin'))->post('/kelola/master/activity_type', ['label' => 'Rapat kerja', 'meta' => ['report' => 'minutes', 'internal_only' => '1']])->assertSessionHasNoErrors();
        $this->post('/kelola/master/officer_position', ['label' => 'Koordinator Lapangan', 'meta' => ['chair' => '0', 'manager' => '1']])->assertSessionHasNoErrors();
        $this->post('/kelola/master/location', ['label' => 'Aula Gedung Rektorat', 'meta' => ['kind' => 'aula', 'capacity' => '400', 'permit' => '1']])->assertSessionHasNoErrors();
        $this->post('/kelola/master/location', ['label' => 'Lapangan tanpa jenis', 'meta' => ['kind' => 'tidak-ada']])->assertSessionHasErrors('action');
        $this->post('/kelola/master/registration_document', ['label' => 'Pas foto 3x4', 'meta' => ['per_student' => '1']])->assertSessionHasNoErrors();

        $club = Club::where('name', 'UKM Olahraga')->first();
        $monday = now(config('brudel.timezone'))->next('Monday')->setTime(9, 0);
        $this->actingAs($this->user('pengurus'))->get('/kegiatan')->assertSee('Rapat kerja')->assertSee('Aula Gedung Rektorat');
        $this->post('/kegiatan', ['club_id' => $club->id, 'type' => 'rapat_kerja', 'title' => 'Rapat kerja tahunan', 'location' => 'Ruang seminar', 'is_public' => 1,
            'starts_at' => $monday->format('Y-m-d\TH:i'), 'ends_at' => $monday->copy()->addHours(2)->format('Y-m-d\TH:i')])->assertRedirect();
        $activity = ClubActivity::where('title', 'Rapat kerja tahunan')->firstOrFail();
        $this->assertFalse($activity->is_public, 'Jenis internal selalu tertutup.');
        $this->assertSame(['Rapat kerja', 'minutes'], [$activity->typeLabel(), $activity->reportKind()]);
        $this->travelTo($monday->copy()->addHours(3));
        $this->post('/kegiatan/'.$activity->id.'/laporan', ['notes' => 'Program kerja disepakati.'])->assertSessionHasErrors('action');

        $window = ['starts_at' => now()->format('Y-m-d\TH:i'), 'ends_at' => now()->addYear()->format('Y-m-d\TH:i')];
        $this->post('/kelola/penugasan', ['user' => 'DEMO004', 'role' => 'pengurus', 'scope_id' => $club->id, 'position' => 'Koordinator Lapangan'] + $window)->assertSessionHasNoErrors();
        $this->assertTrue($this->user('atlet1')->managesOfficers($club->id));

        $treasurer = $this->option('officer_position', 'Bendahara');
        Assignment::create(['user_id' => $this->user('atlet2')->id, 'role' => 'pengurus', 'club_id' => $club->id, 'position' => 'Bendahara', 'starts_at' => now(), 'ends_at' => now()->addYear()]);
        $this->actingAs($this->user('admin'))->post('/kelola/master/officer_position/'.$treasurer->id, ['label' => 'Bendahara Umum', 'active' => 1, 'meta' => ['chair' => '0', 'manager' => '0']])->assertSessionHasNoErrors();
        $this->assertTrue(Assignment::where('position', 'Bendahara Umum')->exists());
        $chair = $this->option('officer_position', 'Ketua');
        foreach (['Ketua Putra', 'Ketua Putri'] as $other) {
            $o = $this->option('officer_position', $other);
            $this->post('/kelola/master/officer_position/'.$o->id, ['label' => $other, 'active' => 1, 'meta' => ['chair' => '0', 'manager' => '1']])->assertSessionHasNoErrors();
        }
        $this->post('/kelola/master/officer_position/'.$chair->id, ['label' => 'Ketua', 'active' => 1, 'meta' => ['chair' => '0', 'manager' => '1']])->assertSessionHasErrors('action');

        $event = $this->competition('Badminton Tunggal')->event;
        $this->get('/kelola/event/'.$event->id)->assertSee('Jenis lomba (mengisi form otomatis)')->assertSee('Karya tulis ilmiah');
        $this->actingAs($this->user('mahasiswa'))->get('/prestasi')->assertSee('award-options', false)->assertSee('Juara Harapan 1');
    }
}
