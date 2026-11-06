<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Club;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Services\Wita;

/**
 * Activating dosen and tendik, and giving them a task in the same step.
 */
class EmployeeActivationTest extends DemoTestCase
{
    private const URL = '/kelola/master/pegawai/aktifkan';

    private function employee(string $kind, string $nip, string $name, bool $active = false): User
    {
        $user = new User;
        $user->forceFill(['name' => $name, 'username' => $nip, 'email' => $nip.'@'.$kind.'.brudel.invalid', 'password' => $nip, 'active' => $active, 'must_change_password' => true])->save();
        Employee::create(['user_id' => $user->id, 'kind' => $kind, 'position' => 'Uji']);

        return $user;
    }

    /**
     * @return array{employees: list<int>, tasks: list<string>, ends_at: string}
     */
    private function request(array $people, string ...$tasks): array
    {
        return ['employees' => array_map(fn (User $person) => $person->id, $people), 'tasks' => $tasks, 'ends_at' => Wita::input(now()->addYear())];
    }

    private function sports(): Club
    {
        return Club::where('name', 'UKM Olahraga')->firstOrFail();
    }

    public function test_a_dosen_is_activated_and_becomes_pendamping_of_the_chosen_ukm(): void
    {
        $club = $this->sports();
        $dosen = $this->employee('dosen', '198001012005012001', 'Dosen Pendamping Uji');

        $this->actingAs($this->user('admin'))->get('/kelola/master/dosen')->assertOk()->assertSee('Aktifkan dan tugaskan')->assertSee('UKM Olahraga');
        $this->post(self::URL, $this->request([$dosen], 'pembina:'.$club->id))->assertSessionHasNoErrors()->assertSessionHas('success', '1 akun diaktifkan; 1 tugas diberikan');

        $dosen = $dosen->fresh();
        $this->assertTrue($dosen->active);
        $this->assertTrue($dosen->assigned('pembina', $club->id));
        $this->assertTrue($dosen->notifications()->get()->contains(fn ($n) => $n->data['title'] === 'Penugasan baru'));
        $this->get('/kelola/master/dosen')->assertSee('Pendamping UKM Olahraga');
        $this->get('/ukm/'.$club->id)->assertSee('Dosen Pendamping Uji');
    }

    public function test_one_person_can_guide_several_organizations(): void
    {
        $sports = $this->sports();
        $arts = Club::where('category', 'ukm')->whereKeyNot($sports->id)->firstOrFail();
        $dosen = $this->employee('dosen', '198001012005012001', 'Dosen Bimbingan Uji');

        $this->actingAs($this->user('admin'))->post(self::URL, $this->request([$dosen], 'pembina:'.$sports->id, 'pembina:'.$arts->id))
            ->assertSessionHasNoErrors()->assertSessionHas('success', '1 akun diaktifkan; 2 tugas diberikan');
        $this->get('/kelola/master/dosen')->assertSee('Pendamping UKM Olahraga')->assertSee('Pendamping '.$arts->name);

        $third = Club::where('category', 'ukm')->whereKeyNot($sports->id)->whereKeyNot($arts->id)->firstOrFail();
        $this->post(self::URL, $this->request([$dosen], 'pembina:'.$third->id))->assertSessionHasNoErrors()->assertSessionHas('success', '1 tugas diberikan');

        $dosen->fresh()->forceFill(['email' => 'dosen.bimbingan@contoh.test', 'phone' => '081234567890', 'must_change_password' => false, 'email_verified_at' => now()])->save();
        $coached = Club::coachedBy($dosen->fresh())->pluck('id');
        $this->assertEqualsCanonicalizing([$sports->id, $arts->id, $third->id], $coached->all());
        $this->actingAs($dosen->fresh())->get('/kelola/ukm')->assertOk()->assertSee('UKM Olahraga')->assertSee($arts->name)->assertSee($third->name);
    }

