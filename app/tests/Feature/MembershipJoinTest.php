<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Department;
use App\Models\Membership;
use App\Models\MembershipRequest;
use App\Models\User;

/**
 * The ways into a UKM or HIMAJU: a student's own request, or an invitation or direct addition by the ketua, pembina, or admin jurusan.
 */
class MembershipJoinTest extends DemoTestCase
{
    private function ukm(): Club
    {
        $club = Club::where('name', 'UKM Olahraga')->firstOrFail();
        $club->recruitments()->update(['closes_at' => now()->subDay()]);

        return $club;
    }

    private function himaju(string $code): Club
    {
        return Club::where('category', 'himaju')->where('department_id', Department::where('code', $code)->value('id'))->firstOrFail();
    }

    private function isActiveMember(Club $club, User $student): bool
    {
        return Membership::where('club_id', $club->id)->where('user_id', $student->id)->where('status', 'active')->exists();
    }

    private function askToJoin(Club $club, User $student): MembershipRequest
    {
        $this->actingAs($student)->post('/ukm/'.$club->id.'/minta-gabung', ['message' => 'Saya ingin berlatih bersama.'])->assertSessionHasNoErrors();

        return MembershipRequest::where('club_id', $club->id)->where('user_id', $student->id)->latest('id')->firstOrFail();
    }

    private function invite(Club $club, User $by, User $student): MembershipRequest
    {
        $this->actingAs($by)->post('/ruang/'.$club->id.'/undang', ['user' => $student->nim])->assertSessionHasNoErrors();

        return MembershipRequest::where('club_id', $club->id)->where('user_id', $student->id)->latest('id')->firstOrFail();
    }

    public function test_a_student_request_becomes_a_membership_once_the_pembina_accepts(): void
    {
        $club = $this->ukm();
        $student = $this->user('anggota');
        $this->actingAs($student)->get('/ukm/'.$club->id)->assertOk()->assertSee('Minta bergabung');

        $request = $this->askToJoin($club, $student);
        $this->assertFalse($this->isActiveMember($club, $student), 'Meminta bergabung belum menjadikan anggota.');
        $this->get('/ukm/'.$club->id)->assertSee('Permintaanmu menunggu tinjauan pengurus');
        $this->post('/ukm/'.$club->id.'/minta-gabung')->assertSessionHasErrors('action');

        $this->actingAs($this->user('atlet1'))->post('/permintaan-gabung/'.$request->id, ['decision' => 'accepted'])->assertForbidden();
        $this->actingAs($this->user('pembina'))->get('/ruang/'.$club->id.'?tab=anggota')->assertOk()->assertSee($student->name)->assertSee('Saya ingin berlatih bersama.');
        $this->post('/permintaan-gabung/'.$request->id, ['decision' => 'accepted'])->assertSessionHasNoErrors();

        $this->assertTrue($this->isActiveMember($club, $student));
        $this->assertTrue($student->notifications()->get()->contains(fn ($n) => str_contains($n->data['title'], 'Permintaan bergabung')));
        $this->post('/permintaan-gabung/'.$request->id, ['decision' => 'accepted'])->assertSessionHasErrors('action');
    }

