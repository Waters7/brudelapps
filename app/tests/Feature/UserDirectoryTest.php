<?php

namespace Tests\Feature;

use App\Models\CompetitionAccount;
use App\Models\Department;
use App\Models\User;

/**
 * Master Data > Pengguna per Jurusan.
 */
class UserDirectoryTest extends DemoTestCase
{
    private function jurusan(string $code): Department
    {
        return Department::where('code', $code)->firstOrFail();
    }

    public function test_only_direktorat_opens_the_directory(): void
    {
        foreach (['jurusan', 'pengurus', 'mahasiswa'] as $role) {
            $this->actingAs($this->user($role))->get('/kelola/master/pengguna')->assertForbidden();
        }
    }

    public function test_students_are_grouped_by_the_jurusan_of_their_program(): void
    {
        $nursing = $this->jurusan('KEP');
        $expected = User::whereNotNull('nim')->whereHas('program', fn ($q) => $q->where('department_id', $nursing->id))->count();

        $this->actingAs($this->user('admin'))->get('/kelola/master/pengguna?jurusan='.$nursing->id)->assertOk()
            ->assertSee('Bintang Mandagi')->assertDontSee('Michelle Sumual')
            ->assertViewHas('groups', fn ($groups) => $groups->firstWhere('key', $nursing->id)['total'] === $expected && $groups->firstWhere('key', $nursing->id)['selected']);
        $this->get('/kelola/master/pengguna?jurusan='.$this->jurusan('GIZ')->id)->assertOk()->assertSee('Michelle Sumual')->assertDontSee('Bintang Mandagi');
    }

    public function test_ketua_can_be_filtered_and_shows_the_organization(): void
    {
        $this->actingAs($this->user('admin'))->get('/kelola/master/pengguna?status=pengurus&jurusan='.$this->jurusan('KEP')->id)->assertOk()
            ->assertSee('Andre Runtuwene')->assertSee('Ketua UKM Olahraga')->assertDontSee('Bintang Mandagi')
            ->assertViewHas('groups', fn ($groups) => $groups->firstWhere('key', $this->jurusan('KEP')->id)['officers'] === 1);
    }

    public function test_search_and_sign_in_status_narrow_the_list(): void
    {
        $url = '/kelola/master/pengguna?jurusan='.$this->jurusan('KEP')->id;
        $this->actingAs($this->user('admin'))->get($url.'&q=DEMO001')->assertSee('Bintang Mandagi')->assertDontSee('Andre Runtuwene');

        $this->user('mahasiswa')->forceFill(['email_verified_at' => null])->save();
        $this->get($url.'&status=belum')->assertSee('Bintang Mandagi')->assertDontSee('Andre Runtuwene');
        $this->get($url.'&status=sudah')->assertSee('Andre Runtuwene')->assertDontSee('Bintang Mandagi');
    }

    public function test_staff_form_their_own_group_without_competition_accounts(): void
    {
        $competitionUser = CompetitionAccount::with('user')->firstOrFail()->user;
        $this->actingAs($this->user('admin'))->get('/kelola/master/pengguna?jurusan=lainnya')->assertOk()
            ->assertSee('Kemahasiswaan Jurusan Keperawatan')->assertDontSee('Bintang Mandagi')->assertDontSee($competitionUser->email);
    }
}
