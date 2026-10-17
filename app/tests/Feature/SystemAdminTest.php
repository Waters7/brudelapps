<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\User;

class SystemAdminTest extends DemoTestCase
{
    public function test_superadmin_is_created_from_the_console_and_signs_in_with_a_username(): void
    {
        $this->artisan('brudel:buat-admin', ['username' => 'admin', '--password' => 'pendek'])->assertFailed();
        $this->artisan('brudel:buat-admin', ['username' => 'admin', '--password' => 'SandiUjiAdmin2026'])->assertSuccessful();

        $admin = User::where('username', 'admin')->firstOrFail();
        $this->assertTrue($admin->isStudentAffairs());
        $this->assertNull($admin->nim, 'Superadmin bukan akun mahasiswa.');
        // The console placeholder email and the missing mobile number are completed at first sign-in; the password stays.
        $this->post('/login', ['email' => 'ADMIN', 'password' => 'SandiUjiAdmin2026'])->assertRedirect('/sandi-baru');
        $this->get('/sandi-baru')->assertOk()->assertDontSee('Kata sandi baru');
        $this->post('/sandi-baru', ['email' => 'admin.sistem@poltekkes.test', 'phone' => '+62 811-4300-1234'])->assertRedirect('/');
        $this->assertSame(['admin.sistem@poltekkes.test', '081143001234'], [$admin->fresh()->email, $admin->fresh()->phone]);
        $this->get('/kelola/sistem')->assertOk();
        $this->get('/kelola/master')->assertOk();
        $this->post('/logout');

        $this->artisan('brudel:buat-admin', ['username' => 'admin', '--password' => 'SandiBaruAdmin2026'])->assertSuccessful();
        $this->post('/login', ['email' => 'admin', 'password' => 'SandiUjiAdmin2026'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'admin', 'password' => 'SandiBaruAdmin2026'])->assertRedirect('/');
        $this->assertSame(1, Assignment::where('user_id', $admin->id)->where('role', 'kemahasiswaan')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.system_admin_set', 'subject_id' => $admin->id]);
    }
}
