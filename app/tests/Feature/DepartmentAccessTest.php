<?php

namespace Tests\Feature;

use App\Models\AwardRecipient;
use App\Models\AwardResult;
use App\Models\Delegation;
use App\Models\DelegationMember;
use App\Models\Department;
use App\Models\User;

/**
 * Kemahasiswaan jurusan (demo: Keperawatan) gets the student, achievement, report, and delegation menus,
 * each limited to the students of its own jurusan.
 */
class DepartmentAccessTest extends DemoTestCase
{
    public function test_jurusan_manages_only_the_students_of_its_jurusan(): void
    {
        $own = $this->user('mahasiswa');
        $other = $this->user('anggota');
        $this->actingAs($this->user('jurusan'))->get('/')->assertSee('Mahasiswa Jurusan')->assertSee('Verifikasi Prestasi')->assertSee('href="/laporan"', false);

        $this->get('/kelola/mahasiswa')->assertOk()->assertSee($own->name)->assertDontSee($other->name)->assertDontSee('Impor CSV data resmi');
        $this->get('/kelola/mahasiswa?q='.urlencode($other->nim))->assertOk()->assertDontSee($other->name);
        $this->post('/kelola/mahasiswa/impor', ['file' => $this->pdf('mahasiswa.csv'), 'mode' => 'preview'])->assertForbidden();

        $this->post('/kelola/mahasiswa/'.$other->id.'/status', ['active' => 0, 'reason' => 'Uji lintas jurusan'])->assertForbidden();
        $this->post('/kelola/mahasiswa/'.$other->id.'/aktivasi')->assertForbidden();
        $this->post('/kelola/mahasiswa/'.$this->user('admin')->id.'/status', ['active' => 0, 'reason' => 'Bukan mahasiswa'])->assertForbidden();
        $this->post('/kelola/mahasiswa/'.$own->id.'/status', ['active' => 0, 'reason' => 'Cuti akademik'])->assertSessionHasNoErrors();
        $this->assertFalse($own->fresh()->active);
        $this->assertTrue($other->fresh()->active);
    }

    public function test_jurusan_verifies_achievements_submitted_by_its_own_students_only(): void
    {
        $own = $this->pendingAward('mahasiswa', 'Lomba Uji Keperawatan');
        $other = $this->pendingAward('anggota', 'Lomba Uji Gizi');

        $this->actingAs($this->user('jurusan'))->get('/kelola/prestasi')->assertOk()->assertSee('Lomba Uji Keperawatan')->assertDontSee('Lomba Uji Gizi');
        $this->post('/kelola/prestasi/'.$other->id, ['decision' => 'verified'])->assertForbidden();
        $this->post('/kelola/prestasi/'.$own->id, ['decision' => 'verified'])->assertSessionHasNoErrors();

        $this->assertSame('verified', $own->fresh()->status);
        $this->assertSame($this->user('jurusan')->id, $own->fresh()->verified_by);
        $this->assertSame('pending', $other->fresh()->status);
        $this->actingAs($this->user('admin'))->post('/kelola/prestasi/'.$other->id, ['decision' => 'verified'])->assertSessionHasNoErrors();
    }

    public function test_jurusan_reports_are_locked_to_its_jurusan(): void
    {
        $nursing = Department::where('code', 'KEP')->firstOrFail();
        $nutrition = Department::where('code', 'GIZ')->firstOrFail();
        $this->actingAs($this->user('jurusan'));

        $this->get('/laporan?department_id='.$nutrition->id)->assertOk()
            ->assertViewHas('filters', fn ($filters) => $filters['department_id'] === $nursing->id)
            ->assertViewHas('iku', fn ($iku) => $iku['scoped'] && collect($iku['rows'])->pluck('department.code')->all() === ['KEP'])
            ->assertViewHas('exports', fn ($exports) => ! isset($exports['pendaftaran']) && isset($exports['prestasi']))
            ->assertDontSee('Semua jurusan');
        $this->get('/laporan/ekspor/pendaftaran')->assertForbidden();

        $iku = $this->get('/laporan/ekspor/iku')->assertOk()->streamedContent();
        $this->assertStringContainsString('Keperawatan', $iku);
        $this->assertStringNotContainsString('Gizi dan Dietetika', $iku);
        $members = $this->get('/laporan/ekspor/keanggotaan')->assertOk()->streamedContent();
        $this->assertStringContainsString($this->user('mahasiswa')->name, $members);
        $this->assertStringNotContainsString($this->user('anggota')->name, $members);
    }

    public function test_jurusan_follows_delegations_of_its_students_read_only(): void
    {
        $own = $this->delegation('Delegasi Uji Keperawatan', 'mahasiswa');
        $other = $this->delegation('Delegasi Uji Gizi', 'anggota');

        $this->actingAs($this->user('jurusan'))->get('/delegasi')->assertOk()->assertSee('Delegasi Uji Keperawatan')->assertDontSee('Delegasi Uji Gizi');
        $this->get('/delegasi/'.$own->id)->assertOk()->assertViewHas('canManage', false);
        $this->get('/delegasi/'.$other->id)->assertForbidden();
        $this->post('/delegasi/'.$own->id.'/ajukan')->assertForbidden();
    }

