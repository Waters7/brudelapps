<?php

namespace App\Services\Campus;

use App\Models\Setting;
use App\Models\User;
use App\Services\DomainService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Pengaturan Sistem: an allowlist of operational settings that kemahasiswaan direktorat may change from the
 * application instead of the server's .env file. Values live in the settings table (secrets encrypted with
 * APP_KEY) and override the .env values on every request. Infrastructure (APP_KEY, database, debug, storage,
 * sessions, queue) is never editable here: changing it from the web would let a stolen admin account take
 * over the server.
 */
class SystemSettings extends DomainService
{
    public const PREFIX = 'system.';

    /**
     * @var array<string, array{group: string, label: string, config: string, env: string, type: string, options?: array<string, string>, help?: string}>
     */
    public const FIELDS = [
        'app_name' => ['group' => 'Aplikasi', 'label' => 'Nama aplikasi (pengirim dan judul email)', 'config' => 'app.name', 'env' => 'APP_NAME', 'type' => 'text'],
        'competition_account_domain' => ['group' => 'Aplikasi', 'label' => 'Domain email akun lomba otomatis', 'config' => 'brudel.competition_account_domain', 'env' => 'BRUDEL_COMPETITION_ACCOUNT_DOMAIN', 'type' => 'text',
            'help' => 'Berlaku untuk akun lomba yang dibuat setelah diubah.'],
        'mail_mailer' => ['group' => 'Email', 'label' => 'Cara kirim email', 'config' => 'mail.default', 'env' => 'MAIL_MAILER', 'type' => 'select',
            'options' => ['smtp' => 'SMTP (dikirim sungguhan)', 'log' => 'Log saja (tidak dikirim, untuk uji)']],
        'mail_host' => ['group' => 'Email', 'label' => 'Host SMTP', 'config' => 'mail.mailers.smtp.host', 'env' => 'MAIL_HOST', 'type' => 'text'],
        'mail_port' => ['group' => 'Email', 'label' => 'Port SMTP', 'config' => 'mail.mailers.smtp.port', 'env' => 'MAIL_PORT', 'type' => 'number'],
        'mail_scheme' => ['group' => 'Email', 'label' => 'Enkripsi', 'config' => 'mail.mailers.smtp.scheme', 'env' => 'MAIL_SCHEME', 'type' => 'select',
            'options' => ['' => 'Otomatis', 'smtp' => 'STARTTLS (umumnya port 587)', 'smtps' => 'SSL/TLS (umumnya port 465)']],
        'mail_username' => ['group' => 'Email', 'label' => 'Username SMTP', 'config' => 'mail.mailers.smtp.username', 'env' => 'MAIL_USERNAME', 'type' => 'text'],
        'mail_password' => ['group' => 'Email', 'label' => 'Kata sandi SMTP', 'config' => 'mail.mailers.smtp.password', 'env' => 'MAIL_PASSWORD', 'type' => 'secret'],
        'mail_from_address' => ['group' => 'Email', 'label' => 'Alamat pengirim', 'config' => 'mail.from.address', 'env' => 'MAIL_FROM_ADDRESS', 'type' => 'email'],
        'mail_from_name' => ['group' => 'Email', 'label' => 'Nama pengirim', 'config' => 'mail.from.name', 'env' => 'MAIL_FROM_NAME', 'type' => 'text'],
        'google_client_id' => ['group' => 'Masuk dengan Google', 'label' => 'Client ID', 'config' => 'services.google.client_id', 'env' => 'GOOGLE_CLIENT_ID', 'type' => 'text',
            'help' => 'Tombol Google muncul di halaman masuk setelah Client ID dan Client Secret terisi.'],
        'google_client_secret' => ['group' => 'Masuk dengan Google', 'label' => 'Client Secret', 'config' => 'services.google.client_secret', 'env' => 'GOOGLE_CLIENT_SECRET', 'type' => 'secret'],
        'google_redirect' => ['group' => 'Masuk dengan Google', 'label' => 'Redirect URI', 'config' => 'services.google.redirect', 'env' => 'GOOGLE_REDIRECT_URI', 'type' => 'url'],
        'google_allowed_domain' => ['group' => 'Masuk dengan Google', 'label' => 'Batasi domain email (kosong = semua)', 'config' => 'services.google.allowed_domain', 'env' => 'GOOGLE_ALLOWED_DOMAIN', 'type' => 'text'],
        'turnstile_site_key' => ['group' => 'Cloudflare Turnstile', 'label' => 'Site key', 'config' => 'services.turnstile.site_key', 'env' => 'TURNSTILE_SITE_KEY', 'type' => 'text',
            'help' => 'Kunci yang salah membuat semua orang gagal masuk. Admin server dapat memulihkan dengan: php artisan brudel:pengaturan-sistem --reset=turnstile_site_key'],
        'turnstile_secret_key' => ['group' => 'Cloudflare Turnstile', 'label' => 'Secret key', 'config' => 'services.turnstile.secret_key', 'env' => 'TURNSTILE_SECRET_KEY', 'type' => 'secret'],
        'pangi_base_url' => ['group' => 'Integrasi Pangi', 'label' => 'Alamat Pangi', 'config' => 'services.pangi.base_url', 'env' => 'PANGI_BASE_URL', 'type' => 'url'],
        'pangi_token' => ['group' => 'Integrasi Pangi', 'label' => 'Token Pangi', 'config' => 'services.pangi.token', 'env' => 'PANGI_TOKEN', 'type' => 'secret'],
    ];

