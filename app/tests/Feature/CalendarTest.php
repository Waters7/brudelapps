<?php

namespace Tests\Feature;

use App\Models\ClubActivity;
use Illuminate\Support\Carbon;

/**
 * Month calendar on the front page and the dashboard: competition dates, visible UKM activities, tanggal merah.
 */
class CalendarTest extends DemoTestCase
{
    private function month(Carbon $moment): string
    {
        return $moment->copy()->setTimezone(config('brudel.timezone'))->format('Y-m');
    }

    public function test_front_page_calendar_shows_competitions_public_activities_and_holidays(): void
    {
        $poster = $this->competition('Lomba Poster Kesehatan');
        $this->get('/?kalender='.$this->month($poster->closes_at))->assertOk()
            ->assertSee('data-calendar', false)->assertSee('href="#kalender"', false)
            ->assertSee('Pendaftaran ditutup')->assertSee('Lomba Poster Kesehatan');

        $meeting = ClubActivity::where('title', 'like', 'Rapat evaluasi semester%')->firstOrFail();
        $public = ClubActivity::where('title', 'like', 'Persahabatan vs UKM Badminton Unsrat%')->firstOrFail();
        $this->get('/?kalender='.$this->month($meeting->starts_at))->assertDontSee('Rapat evaluasi semester');
        $this->get('/?kalender='.$this->month($public->starts_at))->assertSee('Persahabatan vs UKM Badminton Unsrat')->assertDontSee('href="/kegiatan/', false);

        $this->get('/?kalender=2026-08')->assertSee('Hari Kemerdekaan Republik Indonesia')->assertSee('Libur nasional');
        $this->get('/?kalender=2027-08')->assertSee('Hari Kemerdekaan Republik Indonesia', false);
        $this->get('/?kalender=2099-01')->assertViewHas('calendar', fn ($calendar) => $calendar['month']->format('Y-m') === now(config('brudel.timezone'))->format('Y-m'));
    }

    public function test_holidays_are_managed_in_master_data_and_the_dashboard_shows_what_the_user_may_see(): void
    {
        $this->actingAs($this->user('admin'))->get('/kelola/master/holiday')->assertOk()->assertSee('Hari libur (tanggal merah)')->assertSee('Hari Raya Natal');
        $this->post('/kelola/master/holiday', ['label' => 'Tanggal Salah', 'meta' => ['date' => '2027-02-30', 'kind' => 'nasional']])->assertSessionHasErrors('action');
        $this->post('/kelola/master/holiday', ['label' => 'Idul Fitri 1448 H', 'meta' => ['date' => '2027-03-10', 'kind' => 'nasional', 'yearly' => '0']])->assertSessionHasNoErrors();
        $this->post('/kelola/master/holiday', ['label' => 'Dies Natalis Poltekkes', 'meta' => ['date' => '2026-11-20', 'kind' => 'kampus', 'yearly' => '1']])->assertSessionHasNoErrors();

        $this->get('/?kalender=2027-03')->assertOk()->assertSee('Idul Fitri 1448 H');
        $this->get('/?kalender=2028-03')->assertDontSee('Idul Fitri 1448 H');
        $this->get('/?kalender=2027-11')->assertSee('Dies Natalis Poltekkes')->assertSee('Libur kampus');

        $meeting = ClubActivity::where('title', 'like', 'Rapat evaluasi semester%')->firstOrFail();
        $month = $this->month($meeting->starts_at);
        $this->actingAs($this->user('pembina'))->get('/?kalender='.$month)->assertSee('Rapat evaluasi semester')->assertSee('href="/kegiatan/'.$meeting->id.'"', false);
        $this->actingAs($this->user('mahasiswa'))->get('/?kalender='.$month)->assertOk()->assertDontSee('Rapat evaluasi semester');
    }
}
