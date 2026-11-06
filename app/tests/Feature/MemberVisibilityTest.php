<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Club;

/**
 * Students do not see who else belongs to an organization, and an admin jurusan stays within what it coaches.
 */
class MemberVisibilityTest extends DemoTestCase
{
    private function sports(): Club
    {
        return Club::where('name', 'UKM Olahraga')->firstOrFail();
    }

    public function test_a_member_sees_no_member_list_and_no_other_names_on_the_leaderboard(): void
    {
        $club = $this->sports();
        $member = $this->user('atlet1');
        $other = $this->user('atlet2');

        $this->actingAs($member)->get('/ruang/'.$club->id.'?tab=anggota')->assertOk()->assertViewHas('tab', 'beranda')
            ->assertDontSee('?tab=anggota', false)->assertDontSee($other->name);
        $this->get('/ruang/'.$club->id.'?tab=peringkat')->assertOk()->assertSee($member->name)->assertDontSee($other->name)->assertSee('Anggota #');
        $this->get('/ukm/'.$club->id)->assertOk()->assertDontSee($other->name);
    }

    public function test_officers_and_coaches_keep_the_member_list(): void
    {
        $club = $this->sports();
        $name = $this->user('atlet2')->name;
        foreach (['pengurus', 'pembina', 'admin'] as $role) {
            $this->actingAs($this->user($role))->get('/ruang/'.$club->id.'?tab=anggota')->assertOk()->assertViewHas('tab', 'anggota')->assertSee($name);
        }
        $this->actingAs($this->user('pengurus'))->get('/ruang/'.$club->id.'?tab=peringkat')->assertSee($name);
    }

    public function test_admin_jurusan_only_sees_the_organizations_it_coaches(): void
    {
        $club = $this->sports();
        $himaju = Club::where('name', 'HIMAJU Keperawatan')->firstOrFail();
        $admin = $this->user('jurusan');

        $this->actingAs($admin)->get('/ukm')->assertOk()->assertSee('HIMAJU Keperawatan')->assertDontSee('UKM Olahraga')->assertDontSee('HIMAJU Farmasi');
        $this->get('/ukm/'.$himaju->id)->assertOk();
        $this->get('/ukm/'.$club->id)->assertNotFound();
        $this->get('/laporan')->assertViewHas('clubs', fn ($clubs) => $clubs->pluck('id')->all() === [$himaju->id]);

        Assignment::create(['user_id' => $admin->id, 'role' => 'pembina', 'club_id' => $club->id, 'starts_at' => now()->subDay(), 'ends_at' => now()->addYear()]);
        $coach = $this->user('jurusan');
        $this->actingAs($coach)->get('/ukm/'.$club->id)->assertOk();
        $this->get('/kelola/ukm')->assertOk()->assertSee('UKM Olahraga')->assertSee('/kelola/ukm/'.$club->id, false);
    }

    public function test_the_administrator_and_students_still_see_every_organization(): void
    {
        foreach (['admin', 'mahasiswa'] as $role) {
            $this->actingAs($this->user($role))->get('/ukm')->assertOk()->assertSee('UKM Olahraga')->assertSee('HIMAJU Farmasi');
        }
        $this->actingAs($this->user('admin'))->get('/kelola/ukm')->assertOk()->assertSee('UKM Olahraga')->assertSee('HIMAJU Farmasi');
    }

    public function test_a_pembina_reaches_kelola_ukm_for_their_own_ukm_only(): void
    {
        $club = $this->sports();
        $this->actingAs($this->user('pembina'))->get('/kelola/ukm')->assertOk()->assertSee('UKM Olahraga')->assertDontSee('HIMAJU Farmasi')->assertSee('Kelola anggota');
        $this->get('/kelola/ukm/'.$club->id)->assertForbidden();
    }
}
