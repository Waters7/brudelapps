<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Campus\SystemSettings;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class SystemSettingsTest extends DemoTestCase
{
    public function test_direktorat_changes_allowlisted_env_values_with_encrypted_secrets(): void
    {
        $this->actingAs($this->user('jurusan'))->get('/kelola/sistem')->assertForbidden();
        $this->post('/kelola/sistem', ['settings' => ['mail_host' => 'smtp.jahat.test']])->assertForbidden();

        $this->actingAs($this->user('admin'))->get('/kelola/sistem')->assertOk()->assertSee('MAIL_HOST')->assertSee('DB_CONNECTION')
            ->assertDontSee('name="settings[DB_CONNECTION]"', false)->assertDontSee('APP_KEY=', false);
        $this->post('/kelola/sistem', ['settings' => ['mail_port' => 'bukan-angka']])->assertSessionHasErrors('action');
        $this->post('/kelola/sistem', ['settings' => [
            'mail_mailer' => 'smtp', 'mail_host' => 'smtp.kampus.ac.id', 'mail_port' => '587', 'mail_password' => 'Rahasia-SMTP-2026', 'mail_from_address' => 'brudel@kampus.ac.id',
        ]])->assertSessionHasNoErrors();

        $this->assertSame(['smtp.kampus.ac.id', 587, 'Rahasia-SMTP-2026'], [config('mail.mailers.smtp.host'), config('mail.mailers.smtp.port'), config('mail.mailers.smtp.password')]);
        $stored = Setting::whereKey('system.mail_password')->value('value');
        $this->assertNotSame('Rahasia-SMTP-2026', $stored);
        $this->assertSame('Rahasia-SMTP-2026', Crypt::decryptString($stored));
        $this->get('/kelola/sistem')->assertDontSee('Rahasia-SMTP-2026')->assertSee('Tersimpan · isi untuk mengganti');
        $this->post('/kelola/sistem', ['settings' => ['mail_password' => '']])->assertSessionHasNoErrors();
        $this->assertSame($stored, Setting::whereKey('system.mail_password')->value('value'), 'Isian rahasia kosong tidak menghapus nilai tersimpan.');
        $this->assertDatabaseHas('audit_logs', ['action' => 'system.settings_updated']);
        $this->assertStringNotContainsString('Rahasia', (string) DB::table('audit_logs')->where('action', 'system.settings_updated')->value('details'));
    }

    public function test_turnstile_from_the_menu_guards_login_and_can_be_reset_from_the_server(): void
    {
        $this->get('/login')->assertDontSee('cf-turnstile', false);
        $this->actingAs($this->user('admin'))->post('/kelola/sistem', ['settings' => ['turnstile_site_key' => 'site-dari-menu', 'turnstile_secret_key' => 'secret-dari-menu']])->assertSessionHasNoErrors();
        $this->post('/logout');
        $this->get('/login')->assertSee('site-dari-menu');

        $this->artisan('brudel:pengaturan-sistem')->expectsOutputToContain('turnstile_site_key')->assertSuccessful();
        $this->artisan('brudel:pengaturan-sistem', ['--reset' => ['all']])->assertSuccessful();
        $this->assertFalse(Setting::where('key', 'like', 'system.%')->exists());

        Setting::create(['key' => 'system.pangi_base_url', 'value' => 'https://pangi.kampus.test']);
        app(SystemSettings::class)->apply();
        $this->assertSame('https://pangi.kampus.test', config('services.pangi.base_url'));
        $this->actingAs($this->user('admin'))->post('/kelola/sistem', ['reset' => ['pangi_base_url']])->assertSessionHasNoErrors();
        $this->assertNull(config('services.pangi.base_url'));
        $this->post('/kelola/sistem/uji-email')->assertSessionHasNoErrors();
    }
}
