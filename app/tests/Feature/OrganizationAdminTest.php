<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Department;
use App\Models\Membership;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class OrganizationAdminTest extends DemoTestCase
{
    private function himaju(string $code): Club
    {
        return Club::where('category', 'himaju')->where('department_id', Department::where('code', $code)->value('id'))->firstOrFail();
    }

    public function test_officers_upload_a_card_banner_that_is_reencoded_and_shown_in_the_catalog(): void
    {
        Storage::fake('local');
        $club = Club::where('name', 'UKM Olahraga')->first();
        $this->actingAs($this->user('mahasiswa'))->post('/kelola/ukm/'.$club->id.'/banner', ['banner' => UploadedFile::fake()->image('b.png', 1600, 900)])->assertForbidden();

        $this->actingAs($this->user('pengurus'))->get('/kelola/ukm/'.$club->id)->assertOk()->assertSee('1200 × 480')->assertSee('/templates/banner-ukm-1200x480.png', false);
        $this->post('/kelola/ukm/'.$club->id.'/banner', ['banner' => UploadedFile::fake()->createWithContent('b.png', '<?php echo 1;')])->assertSessionHasErrors('banner');
        $this->post('/kelola/ukm/'.$club->id.'/banner', ['banner' => UploadedFile::fake()->image('kecil.png', 300, 100)])->assertSessionHasErrors('banner');
        $this->post('/kelola/ukm/'.$club->id.'/banner', ['banner' => UploadedFile::fake()->image('b.png', 1600, 900)])->assertSessionHasNoErrors();
        $club->refresh();
        $this->assertSame([1200, 480], array_slice(getimagesizefromstring(Storage::disk('local')->get($club->banner_path)), 0, 2));
        $this->assertFileExists(public_path('templates/banner-ukm-1200x480.png'));

        $this->get('/ukm')->assertSee('/banner/'.$club->id.'?v=', false);
        $this->post('/logout');
        $this->get('/banner/'.$club->id)->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        $old = $club->banner_path;
        $this->actingAs($this->user('pengurus'))->post('/kelola/ukm/'.$club->id.'/banner/hapus')->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($old);
        $this->get('/banner/'.$club->id)->assertNotFound();
    }

    public function test_officer_form_lists_only_active_members(): void
    {
        $club = Club::where('name', 'UKM Olahraga')->first();
        $this->actingAs($this->user('pengurus'))->get('/kelola/ukm/'.$club->id)->assertOk()
            ->assertSee('Rifky Lasut · DEMO004')->assertDontSee('Bintang Mandagi · DEMO001');
    }

    public function test_kemahasiswaan_jurusan_administers_only_the_himaju_of_its_jurusan(): void
    {
        $admin = $this->user('jurusan');
        $own = $this->himaju('KEP');
        $this->actingAs($admin)->get('/kelola/ukm')->assertOk()->assertSee('HIMAJU Keperawatan')->assertDontSee('HIMAJU Farmasi')->assertDontSee('UKM Olahraga');
        $this->get('/kelola/ukm/'.$own->id)->assertOk()->assertSee('Tambah pengurus');
        $this->get('/kelola/ukm/'.$this->himaju('FAR')->id)->assertForbidden();
        $this->get('/kelola/ukm/'.Club::where('name', 'UKM Olahraga')->value('id'))->assertForbidden();
        $this->post('/kelola/ukm/'.$own->id, ['name' => 'Diganti', 'category' => 'ukm', 'description' => 'Himpunan jurusan.', 'mentor' => 'Dosen pembina', 'contact' => 'himaju@kampus'])->assertSessionHasNoErrors();
        $own->refresh();
        $this->assertSame(['HIMAJU Keperawatan', 'himaju', 'Dosen pembina'], [$own->name, $own->category, $own->mentor], 'Nama dan kategori tetap milik direktorat.');

        $window = ['starts_at' => now()->format('Y-m-d\TH:i'), 'ends_at' => now()->addYear()->format('Y-m-d\TH:i')];
        $this->post('/kelola/penugasan', ['user' => 'DEMO001', 'role' => 'pengurus', 'scope_id' => $own->id, 'position' => 'Ketua'] + $window)->assertSessionHasNoErrors();
        $this->assertTrue($this->user('mahasiswa')->managesOfficers($own->id));
        $this->post('/kelola/penugasan', ['user' => 'DEMO004', 'role' => 'pengurus', 'scope_id' => Club::where('name', 'UKM Olahraga')->value('id')] + $window)->assertForbidden();
        $this->get('/kelola/pengaturan')->assertForbidden();
        $this->get('/kelola/penugasan')->assertForbidden();
        $this->assertSame('Kemahasiswaan Jurusan', config('brudel.roles.jurusan.label'));
    }

    public function test_judges_see_why_a_score_sheet_cannot_be_filled_yet(): void
    {
        $poster = $this->competition('Lomba Poster Kesehatan');
        $this->actingAs($this->user('juri'))->get('/penilaian')->assertOk()->assertSee('Belum ada peserta diterima');
        $this->get('/penilaian/'.$poster->id)->assertOk()->assertSee('Belum ada peserta yang diterima');
        $poster->criteria()->delete();
        $this->get('/penilaian')->assertSee('Menunggu rubrik dari pengelola lomba');
        $this->get('/penilaian/'.$poster->id)->assertSee('rubrik belum disusun oleh pengelola lomba');

        $debate = $this->competition('Debat Bahasa Inggris');
        $judge = $debate->accounts()->where('role', 'penilai')->first()->user;
        $this->assertSame(3, $debate->criteria()->count(), 'Juri otomatis Debat dapat langsung menilai di data demo.');
        $this->actingAs($judge)->get('/penilaian')->assertOk()->assertSee($debate->name);
        $this->assertFalse(Membership::where('user_id', $judge->id)->exists());
    }
}
