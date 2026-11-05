<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubActivity;
use App\Models\Companion;

class CompanionTest extends DemoTestCase
{
    private function activity(): ClubActivity
    {
        return ClubActivity::where('club_id', Club::where('name', 'UKM Olahraga')->value('id'))->confirmed()->firstOrFail();
    }

    public function test_those_running_an_activity_record_its_companions(): void
    {
        $activity = $this->activity();
        $url = '/kegiatan/'.$activity->id;
        $data = ['name' => 'Dr. Lina Pendamping, M.Kes', 'kind' => 'dosen', 'identifier' => '198001012005'];

        $this->actingAs($this->user('atlet1'))->post($url.'/pendamping', $data)->assertForbidden();
        $this->actingAs($this->user('pengurus'))->post($url.'/pendamping', $data)->assertSessionHasNoErrors();
        $this->post($url.'/pendamping', ['name' => 'dr. lina pendamping, m.kes', 'kind' => 'tendik'])->assertSessionHasErrors('action');
        $this->post($url.'/pendamping', ['name' => 'Budi Tendik', 'kind' => 'mahasiswa'])->assertSessionHasErrors('kind');
        $this->get($url)->assertOk()->assertSee('Dr. Lina Pendamping, M.Kes')->assertSee('198001012005');
        $this->get($url.'/laporan')->assertOk()->assertSee('Dr. Lina Pendamping, M.Kes');

        $this->actingAs($this->user('atlet1'))->get($url)->assertOk()->assertSee('Dr. Lina Pendamping, M.Kes')->assertDontSee('198001012005');
        $companion = Companion::firstOrFail();
        $this->post('/pendamping/'.$companion->id.'/hapus')->assertForbidden();
        $this->actingAs($this->user('pengurus'))->post('/pendamping/'.$companion->id.'/hapus')->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('companions', ['id' => $companion->id]);
    }

    public function test_competition_companions_show_on_the_competition_page(): void
    {
        $competition = $this->competition('Tenis Meja Tunggal');
        $url = '/kelola/lomba/'.$competition->id.'/pendamping';

        $this->actingAs($this->user('mahasiswa'))->post($url, ['name' => 'Budi Tendik', 'kind' => 'tendik'])->assertForbidden();
        $this->actingAs($this->user('admin'))->post($url, ['name' => 'Budi Tendik', 'kind' => 'tendik', 'identifier' => '1990'])->assertSessionHasNoErrors();
        $this->get('/kelola/lomba/'.$competition->id)->assertOk()->assertSee('Budi Tendik');
        $this->actingAs($this->user('mahasiswa'))->get('/lomba/'.$competition->id)->assertOk()->assertSee('Budi Tendik (Tendik)');
    }
}
