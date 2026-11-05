<?php

namespace Tests\Feature;

use App\Models\CompetitionAccount;
use App\Models\User;
use App\Notifications\AccountLink;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Every account can recover or change its password; the first sign-in of an imported student replaces the initial one.
 */
class PasswordRecoveryTest extends DemoTestCase
{
    private const NEW_PASSWORD = 'SandiPulih2026';

    private function tokenFor(User $user): string
    {
        $token = null;
        Notification::assertSentTo($user, AccountLink::class, function (AccountLink $notification) use (&$token) {
            $token = $notification->token;

            return $notification->purpose === 'reset';
        });

        return $token;
    }

    private function neverSignedIn(string $email = 'baru.masuk@kampus.test'): User
    {
        $student = User::create(['name' => 'Mahasiswa Baru', 'nim' => 'SIA0500', 'email' => $email, 'password' => '01012001', 'active' => true]);
        $student->forceFill(['must_change_password' => true])->save();

        return $student;
    }

    public function test_recovery_accepts_nim_email_or_username(): void
    {
        Notification::fake();
        $student = $this->user('mahasiswa');
        $this->post('/lupa-sandi', ['email' => $student->nim])->assertSessionHasNoErrors()->assertSessionHas('success');
        Notification::assertSentTo($student, AccountLink::class);

        $staff = $this->user('jurusan');
        $staff->forceFill(['username' => 'adminkep'])->save();
        $this->post('/lupa-sandi', ['email' => 'AdminKep'])->assertSessionHasNoErrors();
        Notification::assertSentTo($staff, AccountLink::class);
    }

    public function test_a_student_who_never_signed_in_can_recover_and_is_not_asked_to_change_again(): void
    {
        Notification::fake();
        $student = $this->neverSignedIn();
        $this->post('/lupa-sandi', ['email' => $student->nim])->assertSessionHasNoErrors();
        $this->post('/reset-sandi/'.$this->tokenFor($student), ['email' => $student->email, 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD])->assertRedirect('/login');

        $student->refresh();
        $this->assertFalse($student->must_change_password);
        $this->assertNotNull($student->email_verified_at);
        $this->post('/login', ['email' => $student->nim, 'password' => self::NEW_PASSWORD])->assertRedirect('/sandi-baru');
        $this->get('/sandi-baru')->assertOk()->assertDontSee('Kata sandi baru');
    }

    public function test_accounts_without_a_reachable_email_get_the_same_neutral_answer(): void
    {
        Notification::fake();
        $placeholder = $this->neverSignedIn('sia0500@siakad.brudel.invalid');
        $inactive = $this->user('anggota');
        $inactive->forceFill(['active' => false])->save();
        $competition = CompetitionAccount::with('user')->firstOrFail()->user;

        foreach ([$placeholder->nim, $inactive->nim, $competition->email, 'TIDAKADA01'] as $identifier) {
            $this->post('/lupa-sandi', ['email' => $identifier])->assertSessionHasNoErrors()->assertSessionHas('success');
        }
        Notification::assertNothingSent();
    }

    public function test_every_kind_of_user_changes_their_own_password(): void
    {
        foreach (['mahasiswa', 'pengurus', 'pembina', 'jurusan', 'admin', 'juri'] as $role) {
            $user = $this->user($role);
            $this->actingAs($user)->post('/profil/sandi', ['current_password' => 'BrudelDemo!2026', 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD])->assertSessionHasNoErrors();
            $this->post('/logout');
            $this->post('/login', ['email' => $user->email, 'password' => self::NEW_PASSWORD])->assertSessionHasNoErrors();
            $this->post('/logout');
        }
    }

    public function test_a_temporary_password_works_once_and_must_be_replaced(): void
    {
        $student = $this->user('mahasiswa');
        DB::table('sessions')->insert(['id' => 'lama', 'user_id' => $student->id, 'payload' => '', 'last_activity' => time()]);
        $response = $this->actingAs($this->user('jurusan'))->post('/kelola/akun/'.$student->id.'/sandi-sementara')->assertSessionHasNoErrors();
        $this->assertSame(1, preg_match('/^Sandi sementara .+: ([A-Za-z0-9]{12}) /', session('success'), $match));

        $this->assertDatabaseMissing('sessions', ['id' => 'lama']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.password_issued', 'subject_id' => $student->id]);
        $this->assertStringNotContainsString($match[1], (string) DB::table('audit_logs')->where('action', 'account.password_issued')->value('details'));

        $this->post('/logout');
        $this->post('/login', ['email' => $student->nim, 'password' => 'BrudelDemo!2026'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $student->nim, 'password' => $match[1]])->assertRedirect('/sandi-baru');
        $this->get('/')->assertRedirect('/sandi-baru');
    }

    public function test_temporary_passwords_stay_within_each_admin_scope(): void
    {
        $this->actingAs($this->user('jurusan'))->post('/kelola/akun/'.$this->user('anggota')->id.'/sandi-sementara')->assertForbidden();
        $this->post('/kelola/akun/'.$this->user('jurusan')->id.'/sandi-sementara')->assertForbidden();
        $this->actingAs($this->user('pengurus'))->post('/kelola/akun/'.$this->user('mahasiswa')->id.'/sandi-sementara')->assertForbidden();

        $this->actingAs($this->user('admin'))->post('/kelola/akun/'.$this->user('jurusan')->id.'/sandi-sementara')->assertSessionHasNoErrors();
        $this->assertTrue($this->user('jurusan')->must_change_password);
        $this->post('/kelola/akun/'.$this->user('admin')->id.'/sandi-sementara')->assertForbidden();
        $this->post('/kelola/akun/'.CompetitionAccount::firstOrFail()->user_id.'/sandi-sementara')->assertSessionHasErrors('action');
    }
}
