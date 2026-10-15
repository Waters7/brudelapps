<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Delegation;
use App\Models\Event;
use App\Models\User;

class PageRenderTest extends DemoTestCase
{
    public function test_every_role_sees_its_pages_without_errors(): void
    {
        $club = Club::where('name', 'Badminton')->first();
        $event = Event::where('name', 'Pekan Prestasi Mahasiswa 2026 · Demo')->first();
        $tennis = $this->competition('Tenis Meja Tunggal');
        $poster = $this->competition('Lomba Poster Kesehatan');
        $common = ['/', '/ukm', '/lomba', '/live', '/live/'.$tennis->id, '/delegasi', '/notifikasi', '/rekomendasi', '/profil', '/ukm/'.$club->id, '/lomba/'.$tennis->id];
        $pages = [
            'mahasiswa' => ['/pendaftaran', '/ukm-saya', '/sertifikat', '/prestasi'],
            'pengurus' => ['/tinjauan', '/kelola/ukm', '/kelola/ukm/'.$club->id, '/kelola/event', '/kelola/event/'.Event::where('club_id', $club->id)->first()->id, '/laporan'],
            'admin' => ['/tinjauan', '/kelola/ukm', '/kelola/ukm/'.$club->id, '/kelola/event', '/kelola/event/'.$event->id, '/kelola/lomba/'.$tennis->id, '/kelola/lomba/'.$poster->id,
                '/jurusan', '/pembayaran', '/operator', '/kelola/sertifikat', '/kelola/sertifikat?lomba='.$tennis->id, '/kelola/prestasi', '/laporan', '/kelola/mahasiswa',
                '/kelola/penugasan', '/kelola/pengaturan', '/delegasi/baru', '/delegasi/baru?lomba='.$this->competition('Seleksi Baca Puisi')->id],
            'event' => ['/tinjauan', '/kelola/event', '/kelola/event/'.$event->id, '/kelola/lomba/'.$tennis->id, '/pembayaran', '/operator', '/kelola/sertifikat', '/laporan'],
            'jurusan' => ['/jurusan'],
            'juri' => ['/penilaian', '/penilaian/'.$poster->id],
            'operator' => ['/operator'],
            'pimpinan' => ['/laporan'],
        ];
        foreach ($pages as $role => $urls) {
            foreach (array_merge($common, $urls) as $url) {
                $response = $this->actingAs($this->user($role))->get($url);
                $this->assertSame(200, $response->status(), $role.' '.$url);
                $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
            }
        }
    }

    public function test_guests_see_only_public_pages(): void
    {
        $tennis = $this->competition('Tenis Meja Tunggal');
        $poster = $this->competition('Lomba Poster Kesehatan');
        $this->get('/live')->assertOk()->assertSee('Tenis Meja Tunggal')->assertDontSee('Lomba Poster Kesehatan');
        $this->get('/live/'.$tennis->id)->assertOk();
        $this->get('/live/'.$poster->id)->assertRedirect('/login');
        $this->get('/mahasiswa/bintang-mandagi-demo')->assertOk()->assertSee('Bintang Mandagi')->assertDontSee('DEMO001');
        foreach (['/aktivasi', '/lupa-sandi', '/login'] as $url) {
            $this->get($url)->assertOk();
        }
        foreach (['/pendaftaran', '/kelola/ukm', '/laporan', '/sertifikat'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        User::where('public_slug', 'bintang-mandagi-demo')->update(['publication_consent' => false]);
        $this->get('/mahasiswa/bintang-mandagi-demo')->assertNotFound();
    }

    public function test_students_cannot_open_management_pages(): void
    {
        $this->actingAs($this->user('mahasiswa'));
        foreach (['/kelola/ukm', '/kelola/event', '/jurusan', '/pembayaran', '/operator', '/kelola/sertifikat', '/kelola/prestasi', '/laporan', '/kelola/mahasiswa', '/kelola/penugasan', '/kelola/pengaturan', '/delegasi/baru'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->assertSame(0, Delegation::count());
    }
}
