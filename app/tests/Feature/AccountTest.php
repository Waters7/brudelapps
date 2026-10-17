<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Club;
use App\Models\StudentImport;
use App\Models\User;
use App\Notifications\AccountLink;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class AccountTest extends DemoTestCase
{
    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('mahasiswa.csv', $content);
    }

    public function test_csv_import_validates_rows_and_preview_writes_nothing(): void
    {
        $csv = "nim;nama;email;prodi;status\n"
            ."2026001;Ana Lumi;ana@kampus.test;KEP;aktif\n"
            ."12;NIM Pendek;pendek@kampus.test;KEP;aktif\n"
            ."2026001;Duplikat;lain@kampus.test;KEP;aktif\n"
            ."2026003;Prodi Salah;salah@kampus.test;TIDAKADA;aktif\n"
            ."2026004;Email Terpakai;mahasiswa@demo.brudel.test;GIZ;aktif\n"
            ."DEMO009;Nama Baru Demo;baru@demo.brudel.test;D-IV Gizi dan Dietetika;nonaktif\n";
        $admin = $this->user('admin');

        $this->actingAs($admin)->post('/kelola/mahasiswa/impor', ['file' => $this->csv($csv), 'mode' => 'preview'])->assertSessionHasNoErrors();
        $preview = StudentImport::latest('id')->first();
        $this->assertSame(['preview', 6, 4], [$preview->mode, $preview->total_rows, $preview->failed_count]);
        $this->assertSame([3, 4, 5, 6], array_column($preview->row_errors, 'line'));
        $this->assertDatabaseMissing('users', ['nim' => '2026001']);

        $this->post('/kelola/mahasiswa/impor', ['file' => $this->csv($csv), 'mode' => 'commit'])->assertSessionHasNoErrors();
        $commit = StudentImport::latest('id')->first();
        $this->assertSame([1, 1, 4], [$commit->created_count, $commit->updated_count, $commit->failed_count]);
        $created = User::where('nim', '2026001')->first();
        $this->assertNull($created->email_verified_at);
        $this->assertSame('KEP', $created->program->code);
        $this->assertFalse(User::where('nim', 'DEMO009')->first()->active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'students.imported', 'subject_id' => $commit->id]);

        $this->actingAs($this->user('pengurus'))->post('/kelola/mahasiswa/impor', ['file' => $this->csv($csv), 'mode' => 'commit'])->assertForbidden();
    }

    public function test_activation_link_sets_password_once_and_does_not_reveal_accounts(): void
    {
        Notification::fake();
        $student = User::where('nim', 'DEMO009')->first();
        $this->post('/aktivasi', ['email' => 'baru@demo.brudel.test'])->assertSessionHas('success');
        $this->post('/aktivasi', ['email' => 'tidak-ada@demo.brudel.test'])->assertSessionHas('success');
        $this->post('/aktivasi', ['email' => 'mahasiswa@demo.brudel.test'])->assertSessionHas('success');

        $token = null;
        Notification::assertSentTo($student, AccountLink::class, function (AccountLink $notification) use (&$token) {
            $token = $notification->token;

            return $notification->purpose === 'activation';
        });
        Notification::assertSentTimes(AccountLink::class, 1);

        $this->post('/login', ['email' => 'baru@demo.brudel.test', 'password' => 'RahasiaBaru2026'])->assertSessionHasErrors('email');
        $this->post('/aktivasi/salah-token', ['email' => 'baru@demo.brudel.test', 'password' => 'RahasiaBaru2026', 'password_confirmation' => 'RahasiaBaru2026'])->assertSessionHasErrors('action');
        $this->post('/aktivasi/'.$token, ['email' => 'baru@demo.brudel.test', 'password' => 'RahasiaBaru2026', 'password_confirmation' => 'RahasiaBaru2026'])->assertRedirect('/login');
        $this->assertNotNull($student->fresh()->email_verified_at);
        $this->post('/aktivasi/'.$token, ['email' => 'baru@demo.brudel.test', 'password' => 'LainLagi20260', 'password_confirmation' => 'LainLagi20260'])->assertSessionHasErrors('action');

        // Activated by email, but still without an active mobile number: the account is completed first.
        $this->post('/login', ['email' => 'baru@demo.brudel.test', 'password' => 'RahasiaBaru2026'])->assertRedirect('/sandi-baru');
        $this->post('/sandi-baru', ['email' => 'baru@demo.brudel.test', 'phone' => '081277778888'])->assertRedirect('/');
        $this->get('/')->assertOk();
    }

    public function test_password_reset_replaces_password_and_ends_sessions(): void
    {
        Notification::fake();
        $student = $this->user('mahasiswa');
        $this->post('/lupa-sandi', ['email' => $student->email])->assertSessionHas('success');
        $token = null;
        Notification::assertSentTo($student, AccountLink::class, function (AccountLink $notification) use (&$token) {
            $token = $notification->token;

            return $notification->purpose === 'reset';
        });
        DB::table('sessions')->insert(['id' => 'lama', 'user_id' => $student->id, 'payload' => '', 'last_activity' => time()]);
        $this->post('/reset-sandi/'.$token, ['email' => $student->email, 'password' => 'SandiBaru2026x', 'password_confirmation' => 'SandiBaru2026x'])->assertRedirect('/login');
        $this->assertDatabaseMissing('sessions', ['id' => 'lama']);
        $this->post('/login', ['email' => $student->email, 'password' => 'BrudelDemo!2026'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $student->email, 'password' => 'SandiBaru2026x'])->assertRedirect('/');
    }

    public function test_student_status_change_needs_reason_and_blocks_login(): void
    {
        $admin = $this->user('admin');
        $student = $this->user('anggota');
        $this->actingAs($admin)->post('/kelola/mahasiswa/'.$student->id.'/status', ['active' => 0, 'reason' => ''])->assertSessionHasErrors('reason');
        $this->post('/kelola/mahasiswa/'.$student->id.'/status', ['active' => 0, 'reason' => 'Cuti akademik semester ini'])->assertSessionHasNoErrors();
        $this->assertFalse($student->fresh()->active);
        $this->assertDatabaseHas('status_histories', ['subject_type' => 'users', 'subject_id' => $student->id, 'to_status' => 'inactive']);
        $this->post('/kelola/mahasiswa/'.$admin->id.'/status', ['active' => 0, 'reason' => 'Mencoba akun sendiri'])->assertForbidden();
        $this->post('/logout');
        $this->post('/login', ['email' => $student->email, 'password' => 'BrudelDemo!2026'])->assertSessionHasErrors('email');
    }

    public function test_student_search_ignores_letter_case_on_every_database(): void
    {
        $this->actingAs($this->user('admin'));
        foreach (['demo001', 'MAHASISWA@DEMO', 'bintang MANDAGI'] as $query) {
            $this->get('/kelola/mahasiswa?q='.urlencode($query))->assertOk()->assertSee('Bintang Mandagi')->assertDontSee('Michelle Sumual');
        }
    }

    public function test_role_assignment_scope_rules(): void
    {
        $badminton = Club::where('name', 'UKM Olahraga')->first();
        $choir = Club::where('name', 'UKM Manado Poltekkes Choir')->first();
        $window = ['starts_at' => now()->format('Y-m-d\TH:i'), 'ends_at' => now()->addMonths(6)->format('Y-m-d\TH:i')];
        $chair = $this->user('pengurus');

        $this->actingAs($chair)->post('/kelola/penugasan', ['user' => 'DEMO001', 'role' => 'pengurus', 'scope_id' => $badminton->id, 'position' => 'Sekretaris'] + $window)->assertSessionHasErrors('action');
        $this->post('/kelola/penugasan', ['user' => 'DEMO004', 'role' => 'pengurus', 'scope_id' => $badminton->id, 'position' => 'Sekretaris'] + $window)->assertSessionHasNoErrors();
        $this->assertTrue($this->user('atlet1')->managesClub($badminton->id));
        $this->post('/kelola/penugasan', ['user' => 'DEMO004', 'role' => 'pengurus', 'scope_id' => $badminton->id] + $window)->assertSessionHasErrors('action');
        $this->actingAs($this->user('atlet1'))->post('/kelola/penugasan', ['user' => 'DEMO005', 'role' => 'pengurus', 'scope_id' => $badminton->id, 'position' => 'Bendahara'] + $window)->assertSessionHasNoErrors();
        $this->actingAs($this->user('atlet2'))->post('/kelola/penugasan', ['user' => 'DEMO006', 'role' => 'pengurus', 'scope_id' => $badminton->id] + $window)->assertForbidden();
        $this->actingAs($chair);
        $this->post('/kelola/penugasan', ['user' => 'DEMO002', 'role' => 'pengurus', 'scope_id' => $choir->id] + $window)->assertForbidden();
        $this->post('/kelola/penugasan', ['user' => 'DEMO002', 'role' => 'admin_event', 'scope_id' => 1] + $window)->assertForbidden();
        $this->post('/kelola/penugasan', ['user' => 'DEMO003', 'role' => 'pengurus', 'scope_id' => $badminton->id] + $window)->assertForbidden();
        $this->assertFalse($chair->managesEvent(1));

        $tennis = $this->competition('Tenis Meja Tunggal');
        $this->actingAs($this->user('event'))->post('/kelola/penugasan', ['user' => 'DEMO002', 'role' => 'operator', 'scope_id' => $tennis->id] + $window)->assertSessionHasNoErrors();
        $this->post('/kelola/penugasan', ['user' => 'DEMO002', 'role' => 'pimpinan'] + $window)->assertForbidden();

        $own = Assignment::where('user_id', $this->user('admin')->id)->where('role', 'kemahasiswaan')->first();
        $this->actingAs($this->user('admin'));
        $this->post('/kelola/penugasan', ['user' => 'pimpinan@demo.brudel.test', 'role' => 'kemahasiswaan'] + $window)->assertSessionHasNoErrors();
        $only = Assignment::where('role', 'kemahasiswaan')->where('user_id', $this->user('pimpinan')->id)->first();
        $this->post('/kelola/penugasan/'.$own->id.'/akhiri', ['reason' => 'Mengakhiri sendiri'])->assertForbidden();
        Assignment::whereKey($own->id)->update(['ends_at' => now()->subMinute()]);
        $this->actingAs($this->user('pimpinan'))->post('/kelola/penugasan/'.$only->id.'/akhiri', ['reason' => 'Tidak boleh habis'])->assertForbidden();
    }

    public function test_login_attempts_are_rate_limited_per_account(): void
    {
        foreach (range(1, 6) as $attempt) {
            $this->post('/login', ['email' => 'mahasiswa@demo.brudel.test', 'password' => 'salah-'.$attempt])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => 'mahasiswa@demo.brudel.test', 'password' => 'BrudelDemo!2026'])->assertStatus(429);
        $this->post('/login', ['email' => 'anggota@demo.brudel.test', 'password' => 'BrudelDemo!2026'])->assertRedirect('/');
    }

    public function test_expired_assignment_removes_access(): void
    {
        $badminton = Club::where('name', 'UKM Olahraga')->first();
        $chair = $this->user('pengurus');
        $this->actingAs($chair)->get('/kelola/ukm/'.$badminton->id)->assertOk();
        Assignment::where('user_id', $chair->id)->update(['ends_at' => now()->subMinute()]);
        $this->get('/kelola/ukm/'.$badminton->id)->assertForbidden();
    }
}
