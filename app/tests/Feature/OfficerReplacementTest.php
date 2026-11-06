<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Club;
use App\Models\Department;
use App\Models\Membership;
use App\Models\MembershipRequest;
use App\Models\User;
use App\Services\Wita;

/**
 * Replacing a ketua or officer in one step, and officers of one organization joining another.
 */
class OfficerReplacementTest extends DemoTestCase
{
    private function sports(): Club
    {
        return Club::where('name', 'UKM Olahraga')->firstOrFail();
    }

    private function himaju(): Club
    {
        return Club::where('category', 'himaju')->where('department_id', Department::where('code', 'KEP')->value('id'))->firstOrFail();
    }

    /**
     * @return array<string, string>
     */
    private function form(string $user, string $position = 'Ketua'): array
    {
        return ['user' => $user, 'position' => $position, 'period' => '2027/2028', 'ends_at' => Wita::input(now()->addYear()), 'reason' => 'Hasil musyawarah anggota'];
    }

    private function holder(Club $club, string $position): ?int
    {
        return Assignment::where('role', 'pengurus')->where('club_id', $club->id)->where('position', $position)->where('ends_at', '>', now())->value('user_id');
    }

    public function test_the_super_admin_replaces_a_ketua_in_one_step(): void
    {
        $club = $this->sports();
        $old = $this->user('pengurus');
        $new = $this->user('atlet1');

        $this->actingAs($this->user('admin'))->get('/kelola/ukm/'.$club->id)->assertOk()->assertSee('Ganti ketua atau pengurus');
        $this->post('/kelola/ukm/'.$club->id.'/ganti-pengurus', $this->form($new->nim))->assertSessionHasNoErrors();

        $this->assertSame($new->id, $this->holder($club, 'Ketua'));
        $this->assertFalse(User::find($old->id)->assigned('pengurus', $club->id));
        $this->assertTrue(User::find($new->id)->assigned('pengurus', $club->id));
        $this->assertSame('Hasil musyawarah anggota', Assignment::where('user_id', $old->id)->where('club_id', $club->id)->latest('id')->value('ended_reason'));
        $this->assertTrue($new->notifications()->get()->contains(fn ($n) => $n->data['title'] === 'Penugasan baru'));
        $this->assertTrue(Membership::where('club_id', $club->id)->where('user_id', $old->id)->where('status', 'active')->exists(), 'Keanggotaan tidak ikut berakhir.');
    }

    public function test_an_officer_can_be_promoted_without_holding_two_positions(): void
    {
        $club = $this->sports();
        $new = $this->user('atlet1');
        Assignment::create(['user_id' => $new->id, 'role' => 'pengurus', 'club_id' => $club->id, 'position' => 'Sekretaris', 'starts_at' => now()->subMonth(), 'ends_at' => now()->addYear()]);

        $this->actingAs($this->user('admin'))->post('/kelola/ukm/'.$club->id.'/ganti-pengurus', $this->form($new->nim))->assertSessionHasNoErrors();

        $this->assertSame($new->id, $this->holder($club, 'Ketua'));
        $this->assertNull($this->holder($club, 'Sekretaris'));
        $this->post('/kelola/ukm/'.$club->id.'/ganti-pengurus', $this->form($new->nim))->assertSessionHasErrors('action');
    }

    public function test_the_successor_must_be_an_active_member_and_the_position_real(): void
    {
        $club = $this->sports();
        $ketua = $this->holder($club, 'Ketua');
        $this->actingAs($this->user('admin'))->post('/kelola/ukm/'.$club->id.'/ganti-pengurus', $this->form($this->user('anggota')->nim))->assertSessionHasErrors('action');
        $this->post('/kelola/ukm/'.$club->id.'/ganti-pengurus', $this->form($this->user('atlet1')->nim, 'Jabatan Khayalan'))->assertSessionHasErrors('action');
        $this->assertSame($ketua, $this->holder($club, 'Ketua'), 'Ketua lama tetap menjabat bila penggantian gagal.');
    }

    public function test_admin_jurusan_replaces_officers_of_what_it_coaches_only(): void
    {
        $club = $this->sports();
        $himaju = $this->himaju();
        $student = $this->user('mahasiswa');
        $this->joinClub($himaju, $student);
        $admin = $this->user('jurusan');

        $this->actingAs($admin)->post('/kelola/ukm/'.$himaju->id.'/ganti-pengurus', $this->form($student->nim))->assertSessionHasNoErrors();
        $this->assertSame($student->id, $this->holder($himaju, 'Ketua'));

        $this->post('/kelola/ukm/'.$club->id.'/ganti-pengurus', $this->form($this->user('atlet1')->nim))->assertForbidden();
        Assignment::create(['user_id' => $admin->id, 'role' => 'pembina', 'club_id' => $club->id, 'starts_at' => now()->subDay(), 'ends_at' => now()->addYear()]);
        $this->actingAs($this->user('jurusan'))->get('/kelola/ukm/'.$club->id)->assertOk()->assertSee('Ganti ketua atau pengurus');
        $this->post('/kelola/ukm/'.$club->id.'/ganti-pengurus', $this->form($this->user('atlet1')->nim))->assertSessionHasNoErrors();
        $this->assertSame($this->user('atlet1')->id, $this->holder($club, 'Ketua'));
    }

    public function test_ketua_and_members_cannot_use_the_replacement(): void
    {
        $club = $this->sports();
        $this->actingAs($this->user('pengurus'))->get('/kelola/ukm/'.$club->id)->assertOk()->assertDontSee('Ganti ketua atau pengurus');
        $this->post('/kelola/ukm/'.$club->id.'/ganti-pengurus', $this->form($this->user('atlet1')->nim))->assertForbidden();
        $this->actingAs($this->user('pembina'))->post('/kelola/ukm/'.$club->id.'/ganti-pengurus', $this->form($this->user('atlet1')->nim))->assertForbidden();
    }

    public function test_officers_of_bem_and_himaju_can_join_another_ukm(): void
    {
        $club = $this->sports();
        $club->recruitments()->update(['closes_at' => now()->subDay()]);
        $bem = Club::where('name', 'Badan Eksekutif Mahasiswa (BEM)')->firstOrFail();
        foreach ([[$bem, 'anggota'], [$this->himaju(), 'mahasiswa']] as [$organization, $key]) {
            $officer = $this->user($key);
            $this->joinClub($organization, $officer);
            Assignment::create(['user_id' => $officer->id, 'role' => 'pengurus', 'club_id' => $organization->id, 'position' => 'Ketua', 'starts_at' => now()->subDay(), 'ends_at' => now()->addYear()]);

            $this->actingAs(User::find($officer->id))->post('/ukm/'.$club->id.'/minta-gabung')->assertSessionHasNoErrors();
            $request = MembershipRequest::where('club_id', $club->id)->where('user_id', $officer->id)->firstOrFail();
            $this->actingAs($this->user('pembina'))->post('/permintaan-gabung/'.$request->id, ['decision' => 'accepted'])->assertSessionHasNoErrors();

            $this->assertTrue(Membership::where('club_id', $club->id)->where('user_id', $officer->id)->where('status', 'active')->exists());
            $this->assertTrue(User::find($officer->id)->assigned('pengurus', $organization->id), 'Jabatan di organisasi asal tetap berlaku.');
        }
    }
}