    public function test_kemahasiswaan_jurusan_is_chosen_per_jurusan_and_staff_accounts_are_created_on_the_page(): void
    {
        $midwifery = Department::where('code', 'KEB')->firstOrFail();
        $nursing = Department::where('code', 'KEP')->firstOrFail();
        $this->actingAs($this->user('admin'))->get('/kelola/penugasan')->assertOk()
            ->assertSee('Kemahasiswaan Jurusan Kebidanan')->assertSee('value="jurusan:'.$nursing->id.'"', false)->assertSee('Akun staf baru');

        $staff = ['name' => 'Petugas Kemahasiswaan Kebidanan', 'username' => 'Kemahasiswaan-KEB', 'password' => 'SandiAwal2026', 'password_confirmation' => 'SandiAwal2026',
            'role' => 'jurusan:'.$midwifery->id, 'ends_at' => now()->addYear()->format('Y-m-d H:i')];
        $this->actingAs($this->user('jurusan'))->post('/kelola/penugasan/akun-staf', $staff)->assertForbidden();
        $this->actingAs($this->user('admin'))->post('/kelola/penugasan/akun-staf', ['role' => 'pengurus'] + $staff)->assertSessionHasErrors('role');
        $this->post('/kelola/penugasan/akun-staf', $staff)->assertSessionHasNoErrors();
        $this->post('/kelola/penugasan/akun-staf', $staff)->assertSessionHasErrors('username');

        $account = User::where('username', 'kemahasiswaan-keb')->firstOrFail();
        $this->assertTrue($account->must_change_password);
        $this->assertFalse($account->isStudentAffairs());
        $this->assertSame([$midwifery->id], $account->scopeIds('jurusan', 'department_id')->all());
        $this->get('/kelola/penugasan?role=jurusan:'.$midwifery->id)->assertViewHas('assignments', fn ($page) => $page->pluck('user_id')->all() === [$account->id]);

        $this->post('/kelola/penugasan', ['user' => 'kemahasiswaan-keb', 'role' => 'jurusan:'.$nursing->id, 'starts_at' => now()->format('Y-m-d H:i'), 'ends_at' => now()->addYear()->format('Y-m-d H:i')])
            ->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing([$midwifery->id, $nursing->id], $account->fresh()->scopeIds('jurusan', 'department_id')->all());

        $this->post('/logout');
        $this->post('/login', ['email' => 'kemahasiswaan-keb', 'password' => 'SandiAwal2026'])->assertRedirect('/sandi-baru');
        $this->post('/sandi-baru', ['email' => 'kemahasiswaan.keb@poltekkes.test', 'phone' => '081355550001', 'password' => 'SandiAwal2026', 'password_confirmation' => 'SandiAwal2026'])
            ->assertSessionHasErrors('action');
        $this->post('/sandi-baru', ['email' => 'kemahasiswaan.keb@poltekkes.test', 'phone' => '081355550001', 'password' => 'SandiBaru2026x', 'password_confirmation' => 'SandiBaru2026x'])->assertRedirect('/');
        $this->get('/')->assertOk()->assertSee('Kemahasiswaan Jurusan Kebidanan');
        $this->get('/kelola/mahasiswa')->assertOk()->assertSee($this->user('mahasiswa')->name)->assertDontSee($this->user('anggota')->name);
    }

    private function pendingAward(string $student, string $title): AwardResult
    {
        $user = $this->user($student);
        $award = AwardResult::create(['source' => 'external', 'title' => $title, 'organizer' => 'Panitia Uji', 'field' => 'Akademik', 'level' => 3, 'award' => 'Juara 1',
            'awarded_on' => now()->subWeek(), 'status' => 'pending', 'submitted_by' => $user->id]);
        AwardRecipient::create(['award_result_id' => $award->id, 'user_id' => $user->id, 'team_role' => 'individu']);

        return $award;
    }

    private function delegation(string $title, string $student): Delegation
    {
        $delegation = Delegation::create(['title' => $title, 'destination_event' => 'Lomba Nasional Uji', 'organizer' => 'Panitia Uji', 'level' => 4, 'field' => 'Akademik',
            'starts_at' => now()->addMonth(), 'location' => 'Manado', 'reason' => 'Delegasi uji coba hak akses jurusan.', 'proposed_by' => $this->user('admin')->id]);
        DelegationMember::create(['delegation_id' => $delegation->id, 'user_id' => User::where('email', $student.'@demo.brudel.test')->value('id'), 'role' => 'peserta']);

        return $delegation;
    }
}
