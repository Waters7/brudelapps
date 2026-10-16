<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubActivity;
use App\Models\Department;
use Carbon\CarbonInterface;

class ActivityPermitTest extends DemoTestCase
{
    /**
     * @return array<string, mixed>
     */
    private function activity(Club $club, CarbonInterface $starts, string $title, array $extra = []): array
    {
        return ['club_id' => $club->id, 'type' => 'latihan', 'title' => $title, 'location' => 'Kampus', 'is_public' => 1,
            'starts_at' => $starts->format('Y-m-d\TH:i'), 'ends_at' => $starts->copy()->addHours(2)->format('Y-m-d\TH:i')] + $extra;
    }

    private function wita(string $day, int $hour): CarbonInterface
    {
        return now(config('brudel.timezone'))->next($day)->setTime($hour, 0);
    }

    public function test_activities_in_campus_hours_publish_at_once_and_any_officer_may_create_them(): void
    {
        $club = Club::where('name', 'UKM Olahraga')->first();
        $this->actingAs($this->user('pengurus'))->post('/kelola/penugasan', ['user' => 'DEMO004', 'role' => 'pengurus', 'scope_id' => $club->id, 'position' => 'Bendahara',
            'starts_at' => now()->format('Y-m-d\TH:i'), 'ends_at' => now()->addYear()->format('Y-m-d\TH:i')])->assertSessionHasNoErrors();

        $this->actingAs($this->user('atlet1'))->post('/kegiatan', $this->activity($club, $this->wita('Tuesday', 9), 'Latihan Selasa pagi'))->assertRedirect();
        $activity = ClubActivity::where('title', 'Latihan Selasa pagi')->first();
        $this->assertNull($activity->permit_status);
        $this->assertTrue($this->user('atlet2')->notifications()->get()->contains(fn ($n) => str_contains($n->data['body'], 'Latihan Selasa pagi')));
    }

    public function test_room_or_after_hours_activity_waits_for_the_right_kemahasiswaan(): void
    {
        $club = Club::where('name', 'UKM Olahraga')->first();
        $this->actingAs($this->user('pengurus'))->post('/kegiatan', $this->activity($club, $this->wita('Wednesday', 9), 'Latihan di aula', ['needs_room' => 1]))->assertSessionHasErrors('action');
        $this->post('/kegiatan', $this->activity($club, $this->wita('Wednesday', 9), 'Latihan di aula', ['needs_room' => 1, 'room' => 'Aula Gedung A']))->assertRedirect();
        $this->post('/kegiatan', $this->activity($club, $this->wita('Saturday', 19), 'Latihan malam minggu'))->assertRedirect();
        $room = ClubActivity::where('title', 'Latihan di aula')->first();
        $night = ClubActivity::where('title', 'Latihan malam minggu')->first();
        $this->assertSame(['pending', 'direktorat', 'Peminjaman ruangan'], [$room->permit_status, $room->permit_level, $room->permit_reasons]);
        $this->assertSame('Di luar jam operasional kampus', $night->permit_reasons);

        $this->assertFalse($this->user('atlet1')->notifications()->get()->contains(fn ($n) => str_contains($n->data['body'], 'Latihan di aula')));
        $this->actingAs($this->user('atlet1'))->get('/kegiatan/'.$room->id)->assertForbidden();
        $this->get('/kegiatan')->assertDontSee('Latihan di aula');
        $this->actingAs($this->user('pembina'))->post('/kegiatan/'.$room->id.'/kode')->assertSessionHasErrors('action');
        $this->post('/kegiatan/'.$room->id.'/izin', ['decision' => 'approved'])->assertForbidden();
        $this->actingAs($this->user('jurusan'))->post('/kegiatan/'.$room->id.'/izin', ['decision' => 'approved'])->assertForbidden();

        $admin = $this->user('admin');
        $this->assertTrue($admin->notifications()->get()->contains(fn ($n) => str_contains($n->data['body'], 'Latihan di aula')));
        $this->actingAs($admin)->get('/kegiatan/izin')->assertOk()->assertSee('Latihan di aula')->assertSee('Aula Gedung A');
        $this->post('/kegiatan/'.$night->id.'/izin', ['decision' => 'rejected'])->assertSessionHasErrors('action');
        $this->post('/kegiatan/'.$night->id.'/izin', ['decision' => 'rejected', 'note' => 'Kampus tutup pada malam hari'])->assertSessionHasNoErrors();
        $this->post('/kegiatan/'.$room->id.'/izin', ['decision' => 'approved'])->assertSessionHasNoErrors();
        $this->assertSame(['rejected', 'cancelled'], [$night->fresh()->permit_status, $night->fresh()->status]);
        $this->assertTrue($this->user('atlet1')->notifications()->get()->contains(fn ($n) => str_contains($n->data['body'], 'Latihan di aula')));
        $this->actingAs($this->user('atlet1'))->get('/kegiatan/'.$room->id)->assertOk();
    }

    public function test_himaju_permits_belong_to_kemahasiswaan_jurusan(): void
    {
        $himaju = Club::where('category', 'himaju')->where('department_id', Department::where('code', 'KEP')->value('id'))->first();
        $this->actingAs($this->user('jurusan'))->post('/kelola/penugasan', ['user' => 'DEMO001', 'role' => 'pengurus', 'scope_id' => $himaju->id, 'position' => 'Ketua',
            'starts_at' => now()->format('Y-m-d\TH:i'), 'ends_at' => now()->addYear()->format('Y-m-d\TH:i')])->assertSessionHasNoErrors();
        $this->actingAs($this->user('mahasiswa'))->post('/kegiatan', $this->activity($himaju, $this->wita('Sunday', 8), 'Bakti sosial HIMAJU'))->assertRedirect();
        $activity = ClubActivity::where('title', 'Bakti sosial HIMAJU')->first();
        $this->assertSame('jurusan', $activity->permit_level);

        $this->actingAs($this->user('admin'))->post('/kegiatan/'.$activity->id.'/izin', ['decision' => 'approved'])->assertForbidden();
        $this->actingAs($this->user('jurusan'))->get('/')->assertSee('Izin kegiatan menunggu');
        $this->get('/kegiatan/izin')->assertOk()->assertSee('Bakti sosial HIMAJU');
        $this->post('/kegiatan/'.$activity->id.'/izin', ['decision' => 'approved', 'note' => 'Koordinasikan dengan satpam'])->assertSessionHasNoErrors();
        $this->assertSame('approved', $activity->fresh()->permit_status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'activity.permit_approved', 'subject_id' => $activity->id]);
    }

    public function test_operating_hours_come_from_campus_settings(): void
    {
        $this->actingAs($this->user('admin'))->post('/kelola/pengaturan', [
            'regional_definition' => 'Regional: lintas provinsi.', 'refund_policy' => 'Belum ada pengembalian dana.', 'certificate_prefix' => 'BRUDEL',
            'operating_days' => [1, 2, 3, 4, 5, 6], 'operating_open' => '07:00', 'operating_close' => '21:00',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('audit_logs', ['action' => 'setting.updated', 'subject_type' => 'settings', 'subject_id' => null]);
        $club = Club::where('name', 'UKM Olahraga')->first();
        $this->actingAs($this->user('pengurus'))->post('/kegiatan', $this->activity($club, $this->wita('Saturday', 18), 'Latihan Sabtu sore'))->assertRedirect();
        $this->assertNull(ClubActivity::where('title', 'Latihan Sabtu sore')->value('permit_status'));
    }
}
