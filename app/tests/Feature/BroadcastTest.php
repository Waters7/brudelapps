<?php

namespace Tests\Feature;

use App\Models\Broadcast;
use App\Models\Club;

/**
 * Pengumuman UKM from kemahasiswaan direktorat, kept apart from the announcements officers post in their space.
 */
class BroadcastTest extends DemoTestCase
{
    public function test_direktorat_announces_to_every_ukm_apart_from_officer_announcements(): void
    {
        $sports = Club::where('name', 'UKM Olahraga')->firstOrFail();
        $announcement = ['title' => 'Pendataan kegiatan semester genap', 'body' => 'Setiap UKM mengunggah rencana kegiatan paling lambat akhir bulan.', 'audience' => 'ukm'];

        $this->actingAs($this->user('jurusan'))->get('/kelola/pengumuman')->assertForbidden();
        $this->post('/kelola/pengumuman', $announcement)->assertForbidden();
        $this->actingAs($this->user('admin'))->get('/')->assertSee('href="/kelola/pengumuman"', false);
        $this->get('/kelola/pengumuman')->assertOk()->assertSee('Pilih organisasi');
        $this->post('/kelola/pengumuman', ['expires_at' => now()->subDay()->format('Y-m-d')] + $announcement)->assertSessionHasErrors('action');
        $this->post('/kelola/pengumuman', $announcement)->assertSessionHasNoErrors();

        $broadcast = Broadcast::where('title', 'Pendataan kegiatan semester genap')->firstOrFail();
        $this->assertSame(Club::where('active', true)->where('category', 'ukm')->count(), $broadcast->clubs()->count());
        $this->assertFalse($broadcast->clubs()->where('category', 'himaju')->exists());

        $athlete = $this->user('atlet1');
        $this->assertTrue($athlete->notifications()->get()->contains(fn ($n) => $n->data['title'] === 'Pengumuman Kemahasiswaan Direktorat' && $n->data['body'] === $broadcast->title));
        $this->actingAs($athlete)->get('/ruang/'.$sports->id)->assertOk()->assertSee('Kemahasiswaan Direktorat')->assertSee('Pendataan kegiatan semester genap');
        $this->get('/')->assertSee('Pendataan kegiatan semester genap');
        $this->get('/pengumuman/'.$broadcast->id)->assertOk()->assertSee('Setiap UKM mengunggah rencana kegiatan');

        $this->actingAs($this->user('pengurus'))->post('/ruang/'.$sports->id.'/pengumuman', ['title' => 'Latihan ditiadakan', 'body' => 'Latihan Kamis libur karena lapangan dipakai.'])->assertSessionHasNoErrors();
        $this->actingAs($athlete)->get('/ruang/'.$sports->id)->assertSee('Pengurus UKM Olahraga')->assertSee('Latihan ditiadakan');
        $this->assertTrue($athlete->notifications()->get()->contains(fn ($n) => $n->data['title'] === 'Pengumuman pengurus UKM Olahraga'));

        $this->actingAs($this->user('admin'))->post('/kelola/pengumuman/'.$broadcast->id.'/hapus')->assertSessionHasNoErrors();
        $this->actingAs($athlete)->get('/ruang/'.$sports->id)->assertDontSee('Pendataan kegiatan semester genap');
    }

    public function test_announcements_to_chosen_organizations_reach_only_their_members(): void
    {
        $nursing = Club::where('name', 'HIMAJU Keperawatan')->firstOrFail();
        $this->actingAs($this->user('admin'))->post('/kelola/pengumuman', ['title' => 'Rapat HIMAJU', 'body' => 'Rapat koordinasi pengurus HIMAJU.', 'audience' => 'pilihan'])
            ->assertSessionHasErrors('action');
        $this->post('/kelola/pengumuman', ['title' => 'Rapat HIMAJU', 'body' => 'Rapat koordinasi pengurus HIMAJU.', 'audience' => 'pilihan', 'clubs' => [$nursing->id]])
            ->assertSessionHasNoErrors();
        $broadcast = Broadcast::where('title', 'Rapat HIMAJU')->firstOrFail();
        $this->assertSame([$nursing->id], $broadcast->clubs()->pluck('clubs.id')->all());

        $this->actingAs($this->user('mahasiswa'))->get('/pengumuman/'.$broadcast->id)->assertOk();
        $this->actingAs($this->user('anggota'))->get('/pengumuman/'.$broadcast->id)->assertForbidden();
        $this->get('/')->assertDontSee('Rapat HIMAJU');
    }

    public function test_only_public_announcements_appear_on_the_sign_in_page(): void
    {
        $this->actingAs($this->user('admin'))->post('/kelola/pengumuman', ['title' => 'Libur semester ganjil', 'body' => 'Layanan kemahasiswaan tutup 24 Desember sampai 1 Januari.', 'audience' => 'semua', 'is_public' => '1'])
            ->assertSessionHasNoErrors();
        $this->post('/kelola/pengumuman', ['title' => 'Rapat pengurus internal', 'body' => 'Khusus pengurus organisasi.', 'audience' => 'semua', 'is_public' => '0'])->assertSessionHasNoErrors();
        $this->post('/logout');

        $this->get('/login')->assertOk()->assertSee('PENGUMUMAN KEMAHASISWAAN')->assertSee('Libur semester ganjil')->assertSee('Layanan kemahasiswaan tutup')
            ->assertDontSee('Rapat pengurus internal')->assertDontSee('href="/aktivasi"', false)->assertSee('data-password-toggle', false);
    }
}
