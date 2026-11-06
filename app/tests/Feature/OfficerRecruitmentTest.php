<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Club;
use App\Models\Department;
use App\Models\Membership;
use App\Models\OfficerCandidate;
use App\Models\OfficerRecruitment;
use App\Models\User;

/**
 * New officers of BEM, MPM and HIMAJU: the outgoing officers set the criteria and validate those who stand.
 */
class OfficerRecruitmentTest extends DemoTestCase
{
    private function bem(): Club
    {
        return Club::where('name', 'like', '%(BEM)%')->firstOrFail();
    }

    private function himaju(string $code): Club
    {
        return Club::where('category', 'himaju')->where('department_id', Department::where('code', $code)->value('id'))->firstOrFail();
    }

    private function chair(Club $club, string $key = 'pengurus', string $position = 'Ketua', ?string $ends = null): User
    {
        $user = $this->user($key);
        Assignment::create(['user_id' => $user->id, 'role' => 'pengurus', 'club_id' => $club->id, 'position' => $position, 'period' => '2025/2026', 'starts_at' => now()->subYear(), 'ends_at' => $ends ?? now()->addYears(2)]);

        return User::findOrFail($user->id);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Rekrutmen Pengurus 2027', 'period' => '2026/2027', 'positions' => ['Ketua', 'Anggota pengurus'],
            'criteria' => "Mahasiswa aktif\nPernah menjadi panitia",
            'opens_at' => now()->subDay()->format('Y-m-d H:i'), 'closes_at' => now()->addWeek()->format('Y-m-d H:i'),
            'term_starts_at' => now()->addMonth()->format('Y-m-d H:i'), 'term_ends_at' => now()->addMonth()->addYear()->format('Y-m-d H:i'),
        ];
    }

    private function open(Club $club, User $chair): OfficerRecruitment
    {
        $this->actingAs($chair)->post('/kelola/ukm/'.$club->id.'/rekrutmen-pengurus', $this->form())->assertSessionHasNoErrors();

        return OfficerRecruitment::where('club_id', $club->id)->latest('id')->firstOrFail();
    }

    private function stand(OfficerRecruitment $recruitment, string $key = 'anggota', string $position = 'Ketua'): OfficerCandidate
    {
        $this->actingAs($this->user($key))->post('/rekrutmen-pengurus/'.$recruitment->id.'/ajukan', ['position' => $position, 'statement' => 'Saya siap melanjutkan program organisasi ini.'])->assertSessionHasNoErrors();

        return OfficerCandidate::where('officer_recruitment_id', $recruitment->id)->latest('id')->firstOrFail();
    }

    public function test_the_outgoing_officers_set_the_criteria_and_the_posts(): void
    {
        $club = $this->bem();
        $chair = $this->chair($club);
        $recruitment = $this->open($club, $chair);

        $this->assertSame(['Mahasiswa aktif', 'Pernah menjadi panitia'], $recruitment->criteria);
        $this->assertSame(['Ketua', 'Anggota pengurus'], $recruitment->positions);
        $this->get('/kelola/ukm/'.$club->id.'/rekrutmen-pengurus')->assertOk()->assertSee('Rekrutmen Pengurus 2027');
        $this->get('/kelola/rekrutmen-pengurus/'.$recruitment->id)->assertOk()->assertSee('Pernah menjadi panitia');

        $this->actingAs($this->user('mahasiswa'))->post('/kelola/ukm/'.$club->id.'/rekrutmen-pengurus', $this->form(['title' => 'Bukan pengurus']))->assertForbidden();
        $this->get('/kelola/rekrutmen-pengurus/'.$recruitment->id)->assertForbidden();
    }

    public function test_it_is_for_bem_mpm_and_himaju_only(): void
    {
        $ukm = Club::where('name', 'UKM Olahraga')->firstOrFail();
        $this->actingAs($this->user('pengurus'))->get('/kelola/ukm/'.$ukm->id.'/rekrutmen-pengurus')->assertForbidden();
        $this->post('/kelola/ukm/'.$ukm->id.'/rekrutmen-pengurus', $this->form())->assertForbidden();
    }

    public function test_a_plain_officer_cannot_open_or_validate(): void
    {
        $club = $this->bem();
        $recruitment = $this->open($club, $this->chair($club));
        $candidate = $this->stand($recruitment);
        $treasurer = $this->chair($club, 'atlet1', 'Bendahara');

        $this->actingAs($treasurer)->get('/kelola/rekrutmen-pengurus/'.$recruitment->id)->assertOk()->assertDontSee('Kriteria yang terpenuhi');
        $this->post('/kelola/kandidat-pengurus/'.$candidate->id, ['decision' => 'accepted', 'met' => [0, 1]])->assertForbidden();
        $this->post('/kelola/ukm/'.$club->id.'/rekrutmen-pengurus', $this->form(['title' => 'Rekrutmen lain']))->assertForbidden();
    }

    public function test_bad_schedules_and_empty_criteria_are_refused(): void
    {
        $club = $this->bem();
        $chair = $this->chair($club);
        $this->actingAs($chair);
        $url = '/kelola/ukm/'.$club->id.'/rekrutmen-pengurus';

        $this->post($url, $this->form(['closes_at' => now()->subDays(2)->format('Y-m-d H:i')]))->assertSessionHasErrors('action');
        $this->post($url, $this->form(['term_starts_at' => now()->addDay()->format('Y-m-d H:i')]))->assertSessionHasErrors('action');
        $this->post($url, $this->form(['criteria' => "  \n  "]))->assertSessionHasErrors('criteria');
        $this->post($url, $this->form(['positions' => ['Presiden']]))->assertSessionHasErrors('action');

        $this->post($url, $this->form())->assertSessionHasNoErrors();
        $this->post($url, $this->form(['title' => 'Rekrutmen tumpang tindih']))->assertSessionHasErrors('action');
    }

    public function test_criteria_lock_once_someone_stands(): void
    {
        $club = $this->bem();
        $chair = $this->chair($club);
        $recruitment = $this->open($club, $chair);
        $this->stand($recruitment);

        $this->actingAs($chair)->post('/kelola/rekrutmen-pengurus/'.$recruitment->id, $this->form(['criteria' => 'Kriteria baru']))->assertSessionHasErrors('action');
        $this->post('/kelola/rekrutmen-pengurus/'.$recruitment->id, $this->form(['title' => 'Judul diperbarui']))->assertSessionHasNoErrors();
        $this->assertSame('Judul diperbarui', $recruitment->fresh()->title);
    }

    public function test_a_student_stands_once_sees_the_status_and_may_withdraw(): void
    {
        $club = $this->bem();
        $chair = $this->chair($club);
        $recruitment = $this->open($club, $chair);
        $student = $this->user('anggota');

        $this->actingAs($student)->get('/ukm-saya')->assertOk()->assertSee('Rekrutmen pengurus dibuka');
        $this->get('/ukm/'.$club->id)->assertOk()->assertSee('Pernah menjadi panitia')->assertSee('Ajukan diri');

        $candidate = $this->stand($recruitment);
        $this->assertTrue($chair->notifications()->get()->contains(fn ($n) => str_contains($n->data['title'], 'Kandidat pengurus')));
        $this->post('/rekrutmen-pengurus/'.$recruitment->id.'/ajukan', ['position' => 'Ketua', 'statement' => 'Saya mencoba mengajukan diri dua kali.'])->assertSessionHasErrors('action');
        $this->get('/ukm-saya')->assertSee('Pengajuan pengurus')->assertDontSee('Rekrutmen pengurus dibuka');

        $this->actingAs($this->user('mahasiswa'))->post('/kandidat-pengurus/'.$candidate->id.'/batal')->assertForbidden();
        $this->actingAs($student)->post('/kandidat-pengurus/'.$candidate->id.'/batal')->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $candidate->fresh()->status);
        $this->assertSame('submitted', $this->stand($recruitment)->status, 'Pengajuan yang dibatalkan dapat diulang.');
    }

    public function test_standing_follows_the_schedule_the_posts_and_the_jurusan(): void
    {
        $bem = $this->bem();
        $recruitment = $this->open($bem, $this->chair($bem));
        $this->actingAs($this->user('anggota'))->post('/rekrutmen-pengurus/'.$recruitment->id.'/ajukan', ['position' => 'Bendahara', 'statement' => 'Jabatan ini tidak dibuka untuk rekrutmen.'])->assertSessionHasErrors('action');
        $this->post('/rekrutmen-pengurus/'.$recruitment->id.'/ajukan', ['position' => 'Ketua', 'statement' => 'pendek'])->assertSessionHasErrors('statement');

        $recruitment->update(['closes_at' => now()->subMinute()]);
        $this->post('/rekrutmen-pengurus/'.$recruitment->id.'/ajukan', ['position' => 'Ketua', 'statement' => 'Pendaftaran sudah ditutup untuk rekrutmen.'])->assertSessionHasErrors('action');

        $other = $this->himaju('GIZ');
        $himaju = OfficerRecruitment::create($this->form(['club_id' => $other->id, 'criteria' => ['Aktif'], 'created_by' => $this->user('admin')->id]));
        $this->actingAs($this->user('mahasiswa'))->get('/ukm-saya')->assertDontSee($himaju->title);
        $this->post('/rekrutmen-pengurus/'.$himaju->id.'/ajukan', ['position' => 'Ketua', 'statement' => 'Saya bukan mahasiswa jurusan ini.'])->assertSessionHasErrors('action');
    }

    public function test_acceptance_needs_every_criterion_and_starts_with_the_new_term(): void
    {
        $club = $this->bem();
        $chair = $this->chair($club);
        $recruitment = $this->open($club, $chair);
        $candidate = $this->stand($recruitment);
        $url = '/kelola/kandidat-pengurus/'.$candidate->id;

        $this->actingAs($chair)->post($url, ['decision' => 'accepted', 'met' => [0]])->assertSessionHasErrors('action');
        $this->post($url, ['decision' => 'accepted', 'met' => [0, 1]])->assertSessionHasNoErrors();

        $student = User::findOrFail($candidate->user_id);
        $this->assertSame('accepted', $candidate->fresh()->status);
        $this->assertTrue(Membership::where('club_id', $club->id)->where('user_id', $student->id)->where('status', 'active')->exists());
        $assignment = Assignment::where('user_id', $student->id)->where('role', 'pengurus')->where('club_id', $club->id)->firstOrFail();
        $this->assertSame(['Ketua', '2026/2027'], [$assignment->position, $assignment->period]);
        $this->assertTrue($assignment->starts_at->equalTo($recruitment->term_starts_at));
        $this->assertFalse($student->leadsClub($club->id), 'Belum menjabat sebelum masa jabatan dimulai.');

        $this->post($url, ['decision' => 'rejected', 'note' => 'Terlambat dibatalkan'])->assertSessionHasErrors('action');
    }

    public function test_the_outgoing_holder_serves_until_the_new_term_starts(): void
    {
        $club = $this->bem();
        $chair = $this->chair($club);
        $recruitment = $this->open($club, $chair);
        $candidate = $this->stand($recruitment);

        $this->actingAs($chair)->post('/kelola/kandidat-pengurus/'.$candidate->id, ['decision' => 'accepted', 'met' => [0, 1]])->assertSessionHasNoErrors();

        $old = Assignment::where('user_id', $chair->id)->where('club_id', $club->id)->where('role', 'pengurus')->firstOrFail();
        $this->assertTrue($old->ends_at->equalTo($recruitment->term_starts_at));
        $this->assertTrue(User::findOrFail($chair->id)->leadsClub($club->id), 'Ketua lama tetap menjabat sampai masa jabatan baru.');
    }

    public function test_a_serving_officer_may_stand_for_another_post_but_not_validate_themselves(): void
    {
        $club = $this->bem();
        $chair = $this->chair($club);
        $treasurer = $this->chair($club, 'anggota', 'Bendahara');
        $recruitment = $this->open($club, $chair);
        $candidate = $this->stand($recruitment);
        $own = $this->stand($recruitment, 'pengurus', 'Anggota pengurus');

        $this->actingAs($chair)->post('/kelola/kandidat-pengurus/'.$own->id, ['decision' => 'accepted', 'met' => [0, 1]])->assertForbidden();
        $this->post('/kelola/kandidat-pengurus/'.$candidate->id, ['decision' => 'accepted', 'met' => [0, 1]])->assertSessionHasNoErrors();

        $assignments = Assignment::where('user_id', $treasurer->id)->where('club_id', $club->id)->where('role', 'pengurus')->orderBy('starts_at')->get();
        $this->assertSame(['Bendahara', 'Ketua'], $assignments->pluck('position')->all());
        $this->assertTrue($assignments[0]->ends_at->equalTo($assignments[1]->starts_at));
    }

    public function test_a_single_holder_post_is_filled_once(): void
    {
        $club = $this->bem();
        $chair = $this->chair($club);
        $recruitment = $this->open($club, $chair);
        $first = $this->stand($recruitment, 'anggota');
        $second = $this->stand($recruitment, 'mahasiswa');

        $this->actingAs($chair)->post('/kelola/kandidat-pengurus/'.$first->id, ['decision' => 'accepted', 'met' => [0, 1]])->assertSessionHasNoErrors();
        $this->post('/kelola/kandidat-pengurus/'.$second->id, ['decision' => 'accepted', 'met' => [0, 1]])->assertSessionHasErrors('action');
        $this->assertSame('submitted', $second->fresh()->status);
    }

    public function test_a_rejection_needs_a_note_and_tells_the_student(): void
    {
        $club = $this->bem();
        $chair = $this->chair($club);
        $candidate = $this->stand($this->open($club, $chair));
        $url = '/kelola/kandidat-pengurus/'.$candidate->id;

        $this->actingAs($chair)->post($url, ['decision' => 'rejected', 'note' => 'no'])->assertSessionHasErrors('action');
        $this->post($url, ['decision' => 'rejected', 'met' => [0], 'note' => 'Belum pernah menjadi panitia.'])->assertSessionHasNoErrors();

        $student = User::findOrFail($candidate->user_id);
        $this->assertSame([0], $candidate->fresh()->met_criteria);
        $this->assertFalse(Assignment::where('user_id', $student->id)->where('club_id', $club->id)->exists());
        $this->assertTrue($student->notifications()->get()->contains(fn ($n) => str_contains($n->data['body'] ?? '', 'Belum pernah menjadi panitia.')));
        $this->actingAs($student)->get('/ukm-saya')->assertSee('Belum pernah menjadi panitia.');
    }

    public function test_the_admin_jurusan_can_step_in_for_its_himaju(): void
    {
        $club = $this->himaju('KEP');
        $recruitment = $this->open($club, $this->user('jurusan'));
        $candidate = $this->stand($recruitment, 'mahasiswa');

        $this->actingAs($this->user('jurusan'))->post('/kelola/kandidat-pengurus/'.$candidate->id, ['decision' => 'accepted', 'met' => [0, 1]])->assertSessionHasNoErrors();
        $this->assertTrue(Assignment::where('user_id', $candidate->user_id)->where('club_id', $club->id)->where('position', 'Ketua')->exists());
    }
}
