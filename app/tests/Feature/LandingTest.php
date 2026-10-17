<?php

namespace Tests\Feature;

use App\Models\LandingSlide;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class LandingTest extends DemoTestCase
{
    public function test_front_page_shows_animated_default_banner_and_organizations(): void
    {
        $this->get('/')->assertOk()->assertSee('data-slider', false)->assertSee('lomba sedang membuka pendaftaran')
            ->assertSee('organisasi siap menyambutmu')->assertSee('HIMAJU Keperawatan')->assertSee('Info lomba mahasiswa');
    }

    public function test_direktorat_manages_video_and_image_slides(): void
    {
        Storage::fake('local');
        $this->actingAs($this->user('jurusan'))->get('/kelola/banner-depan')->assertForbidden();
        $this->actingAs($this->user('admin'))->get('/kelola/banner-depan')->assertOk()->assertSee('20 MB');

        $fake = UploadedFile::fake()->createWithContent('palsu.mp4', str_repeat("\0", 64))->mimeType('video/mp4');
        $this->post('/kelola/banner-depan', ['title' => 'Video palsu', 'media' => $fake])->assertSessionHasErrors('media');
        $video = UploadedFile::fake()->createWithContent('pekan.mp4', "\0\0\0\x18ftypmp42".str_repeat("\0", 64))->mimeType('video/mp4');
        $this->post('/kelola/banner-depan', ['title' => 'Pekan Prestasi 2026', 'caption' => 'Saksikan finalnya.', 'link_url' => 'javascript:alert(1)', 'media' => $video])->assertSessionHasErrors('action');
        $this->post('/kelola/banner-depan', ['title' => 'Pekan Prestasi 2026', 'caption' => 'Saksikan finalnya.', 'link_url' => '/live', 'link_label' => 'Tonton live', 'media' => $video])->assertSessionHasNoErrors();
        $this->post('/kelola/banner-depan', ['title' => 'Juara nasional', 'media' => UploadedFile::fake()->image('juara.jpg', 2000, 800)])->assertSessionHasNoErrors();
        [$clip, $photo] = LandingSlide::orderBy('id')->get()->all();
        $this->assertSame(['video', 'image'], [$clip->media_type, $photo->media_type]);
        $this->assertSame([1600, 640], array_slice(getimagesizefromstring(Storage::disk('local')->get($photo->media_path)), 0, 2));

        $this->post('/logout');
        $this->get('/')->assertSee('Pekan Prestasi 2026')->assertSee('Tonton live')->assertSee($clip->mediaUrl(), false)->assertDontSee('organisasi siap menyambutmu');
        $media = $this->get('/slide/'.$clip->id)->assertOk()->assertHeader('Content-Type', 'video/mp4');
        $this->assertStringContainsString('public', $media->headers->get('Cache-Control'));

        $this->actingAs($this->user('admin'))->post('/kelola/banner-depan/'.$clip->id, ['position' => 1, 'active' => 0])->assertSessionHasNoErrors();
        $this->post('/logout');
        $this->get('/')->assertDontSee('Pekan Prestasi 2026')->assertSee('Juara nasional');
        $this->get('/slide/'.$clip->id)->assertNotFound();

        $this->actingAs($this->user('admin'))->post('/kelola/banner-depan/'.$photo->id.'/hapus')->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($photo->media_path);
    }

    public function test_livewire_script_is_served_from_a_path_without_a_js_extension(): void
    {
        // The Docker nginx serves *.js URLs only from disk, so Livewire's script must not end in ".js".
        preg_match('#src="[^"]*(/aset/livewire\?id=[^"]+)"#', (string) $this->get('/')->assertOk()->getContent(), $script);
        $this->assertNotEmpty($script, 'Halaman memuat skrip Livewire dari /aset/livewire.');
        $this->assertStringNotContainsString('livewire.min.js', $script[0]);
        $this->assertStringContainsString('javascript', (string) $this->get($script[1])->assertOk()->headers->get('Content-Type'));
    }

    public function test_sign_in_shows_the_brudel_loader(): void
    {
        $this->get('/login')->assertOk()->assertSee('id="brand-loader"', false)->assertSee('action="/login" data-loader', false)->assertSee('Menyiapkan ruangmu');
        $this->get('/aktivasi')->assertSee('data-loader', false);
    }
}
