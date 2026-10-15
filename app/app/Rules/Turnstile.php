<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Cloudflare Turnstile check. Skipped while TURNSTILE_SECRET_KEY is empty (local development).
 */
class Turnstile implements ValidationRule
{
    /** Run even when the token field is missing, so the check cannot be skipped by omitting it. */
    public bool $implicit = true;

    public static function enabled(): bool
    {
        return filled(config('services.turnstile.site_key')) && filled(config('services.turnstile.secret_key'));
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::enabled()) {
            return;
        }
        if (! is_string($value) || $value === '') {
            $fail('Selesaikan verifikasi keamanan Cloudflare terlebih dahulu.');

            return;
        }

        $response = Http::asForm()->timeout(8)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'secret' => config('services.turnstile.secret_key'),
            'response' => $value,
            'remoteip' => request()->ip(),
        ]);
        if (! $response->successful() || $response->json('success') !== true) {
            $fail('Verifikasi keamanan gagal. Muat ulang halaman lalu coba lagi.');
        }
    }
}
