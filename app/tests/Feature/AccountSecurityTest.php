<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\CompetitionAccount;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class AccountSecurityTest extends DemoTestCase
{
    public function test_password_change_requires_current_password_and_ends_other_sessions(): void
    {
        $student = $this->user('mahasiswa');
        DB::table('sessions')->insert(['id' => 'perangkat-lain', 'user_id' => $student->id, 'payload' => '', 'last_activity' => time()]);
        $this->actingAs($student)->post('/profil/sandi', ['current_password' => 'salah', 'password' => 'SandiBaru2026x', 'password_confirmation' => 'SandiBaru2026x'])->assertSessionHasErrors('action');
        $this->post('/profil/sandi', ['current_password' => 'BrudelDemo!2026', 'password' => 'pendek', 'password_confirmation' => 'pendek'])->assertSessionHasErrors('password');
        $this->post('/profil/sandi', ['current_password' => 'BrudelDemo!2026', 'password' => 'SandiBaru2026x', 'password_confirmation' => 'SandiBaru2026x'])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('sessions', ['id' => 'perangkat-lain']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.password_changed', 'subject_id' => $student->id]);
        $this->post('/logout');
        $this->post('/login', ['email' => $student->email, 'password' => 'SandiBaru2026x'])->assertRedirect('/');

        $generated = CompetitionAccount::where('role', 'operator')->first()->user;
        $this->actingAs($generated)->get('/profil')->assertOk()->assertSee('akun lomba yang dibuat otomatis');
        $this->post('/profil/sandi', ['current_password' => 'apa saja', 'password' => 'SandiBaru2026x', 'password_confirmation' => 'SandiBaru2026x'])->assertSessionHasErrors('action');
    }

    public function test_profile_photo_is_reencoded_private_and_visible_to_signed_in_users_only(): void
    {
        Storage::fake('local');
        $student = $this->user('mahasiswa');
        $this->actingAs($student)->post('/profil/foto', ['photo' => UploadedFile::fake()->createWithContent('foto.png', '<?php echo 1;')])->assertSessionHasErrors('photo');
        $this->post('/profil/foto', ['photo' => UploadedFile::fake()->image('foto.png', 640, 480)])->assertSessionHasNoErrors();
        $student->refresh();
        $this->assertStringEndsWith('.jpg', $student->photo_path);
        $this->assertSame("\xFF\xD8", substr(Storage::disk('local')->get($student->photo_path), 0, 2));
        $this->assertSame([320, 320], array_slice(getimagesizefromstring(Storage::disk('local')->get($student->photo_path)), 0, 2));

        $this->actingAs($this->user('anggota'))->get('/foto/'.$student->id)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->get('/ukm/'.Club::where('name', 'UKM Olahraga')->value('id'))->assertOk();
        $this->post('/logout');
        $this->get('/foto/'.$student->id)->assertRedirect('/login');
        $this->get('/mahasiswa/bintang-mandagi-demo')->assertOk()->assertDontSee('/foto/'.$student->id);

        $old = $student->photo_path;
        $this->actingAs($student)->post('/profil/foto/hapus')->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($old);
    }

    /** @var array<string, mixed> */
    private array $googleProfile = [];

    private bool $turnstileSuccess = false;

    private function enableGoogle(): void
    {
        config(['services.google' => ['client_id' => 'client-uji', 'client_secret' => 'rahasia-uji', 'redirect' => null, 'allowed_domain' => null]]);
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'token-uji']),
            'openidconnect.googleapis.com/*' => fn () => Http::response($this->googleProfile),
        ]);
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private function googleReturns(array $profile): string
    {
        $this->googleProfile = $profile;
        $this->get('/auth/google')->assertRedirectContains('accounts.google.com');

        return session('google_oauth')['state'];
    }

    public function test_google_sign_in_links_existing_campus_accounts_only(): void
    {
        $this->get('/auth/google')->assertNotFound();
        $this->enableGoogle();
        $this->get('/login')->assertSee('Masuk dengan Google');

        $state = $this->googleReturns(['sub' => 'google-123', 'email' => 'BARU@demo.brudel.test', 'email_verified' => true]);
        $this->get('/auth/google/callback?state=salah&code=abc')->assertRedirect('/login');
        $this->assertGuest();

        $state = $this->googleReturns(['sub' => 'google-123', 'email' => 'BARU@demo.brudel.test', 'email_verified' => true]);
        $this->get('/auth/google/callback?state='.$state.'&code=abc')->assertRedirect('/');
        $student = User::where('email', 'baru@demo.brudel.test')->first();
        $this->assertAuthenticatedAs($student);
        $this->assertSame('google-123', $student->google_sub);
        $this->assertNotNull($student->email_verified_at, 'Akun hasil impor teraktivasi lewat email Google yang terverifikasi.');
        Http::assertSent(fn ($request) => str_contains($request->url(), 'oauth2.googleapis.com') && filled($request['code_verifier']));
        $this->post('/logout');

        $state = $this->googleReturns(['sub' => 'google-999', 'email' => 'orang-luar@gmail.com', 'email_verified' => true]);
        $this->get('/auth/google/callback?state='.$state.'&code=abc')->assertRedirect('/login')->assertSessionHasErrors('email');
        $state = $this->googleReturns(['sub' => 'google-777', 'email' => 'baru@demo.brudel.test', 'email_verified' => true]);
        $this->get('/auth/google/callback?state='.$state.'&code=abc')->assertSessionHasErrors('email');
        $this->assertGuest();

        config(['services.google.allowed_domain' => 'poltekkesmanado.ac.id']);
        $state = $this->googleReturns(['sub' => 'google-123', 'email' => 'baru@demo.brudel.test', 'email_verified' => true]);
        $this->get('/auth/google/callback?state='.$state.'&code=abc')->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_turnstile_blocks_login_without_a_valid_cloudflare_token(): void
    {
        $this->post('/login', ['email' => 'mahasiswa@demo.brudel.test', 'password' => 'BrudelDemo!2026'])->assertRedirect('/');
        $this->post('/logout');

        config(['services.turnstile' => ['site_key' => 'site-uji', 'secret_key' => 'secret-uji']]);
        $this->get('/login')->assertSee('cf-turnstile', false)->assertSee('challenges.cloudflare.com/turnstile', false);
        $this->post('/login', ['email' => 'mahasiswa@demo.brudel.test', 'password' => 'BrudelDemo!2026'])->assertSessionHasErrors('cf-turnstile-response');
        Http::fake(['challenges.cloudflare.com/*' => fn () => Http::response(['success' => $this->turnstileSuccess])]);
        $this->post('/login', ['email' => 'mahasiswa@demo.brudel.test', 'password' => 'BrudelDemo!2026', 'cf-turnstile-response' => 'token-palsu'])->assertSessionHasErrors('cf-turnstile-response');
        $this->post('/aktivasi', ['email' => 'baru@demo.brudel.test', 'cf-turnstile-response' => 'token-palsu'])->assertSessionHasErrors('cf-turnstile-response');
        $this->assertGuest();

        $this->turnstileSuccess = true;
        $this->post('/login', ['email' => 'mahasiswa@demo.brudel.test', 'password' => 'BrudelDemo!2026', 'cf-turnstile-response' => 'token-sah'])->assertRedirect('/');
        Http::assertSent(fn ($request) => $request['secret'] === 'secret-uji' && $request['response'] === 'token-sah');
    }

    public function test_account_menu_offers_profile_password_and_logout(): void
    {
        $this->actingAs($this->user('mahasiswa'))->get('/')->assertOk()
            ->assertSee('Ubah kata sandi')->assertSee('action="/logout"', false)->assertSee('Keluar');
    }
}
