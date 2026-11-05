<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Club;
use App\Models\ClubApplication;
use App\Models\Department;
use App\Models\Membership;
use App\Models\Program;
use App\Models\Recruitment;
use App\Models\User;

class OrganizationTest extends DemoTestCase
{
    /** @var array<string, mixed> */
    private array $recruitment = ['name' => 'Rekrutmen uji', 'opens_at' => '2026-10-01T08:00', 'closes_at' => '2026-12-01T08:00', 'quota' => 30, 'requirements' => 'Mahasiswa aktif.'];

    private function himaju(string $code): Club
    {
        return Club::where('category', 'himaju')->where('department_id', Department::where('code', $code)->value('id'))->firstOrFail();
    }

    private function membershipStatus(Club $club, User $user): ?string
    {
        return Membership::where('club_id', $club->id)->where('user_id', $user->id)->value('status');
    }

    public function test_official_organizations_are_seeded_by_category_and_listed_in_order(): void
    {
        $this->assertEquals(['lembaga' => 2, 'himaju' => 7, 'ukm' => 13], Club::selectRaw('category, count(*) as total')->groupBy('category')->pluck('total', 'category')->map(fn ($total) => (int) $total)->all());
        $this->assertSame(7, Club::where('category', 'himaju')->distinct()->count('department_id'));
        $this->assertSame(Department::where('code', 'TLM')->value('id'), Club::where('name', 'HIMAJU Teknologi Laboratorium Medis')->value('department_id'));
        $this->assertNull(Club::where('name', 'Badan Eksekutif Mahasiswa (BEM)')->value('field'));
        $this->assertSame('Kerohanian', Club::where('name', 'UKM Ikatan Mahasiswa Muslim Poltekkes Manado')->value('field'));

        $this->actingAs($this->user('mahasiswa'))->get('/ukm')->assertOk()->assertSeeInOrder([
            'Badan Eksekutif Mahasiswa (BEM)', 'Majelis Permusyawaratan Mahasiswa (MPM)', 'HIMAJU Keperawatan', 'HIMAJU Gizi dan Dietetika',
            'UKM Ikatan Duta', 'UKM Satgas Kegawatdaruratan Bencana', 'UKM Ikatan Mahasiswa Muslim Poltekkes Manado',
        ]);
    }

    public function test_being_in_a_jurusan_does_not_make_a_student_a_himaju_member(): void
    {
        $student = $this->user('mahasiswa');
        $this->assertNull($this->membershipStatus($this->himaju('KEP'), $student));
        $this->actingAs($student)->get('/ukm/'.$this->himaju('KEP')->id)->assertOk()->assertSee('Minta bergabung')->assertDontSee('Kamu anggota HIMAJU Keperawatan');

        $student->update(['program_id' => Program::where('code', 'GIZ')->value('id')]);
        $student->forceFill(['active' => false])->save();
        $student->forceFill(['active' => true])->save();
        $this->assertFalse($student->memberships()->whereHas('club', fn ($q) => $q->where('category', 'himaju'))->exists(), 'Perubahan data mahasiswa tidak menambah keanggotaan HIMAJU.');
        $this->actingAs($this->user('admin'))->post('/kelola/ukm/'.$this->himaju('GIZ')->id.'/rekrutmen', $this->recruitment)->assertSessionHasErrors('action');
    }

    public function test_himaju_membership_ends_with_the_jurusan_and_the_student_may_leave(): void
    {
        $student = $this->user('mahasiswa');
        $this->joinClub($this->himaju('KEP'), $student);
        $student->update(['program_id' => Program::where('code', 'GIZ')->value('id')]);
        $this->assertSame('left', $this->membershipStatus($this->himaju('KEP'), $student), 'Pindah jurusan mengakhiri HIMAJU lama.');
        $this->assertNull($this->membershipStatus($this->himaju('GIZ'), $student), 'Tidak otomatis masuk HIMAJU baru.');

        $member = $this->joinClub($this->himaju('GIZ'), $this->user('anggota'));
        $this->user('anggota')->forceFill(['active' => false])->save();
        $this->assertSame('inactive', $member->fresh()->status);
        $this->user('anggota')->forceFill(['active' => true])->save();
        $this->assertSame('inactive', $member->fresh()->status, 'Aktif kembali tidak otomatis mengaktifkan HIMAJU.');
        $this->actingAs($this->user('admin'))->post('/kelola/anggota/'.$member->id, ['status' => 'left', 'reason' => 'Ingin keluar dari himpunan'])->assertSessionHasNoErrors();
        $this->assertSame('left', $member->fresh()->status);
    }

