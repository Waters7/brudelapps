<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pengaturan › Pengguna Login and Pengaturan › Audit Log, for kemahasiswaan direktorat only.
 */
class SignedInUserTest extends DemoTestCase
{
    public function test_direktorat_sees_who_is_signed_in_and_every_sign_in_is_recorded(): void
    {
        $student = $this->user('mahasiswa');
        $this->post('/login', ['email' => $student->nim, 'password' => 'BrudelDemo!2026'], ['User-Agent' => 'Mozilla/5.0 (Linux; Android 14) Chrome/130.0 Mobile'])->assertRedirect('/');
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login', 'user_id' => $student->id]);
        $this->post('/logout');

        $this->openSession($student, now()->subMinute(), 'Mozilla/5.0 (Linux; Android 14) Chrome/130.0 Mobile');
        $this->openSession($this->user('anggota'), now()->subMinutes((int) config('session.lifetime') + 5), 'Mozilla/5.0 (Windows NT 10.0) Firefox/131.0');
        $this->openSession($this->user('pengurus'), now()->subMinutes(30), 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0) Safari/604.1');

        $this->actingAs($this->user('jurusan'))->get('/kelola/pengguna')->assertForbidden();
        $this->actingAs($this->user('admin'))->get('/')->assertSee('href="/kelola/pengguna"', false)->assertSee('href="/kelola/audit"', false);
        $this->get('/kelola/pengguna')->assertOk()
            ->assertViewHas('signedIn', fn ($rows) => $rows->pluck('user.id')->sort()->values()->all() === collect([$student->id, $this->user('pengurus')->id])->sort()->values()->all())
            ->assertViewHas('online', 1)
            ->assertSee('Chrome · Android')->assertSee('Safari · iOS')->assertDontSee($this->user('anggota')->name);
        $this->get('/kelola/pengguna?cari='.$student->nim)->assertViewHas('signedIn', fn ($rows) => $rows->count() === 1)
            ->assertViewHas('history', fn ($page) => $page->total() === 1);
    }

    public function test_audit_log_is_grouped_by_the_assignment_held_at_that_moment(): void
    {
        $nursing = Department::where('code', 'KEP')->firstOrFail();
        $this->entry('admin', 'uji.direktorat', now()->subHour());
        $this->entry('jurusan', 'uji.jurusan', now()->subHour());
        $this->entry('jurusan', 'uji.sebelum_ditugaskan', now()->subYear());
        $this->entry('mahasiswa', 'uji.mahasiswa', now()->subHour());
        $this->entry(null, 'uji.sistem', now()->subHour());

        $this->actingAs($this->user('jurusan'))->get('/kelola/audit')->assertForbidden();
        $this->actingAs($this->user('admin'));
        $actions = fn (string $group) => collect($this->get('/kelola/audit?kelompok='.$group)->assertOk()->viewData('entries')->items())
            ->pluck('action')->filter(fn ($action) => str_starts_with($action, 'uji.'))->values()->all();

        $this->assertSame(['uji.direktorat'], $actions('kemahasiswaan'));
        $this->assertSame(['uji.jurusan'], $actions('jurusan:'.$nursing->id));
        $this->assertSame(['uji.jurusan'], $actions('jurusan'));
        $this->assertSame(['uji.mahasiswa'], $actions('mahasiswa'));
        $this->assertSame(['uji.sistem'], $actions('sistem'));
        $this->assertContains('uji.sebelum_ditugaskan', collect($this->get('/kelola/audit')->viewData('entries')->items())->pluck('action'));

        $this->get('/kelola/audit?kelompok=jurusan:'.$nursing->id)->assertSee('Kemahasiswaan Jurusan Keperawatan')
            ->assertViewHas('counts', fn ($counts) => $counts['jurusan:'.$nursing->id] >= 1 && $counts['sistem'] >= 1);
        $this->entry('admin', 'role.granted', now()->subMinutes(5));
        $this->get('/kelola/audit?modul=role')->assertOk()->assertSee('Peran diberikan')->assertDontSee('uji.direktorat');
    }

    private function openSession(User $user, \DateTimeInterface $lastActivity, string $agent): void
    {
        DB::table('sessions')->insert(['id' => Str::random(40), 'user_id' => $user->id, 'ip_address' => '10.0.0.'.$user->id, 'user_agent' => $agent,
            'payload' => '', 'last_activity' => $lastActivity->getTimestamp()]);
    }

    private function entry(?string $actor, string $action, \DateTimeInterface $at): void
    {
        $id = $actor ? $this->user($actor)->id : null;
        DB::table('audit_logs')->insert(['user_id' => $id, 'action' => $action, 'subject_type' => 'users', 'subject_id' => $id ?? 0, 'details' => json_encode(['catatan' => 'uji']), 'created_at' => $at]);
    }
}