    public function test_a_pendamping_runs_the_ukm_after_completing_the_account(): void
    {
        $club = $this->sports();
        $dosen = $this->employee('dosen', '198001012005012001', 'Dosen Pendamping Uji');
        $this->actingAs($this->user('admin'))->post(self::URL, $this->request([$dosen], 'pembina:'.$club->id))->assertSessionHasNoErrors();
        $dosen->fresh()->forceFill(['email' => 'dosen.pendamping@contoh.test', 'phone' => '081234567890', 'must_change_password' => false, 'email_verified_at' => now()])->save();

        $this->actingAs($dosen->fresh())->get('/kelola/ukm')->assertOk()->assertSee('UKM Olahraga');
        $this->get('/ruang/'.$club->id.'?tab=anggota')->assertOk()->assertViewHas('tab', 'anggota')->assertSee('Undang anggota');
    }

    public function test_tendik_can_hold_the_same_tasks_and_active_accounts_can_be_assigned_later(): void
    {
        $department = Department::where('code', 'GIZ')->firstOrFail();
        $tendik = $this->employee('tendik', '197001012000012003', 'Tendik Uji', active: true);

        $this->actingAs($this->user('admin'))->post(self::URL, $this->request([$tendik], 'jurusan:'.$department->id))->assertSessionHasNoErrors()->assertSessionHas('success', '1 tugas diberikan');
        $this->assertSame([$department->id], $tendik->fresh()->scopeIds('jurusan', 'department_id')->all());

        $wadir = $this->employee('dosen', '197501012000012005', 'Pimpinan Uji');
        $this->post(self::URL, $this->request([$wadir], 'pimpinan:Wakil Direktur II'))->assertSessionHasNoErrors();
        $this->assertSame('Wakil Direktur II', Assignment::where('user_id', $wadir->id)->value('position'));
    }

    public function test_several_people_share_a_task_and_one_who_already_holds_it_blocks_nobody(): void
    {
        $club = $this->sports();
        $first = $this->employee('dosen', '198001012005012001', 'Dosen Satu');
        $second = $this->employee('tendik', '197001012000012003', 'Tendik Dua');
        $this->actingAs($this->user('admin'))->post(self::URL, $this->request([$first], 'pembina:'.$club->id))->assertSessionHasNoErrors();

        $this->post(self::URL, $this->request([$first, $second], 'pembina:'.$club->id))->assertSessionHasNoErrors()
            ->assertSessionHas('success', '1 akun diaktifkan; 1 tugas diberikan; sudah memiliki tugas yang dipilih: Dosen Satu');
        $this->assertSame(2, Assignment::where('role', 'pembina')->where('club_id', $club->id)->whereIn('user_id', [$first->id, $second->id])->count());
        $this->assertTrue($second->fresh()->active);
    }

    public function test_activating_needs_no_task_and_a_task_needs_an_end_date(): void
    {
        $dosen = $this->employee('dosen', '198001012005012001', 'Dosen Uji');
        $this->actingAs($this->user('admin'))->post(self::URL, ['employees' => [$dosen->id], 'tasks' => ['pembina:'.$this->sports()->id], 'ends_at' => ''])->assertSessionHasErrors('ends_at');
        $this->assertFalse($dosen->fresh()->active);

        $this->post(self::URL, ['employees' => [$dosen->id]])->assertSessionHasNoErrors();
        $this->assertTrue($dosen->fresh()->active);
        $this->assertFalse(Assignment::where('user_id', $dosen->id)->exists());
    }

    public function test_only_offered_tasks_for_employees_are_accepted_and_only_by_the_super_admin(): void
    {
        $dosen = $this->employee('dosen', '198001012005012001', 'Dosen Uji');
        $task = 'pembina:'.$this->sports()->id;

        $this->actingAs($this->user('jurusan'))->post(self::URL, $this->request([$dosen], $task))->assertForbidden();
        $this->actingAs($this->user('admin'))->post(self::URL, $this->request([$dosen], 'kemahasiswaan:0'))->assertSessionHasErrors('tasks.0');
        $this->assertFalse($dosen->fresh()->active);

        $student = $this->user('anggota');
        $this->post(self::URL, $this->request([$student], $task))->assertSessionHasNoErrors();
        $this->assertFalse($student->fresh()->assigned('pembina'), 'Mahasiswa tidak ditugaskan dari menu ini.');
    }
}