    public function test_moving_a_program_to_another_jurusan_ends_the_old_himaju_membership(): void
    {
        $program = Program::where('code', 'KESLING')->firstOrFail();
        $student = $this->user('atlet1');
        $this->joinClub($this->himaju('KL'), $student);
        $this->actingAs($this->user('admin'))->post('/kelola/prodi/'.$program->id, ['name' => $program->name, 'department_id' => Department::where('code', 'KEP')->value('id'), 'code' => $program->code])
            ->assertSessionHasNoErrors();
        $this->assertSame('left', $this->membershipStatus($this->himaju('KL'), $student));
        $this->assertNull($this->membershipStatus($this->himaju('KEP'), $student));
    }

    public function test_upgrade_removes_automatic_himaju_memberships_but_keeps_officers_and_real_members(): void
    {
        $club = $this->himaju('KEP');
        $automatic = 'Anggota otomatis HIMAJU jurusan';
        $plain = Membership::create(['club_id' => $club->id, 'user_id' => $this->user('mahasiswa')->id, 'status' => 'active', 'joined_at' => now(), 'status_reason' => $automatic]);
        $officer = Membership::create(['club_id' => $club->id, 'user_id' => $this->user('pengurus')->id, 'status' => 'active', 'joined_at' => now(), 'status_reason' => $automatic]);
        Assignment::create(['user_id' => $officer->user_id, 'role' => 'pengurus', 'club_id' => $club->id, 'position' => 'Ketua', 'starts_at' => now()->subDay(), 'ends_at' => now()->addYear()]);
        $joined = $this->joinClub($club, $this->user('atlet1'));

        (require database_path('migrations/2026_11_05_000001_make_himaju_membership_optional.php'))->up();

        $this->assertDatabaseMissing('memberships', ['id' => $plain->id]);
        $this->assertSame('Pengurus organisasi', $officer->fresh()->status_reason);
        $this->assertNotNull($joined->fresh());
    }

    public function test_bem_and_mpm_members_are_appointed_from_the_election(): void
    {
        $bem = Club::where('name', 'Badan Eksekutif Mahasiswa (BEM)')->first();
        $mpm = Club::where('name', 'Majelis Permusyawaratan Mahasiswa (MPM)')->first();
        $basis = 'Hasil Pemilihan Raya Mahasiswa 2026';
        $this->assertSame('Kementerian Kesehatan', Membership::where('club_id', $bem->id)->where('user_id', $this->user('anggota')->id)->value('division'));

        $this->actingAs($this->user('pengurus'))->post('/kelola/ukm/'.$bem->id.'/anggota', ['user' => 'DEMO001', 'basis' => $basis])->assertForbidden();
        $this->actingAs($this->user('admin'))->post('/kelola/ukm/'.$bem->id.'/rekrutmen', $this->recruitment)->assertSessionHasErrors('action');
        $this->post('/kelola/ukm/'.$mpm->id.'/anggota', ['user' => 'DEMO001', 'division' => 'Komisi A', 'basis' => $basis])->assertSessionHasNoErrors();
        $this->post('/kelola/ukm/'.$bem->id.'/anggota', ['user' => 'mahasiswa@demo.brudel.test', 'basis' => $basis])->assertSessionHasNoErrors();
        $this->post('/kelola/ukm/'.$mpm->id.'/anggota', ['user' => 'DEMO001', 'basis' => $basis])->assertSessionHasErrors('action');
        $this->post('/kelola/ukm/'.$mpm->id.'/anggota', ['user' => 'DEMO002', 'basis' => 'x'])->assertSessionHasErrors('action');
        $this->post('/kelola/ukm/'.Club::where('name', 'UKM Olahraga')->value('id').'/anggota', ['user' => 'DEMO002', 'basis' => $basis])->assertSessionHasErrors('action');
        $this->assertSame(2, Membership::whereIn('club_id', [$bem->id, $mpm->id])->where('user_id', $this->user('mahasiswa')->id)->where('status', 'active')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'membership.appointed']);