    public function test_a_declined_or_cancelled_request_adds_nobody_and_can_be_asked_again(): void
    {
        $club = $this->ukm();
        $student = $this->user('anggota');
        $declined = $this->askToJoin($club, $student);
        $this->actingAs($this->user('pengurus'))->post('/permintaan-gabung/'.$declined->id, ['decision' => 'declined'])->assertSessionHasNoErrors();
        $this->assertFalse($this->isActiveMember($club, $student));

        $cancelled = $this->askToJoin($club, $student);
        $this->actingAs($this->user('mahasiswa'))->post('/permintaan-gabung/'.$cancelled->id.'/batal')->assertForbidden();
        $this->actingAs($student)->post('/permintaan-gabung/'.$cancelled->id.'/batal')->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $cancelled->fresh()->status);
        $this->assertSame('pending', $this->askToJoin($club, $student)->status);
    }

    public function test_an_open_recruitment_replaces_the_direct_request(): void
    {
        $club = $this->ukm();
        $club->recruitments()->create(['name' => 'Rekrutmen uji', 'opens_at' => now()->subDay(), 'closes_at' => now()->addWeek(), 'quota' => 10, 'requirements' => 'Mahasiswa aktif.']);
        $this->actingAs($this->user('anggota'))->get('/ukm/'.$club->id)->assertOk()->assertDontSee('Minta bergabung')->assertSee('Kirim pendaftaran');
        $this->post('/ukm/'.$club->id.'/minta-gabung')->assertSessionHasErrors('action');
    }

    public function test_ketua_and_pembina_invite_and_the_student_decides(): void
    {
        $club = $this->ukm();
        $invited = $this->user('anggota');
        $invitation = $this->invite($club, $this->user('pengurus'), $invited);
        $this->assertFalse($this->isActiveMember($club, $invited), 'Undangan baru berlaku setelah diterima.');
        $this->assertTrue($invited->notifications()->get()->contains(fn ($n) => str_contains($n->data['title'], 'Undangan bergabung')));

        $this->actingAs($this->user('mahasiswa'))->post('/undangan-organisasi/'.$invitation->id, ['decision' => 'accepted'])->assertForbidden();
        $this->actingAs($invited)->get('/ukm-saya')->assertOk()->assertSee('mengundangmu bergabung');
        $this->post('/undangan-organisasi/'.$invitation->id, ['decision' => 'accepted'])->assertSessionHasNoErrors();
        $this->assertTrue($this->isActiveMember($club, $invited));

        $student = $this->user('mahasiswa');
        $declined = $this->invite($club, $this->user('pembina'), $student);
        $this->actingAs($student)->post('/undangan-organisasi/'.$declined->id, ['decision' => 'declined'])->assertSessionHasNoErrors();
        $this->assertFalse($this->isActiveMember($club, $student));
    }

    public function test_the_ketua_adds_a_student_who_becomes_a_member_at_once(): void
    {
        $club = $this->ukm();
        $student = $this->user('anggota');
        $this->actingAs($this->user('pengurus'))->get('/ruang/'.$club->id.'?tab=anggota')->assertOk()->assertSee('Tambah anggota')->assertSee('Undang anggota');
        $this->post('/ruang/'.$club->id.'/anggota', ['user' => $student->nim, 'division' => 'Publikasi'])->assertSessionHasNoErrors();

        $this->assertTrue($this->isActiveMember($club, $student));
        $this->assertSame('Publikasi', Membership::where('club_id', $club->id)->where('user_id', $student->id)->value('division'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'membership.added']);
        $this->assertTrue($student->notifications()->get()->contains(fn ($n) => str_contains($n->data['title'], 'Anda ditambahkan')));

        $this->post('/ruang/'.$club->id.'/anggota', ['user' => $student->nim])->assertSessionHasErrors('action');
    }

    public function test_adding_a_student_settles_what_was_still_waiting(): void
    {
        $club = $this->ukm();
        $student = $this->user('anggota');
        $invitation = $this->invite($club, $this->user('pembina'), $student);

        $this->post('/ruang/'.$club->id.'/anggota', ['user' => $student->email])->assertSessionHasNoErrors();

        $this->assertSame('accepted', $invitation->fresh()->status);
        $this->assertTrue($this->isActiveMember($club, $student));
    }

    public function test_only_those_who_may_invite_add_members_and_never_themselves(): void
    {
        $club = $this->ukm();
        $student = $this->user('anggota');
        $this->actingAs($this->user('atlet1'))->post('/ruang/'.$club->id.'/anggota', ['user' => $student->nim])->assertForbidden();
        $this->actingAs($this->user('jurusan'))->post('/ruang/'.$club->id.'/anggota', ['user' => $student->nim])->assertForbidden();
        $this->assertFalse($this->isActiveMember($club, $student));

        $chair = $this->user('pengurus');
        $this->actingAs($chair)->post('/ruang/'.$club->id.'/anggota', ['user' => $chair->email])->assertForbidden();
        $this->post('/ruang/'.$club->id.'/anggota', ['user' => '0000000000'])->assertSessionHasErrors('action');
    }

    public function test_adding_keeps_the_rules_of_each_organization(): void
    {
        $admin = $this->user('jurusan');
        $student = $this->user('mahasiswa');
        $own = $this->himaju('KEP');
        Membership::where('club_id', $own->id)->where('user_id', $student->id)->delete();
        $this->actingAs($admin)->post('/ruang/'.$own->id.'/anggota', ['user' => $student->nim])->assertSessionHasNoErrors();
        $this->assertTrue($this->isActiveMember($own, $student));

        $other = $this->himaju('GIZ');
        $this->actingAs($this->user('admin'))->post('/ruang/'.$other->id.'/anggota', ['user' => $student->nim])->assertSessionHasErrors('action');
        $this->assertFalse($this->isActiveMember($other, $student));

        $faith = Club::where('category', 'ukm')->where('active', true)->orderBy('id')->take(2)->get();
        $faith->each->update(['field' => 'Kerohanian']);
        $this->joinClub($faith[0], $this->user('anggota'));
        $this->post('/ruang/'.$faith[1]->id.'/anggota', ['user' => $this->user('anggota')->nim])->assertSessionHasErrors('action');
    }

    public function test_bem_and_mpm_appoint_elected_members_from_their_room_instead(): void
    {
        $club = Club::where('category', 'lembaga')->firstOrFail();
        $this->actingAs($this->user('admin'))->get('/ruang/'.$club->id.'?tab=anggota')->assertOk()->assertSee('Tetapkan anggota terpilih')->assertDontSee('Kirim undangan');
        $this->post('/ruang/'.$club->id.'/anggota', ['user' => $this->user('anggota')->nim])->assertForbidden();
    }

    public function test_the_club_can_withdraw_an_invitation_and_members_cannot_invite(): void
    {
        $club = $this->ukm();
        $student = $this->user('anggota');
        $this->actingAs($this->user('atlet1'))->post('/ruang/'.$club->id.'/undang', ['user' => $student->nim])->assertForbidden();

        $invitation = $this->invite($club, $this->user('pembina'), $student);
        $this->post('/ruang/'.$club->id.'/undang', ['user' => $student->nim])->assertSessionHasErrors('action');
        $this->post('/permintaan-gabung/'.$invitation->id.'/batal')->assertSessionHasNoErrors();
        $this->actingAs($student)->post('/undangan-organisasi/'.$invitation->id, ['decision' => 'accepted'])->assertSessionHasErrors('action');
        $this->assertFalse($this->isActiveMember($club, $student));
    }

    public function test_himaju_takes_only_its_own_jurusan_through_the_admin_jurusan(): void
    {
        $own = $this->himaju('KEP');
        $admin = $this->user('jurusan');
        $this->invite($own, $admin, $this->user('mahasiswa'));
        $this->actingAs($admin)->post('/ruang/'.$own->id.'/undang', ['user' => $this->user('anggota')->nim])->assertSessionHasErrors('action');
        $this->post('/ruang/'.$this->himaju('GIZ')->id.'/undang', ['user' => $this->user('anggota')->nim])->assertForbidden();
        $this->actingAs($this->user('pembina'))->post('/ruang/'.$own->id.'/undang', ['user' => $this->user('mahasiswa')->nim])->assertForbidden();

        $outsider = $this->user('anggota');
        $this->actingAs($outsider)->get('/ukm/'.$own->id)->assertOk()->assertDontSee('Minta bergabung')->assertSee('Khusus mahasiswa Jurusan');
        $this->post('/ukm/'.$own->id.'/minta-gabung')->assertSessionHasErrors('action');

        $request = $this->askToJoin($own, $this->user('pengurus'));
        $this->actingAs($admin)->post('/permintaan-gabung/'.$request->id, ['decision' => 'accepted'])->assertSessionHasNoErrors();
        $this->assertTrue($this->isActiveMember($own, $this->user('pengurus')));
    }

    public function test_elected_bodies_and_the_single_choice_field_stay_protected(): void
    {
        $student = $this->user('anggota');
        $bem = Club::where('name', 'Badan Eksekutif Mahasiswa (BEM)')->firstOrFail();
        $this->actingAs($this->user('mahasiswa'))->post('/ukm/'.$bem->id.'/minta-gabung')->assertSessionHasErrors('action');

        [$first, $second] = Club::where('field', 'Kerohanian')->limit(2)->get()->all();
        $this->joinClub($first, $student);
        $second->recruitments()->update(['closes_at' => now()->subDay()]);
        $this->actingAs($student)->post('/ukm/'.$second->id.'/minta-gabung')->assertSessionHasErrors('action');
        $this->assertFalse($this->isActiveMember($second, $student));
    }
}