    /** Shown read-only: changing these needs the server's .env and a restart. */
    public const SERVER_ONLY = [
        'APP_ENV' => 'app.env', 'APP_DEBUG' => 'app.debug', 'APP_URL' => 'app.url', 'DB_CONNECTION' => 'database.default',
        'SESSION_DRIVER' => 'session.driver', 'QUEUE_CONNECTION' => 'queue.default', 'BRUDEL_DOCUMENT_DISK' => 'brudel.document_disk', 'BRUDEL_DEMO' => 'brudel.demo',
    ];

    /** @var array<string, mixed> .env values before overrides, for "kembali ke .env". */
    private array $defaults = [];

    /**
     * Apply stored overrides to the running configuration. Called on every boot; a missing table or a secret
     * that no longer decrypts (APP_KEY rotated) leaves the .env value in place.
     */
    public function apply(): void
    {
        foreach (self::FIELDS as $key => $field) {
            if (! array_key_exists($key, $this->defaults)) {
                $this->defaults[$key] = config($field['config']);
            }
        }
        try {
            $stored = Setting::where('key', 'like', self::PREFIX.'%')->pluck('value', 'key');
        } catch (\Throwable) {
            return;
        }
        foreach (self::FIELDS as $key => $field) {
            if (! $stored->has(self::PREFIX.$key)) {
                continue;
            }
            $value = $stored[self::PREFIX.$key];
            if ($field['type'] === 'secret') {
                try {
                    $value = Crypt::decryptString((string) $value);
                } catch (DecryptException) {
                    continue;
                }
            }
            config([$field['config'] => $field['type'] === 'number' ? (int) $value : ($value === '' ? null : $value)]);
        }
    }

    /**
     * Current state per field for the form; secrets only report whether they are set.
     *
     * @return array<string, array{value: ?string, overridden: bool, set: bool}>
     */
    public function state(): array
    {
        $stored = Setting::where('key', 'like', self::PREFIX.'%')->pluck('value', 'key');

        return collect(self::FIELDS)->map(fn ($field, $key) => [
            'value' => $field['type'] === 'secret' ? null : (string) config($field['config']),
            'overridden' => $stored->has(self::PREFIX.$key),
            'set' => filled(config($field['config'])),
        ])->all();
    }

    /**
     * @param  array<string, ?string>  $input  new values; an empty secret keeps the stored one
     * @param  list<string>  $reset  keys returned to the .env value
     */
    public function save(User $actor, array $input, array $reset = []): void
    {
        abort_unless($actor->isStudentAffairs(), 403);
        $changes = [];
        foreach (self::FIELDS as $key => $field) {
            if (in_array($key, $reset, true)) {
                $changes[$key] = null;

                continue;
            }
            if (! array_key_exists($key, $input)) {
                continue;
            }
            $value = trim((string) $input[$key]);
            if ($field['type'] === 'secret' && $value === '') {
                continue;
            }
            if ($field['type'] !== 'secret' && $value === trim((string) config($field['config']))) {
                continue;
            }
            $this->validate($field, $value);
            $changes[$key] = $field['type'] === 'secret' ? Crypt::encryptString($value) : $value;
        }
        if ($changes === []) {
            return;
        }

        DB::transaction(function () use ($actor, $changes) {
            foreach ($changes as $key => $value) {
                if ($value === null) {
                    Setting::whereKey(self::PREFIX.$key)->delete();
                    config([self::FIELDS[$key]['config'] => $this->defaults[$key] ?? null]);
                } else {
                    Setting::updateOrCreate(['key' => self::PREFIX.$key], ['value' => $value, 'updated_by' => $actor->id, 'updated_at' => now()]);
                }
            }
            $this->audit($actor, 'system.settings_updated', $actor, ['keys' => array_keys($changes)]);
        });
        $this->apply();
        Mail::purge();
    }

    /**
     * Send a short email to the signed-in admin with the current mail settings.
     */
    public function sendTest(User $actor): void
    {
        abort_unless($actor->isStudentAffairs(), 403);
        try {
            Mail::raw('Email uji dari Pengaturan Sistem BRUDEL. Jika pesan ini sampai, pengaturan email sudah benar.', function ($message) use ($actor) {
                $message->to($actor->email)->subject('Email uji BRUDEL');
            });
        } catch (\Throwable $e) {
            $this->ensure(false, 'Email uji gagal: '.mb_substr($e->getMessage(), 0, 300));
        }
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function validate(array $field, string $value): void
    {
        $ok = match ($field['type']) {
            'number' => ctype_digit($value) && (int) $value >= 1 && (int) $value <= 65535,
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'url' => $value === '' || (filter_var($value, FILTER_VALIDATE_URL) !== false && preg_match('#^https?://#', $value)),
            'select' => array_key_exists($value, $field['options']),
            default => mb_strlen($value) <= 190,
        };
        $this->ensure((bool) $ok, $field['label'].': nilai tidak valid.');
    }
}