        $this->actingAs($this->user('mahasiswa'))->get('/ukm-saya')->assertSee('Majelis Permusyawaratan Mahasiswa (MPM)')->assertSee('Komisi A');
        $this->get('/ukm/'.$bem->id)->assertSee('Anggota terpilih')->assertDontSee('Kirim pendaftaran');
    }

    public function test_students_choose_one_spiritual_ukm_and_join_other_ukm_freely(): void
    {
        $open = fn (string $name) => Recruitment::create(['club_id' => Club::where('name', $name)->value('id'), 'name' => 'Rekrutmen uji', 'opens_at' => now()->subDay(), 'closes_at' => now()->addWeek(), 'quota' => 50, 'requirements' => 'Mahasiswa aktif.']);
        $christian = $open('UKM Persekutuan Mahasiswa Kristen');
        $catholic = $open('UKM Keluarga Mahasiswa Katolik');
        $motivation = ['motivation' => 'Saya ingin bertumbuh bersama teman-teman di kampus.'];
        $student = $this->user('mahasiswa');

        $this->actingAs($student)->post('/rekrutmen/'.$christian->id, $motivation)->assertSessionHasNoErrors();
        $this->post('/rekrutmen/'.$catholic->id, $motivation)->assertSessionHasErrors('action');
        foreach (['UKM English Club', 'UKM Kesenian', 'UKM Olahraga', 'UKM Korps Sukarela PMI'] as $name) {
            $this->post('/rekrutmen/'.Recruitment::whereHas('club', fn ($q) => $q->where('name', $name))->value('id'), $motivation)->assertSessionHasNoErrors();
        }
        $this->assertSame(5, ClubApplication::where('user_id', $student->id)->where('status', 'submitted')->count(), 'UKM selain kerohanian bebas dipilih.');

        $other = $this->user('anggota');
        Membership::create(['club_id' => $catholic->club_id, 'user_id' => $other->id, 'joined_at' => now()]);
        $application = ClubApplication::create(['recruitment_id' => $christian->id, 'user_id' => $other->id, 'motivation' => $motivation['motivation'], 'status' => 'submitted']);
        $this->actingAs($this->user('admin'))->post('/tinjauan/ukm/'.$application->id, ['decision' => 'accepted'])->assertSessionHasErrors('action');
        $this->assertSame('submitted', $application->fresh()->status);
    }

    public function test_category_rules_for_creating_and_editing_organizations(): void
    {
        $base = ['description' => 'Organisasi uji.', 'mentor' => 'Pembina uji', 'contact' => 'kontak uji'];
        $this->actingAs($this->user('admin'));
        $this->post('/kelola/ukm', $base + ['name' => 'HIMAJU Tanpa Jurusan', 'category' => 'himaju'])->assertSessionHasErrors('action');
        $this->post('/kelola/ukm', $base + ['name' => 'HIMAJU Farmasi Kedua', 'category' => 'himaju', 'department_id' => Department::where('code', 'FAR')->value('id')])->assertSessionHasErrors('action');
        $this->post('/kelola/ukm', $base + ['name' => 'UKM Tanpa Bidang', 'category' => 'ukm'])->assertSessionHasErrors('action');
        $this->post('/kelola/ukm', $base + ['name' => 'UKM Catur', 'category' => 'ukm', 'field' => 'Olahraga', 'department_id' => Department::where('code', 'KEP')->value('id')])->assertRedirect();
        $this->assertDatabaseHas('clubs', ['name' => 'UKM Catur', 'category' => 'ukm', 'department_id' => null]);
        $this->assertSame(0, Club::whereIn('name', ['HIMAJU Tanpa Jurusan', 'HIMAJU Farmasi Kedua', 'UKM Tanpa Bidang'])->count());

        $club = Club::where('name', 'UKM Olahraga')->first();
        $this->actingAs($this->user('pengurus'))->post('/kelola/ukm/'.$club->id, $base + ['category' => 'lembaga', 'field' => 'Olahraga'])->assertSessionHasNoErrors();
        $this->assertSame('ukm', $club->fresh()->category, 'Pengurus tidak dapat mengubah kategori.');
        $this->post('/kelola/ukm/'.$club->id, $base + ['field' => ''])->assertSessionHasErrors('action');
    }
}
