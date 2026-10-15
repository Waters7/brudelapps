<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Department;
use App\Models\Recruitment;

class OrganizationTest extends DemoTestCase
{
    public function test_official_organizations_are_seeded_by_category_and_listed_in_order(): void
    {
        $this->assertEquals(['lembaga' => 2, 'himaju' => 7, 'ukm' => 13], Club::selectRaw('category, count(*) as total')->groupBy('category')->pluck('total', 'category')->map(fn ($total) => (int) $total)->all());
        $this->assertSame(7, Club::where('category', 'himaju')->distinct()->count('department_id'));
        $this->assertSame(Department::where('code', 'TLM')->value('id'), Club::where('name', 'HIMAJU Teknologi Laboratorium Medis')->value('department_id'));
        $this->assertNull(Club::where('name', 'Badan Eksekutif Mahasiswa (BEM)')->value('field'));
        $this->assertSame('Kerohanian', Club::where('name', 'UKM Ikatan Mahasiswa Muslim Poltekkes Manado')->value('field'));

        $this->actingAs($this->user('mahasiswa'))->get('/ukm')->assertOk()->assertSeeInOrder([
            'Badan Eksekutif Mahasiswa (BEM)', 'Majelis Permusyawaratan Mahasiswa (MPM)', 'HIMAJU Keperawatan', 'HIMAJU Gizi dan Dietetika',
            'UKM Ikatan Duta', 'UKM Satgas Kegawatdaruratan Bencana', 'UKM Ikatan Mahasiswa Muslim Poltekkes Manado',
        ]);
    }

    public function test_himaju_accepts_only_its_own_jurusan_and_does_not_use_the_single_ukm_slot(): void
    {
        config(['brudel.multiple_clubs' => false]);
        $himaju = Club::where('name', 'HIMAJU Keperawatan')->first();
        $recruitment = Recruitment::create(['club_id' => $himaju->id, 'name' => 'Rekrutmen HIMAJU uji', 'opens_at' => now()->subDay(), 'closes_at' => now()->addWeek(), 'quota' => 50, 'requirements' => 'Mahasiswa Jurusan Keperawatan.']);
        $motivation = ['motivation' => 'Saya ingin aktif di himpunan jurusan saya sendiri.'];

        $this->actingAs($this->user('anggota'))->get('/ukm/'.$himaju->id)->assertOk()->assertSee('khusus mahasiswa Jurusan Keperawatan');
        $this->post('/rekrutmen/'.$recruitment->id, $motivation)->assertSessionHasErrors('action');
        $this->assertDatabaseMissing('club_applications', ['recruitment_id' => $recruitment->id]);

        $student = $this->user('mahasiswa');
        $this->assertTrue($student->memberships()->where('status', 'active')->exists(), 'Mahasiswa demo sudah aktif di satu UKM.');
        $this->actingAs($student)->post('/rekrutmen/'.$recruitment->id, $motivation)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('club_applications', ['recruitment_id' => $recruitment->id, 'user_id' => $student->id, 'status' => 'submitted']);
        $english = Recruitment::whereHas('club', fn ($q) => $q->where('name', 'UKM English Club'))->first();
        $this->post('/rekrutmen/'.$english->id, $motivation)->assertSessionHasErrors('action');
    }

    public function test_category_rules_for_creating_and_editing_organizations(): void
    {
        $base = ['description' => 'Organisasi uji.', 'mentor' => 'Pembina uji', 'contact' => 'kontak uji'];
        $this->actingAs($this->user('admin'));
        $this->post('/kelola/ukm', $base + ['name' => 'HIMAJU Tanpa Jurusan', 'category' => 'himaju'])->assertSessionHasErrors('action');
        $this->post('/kelola/ukm', $base + ['name' => 'HIMAJU Farmasi Kedua', 'category' => 'himaju', 'department_id' => Department::where('code', 'FAR')->value('id')])->assertSessionHasErrors('action');
        $this->post('/kelola/ukm', $base + ['name' => 'UKM Tanpa Bidang', 'category' => 'ukm'])->assertSessionHasErrors('action');
        $this->post('/kelola/ukm', $base + ['name' => 'UKM Catur', 'category' => 'ukm', 'field' => 'Olahraga', 'department_id' => Department::where('code', 'KEP')->value('id')])->assertRedirect();
        $this->assertDatabaseHas('clubs', ['name' => 'UKM Catur', 'category' => 'ukm', 'department_id' => null]);
        $this->assertSame(0, Club::whereIn('name', ['HIMAJU Tanpa Jurusan', 'HIMAJU Farmasi Kedua', 'UKM Tanpa Bidang'])->count());

        $club = Club::where('name', 'UKM Olahraga')->first();
        $this->actingAs($this->user('pengurus'))->post('/kelola/ukm/'.$club->id, $base + ['category' => 'lembaga', 'field' => 'Olahraga'])->assertSessionHasNoErrors();
        $this->assertSame('ukm', $club->fresh()->category, 'Pengurus tidak dapat mengubah kategori.');
        $this->post('/kelola/ukm/'.$club->id, $base + ['field' => ''])->assertSessionHasErrors('action');
    }
}
