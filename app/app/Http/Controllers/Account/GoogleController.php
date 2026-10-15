<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\Account\AccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Google sign-in (OpenID Connect authorization code flow with state and PKCE) using the HTTP client,
 * so no extra dependency is needed. Disabled until GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET are set.
 */
class GoogleController extends Controller
{
    private const AUTHORIZE = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN = 'https://oauth2.googleapis.com/token';

    private const USERINFO = 'https://openidconnect.googleapis.com/v1/userinfo';

    public function redirect(Request $request): RedirectResponse
    {
        abort_unless($this->enabled(), 404);
        $state = Str::random(40);
        $verifier = Str::random(64);
        $request->session()->put('google_oauth', ['state' => $state, 'verifier' => $verifier]);

        return redirect()->away(self::AUTHORIZE.'?'.http_build_query(array_filter([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => $this->callbackUrl(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
            'prompt' => 'select_account',
            'hd' => config('services.google.allowed_domain'),
        ])));
    }

    public function callback(Request $request, AccountService $accounts): RedirectResponse
    {
        abort_unless($this->enabled(), 404);
        $expected = $request->session()->pull('google_oauth');
        if (! $expected || ! hash_equals($expected['state'], (string) $request->query('state')) || ! $request->filled('code')) {
            return redirect('/login')->withErrors(['email' => 'Masuk dengan Google dibatalkan atau kedaluwarsa. Coba lagi.']);
        }

        $token = Http::asForm()->timeout(10)->post(self::TOKEN, [
            'code' => $request->query('code'),
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => $this->callbackUrl(),
            'grant_type' => 'authorization_code',
            'code_verifier' => $expected['verifier'],
        ]);
        $profile = $token->successful() ? Http::withToken((string) $token->json('access_token'))->timeout(10)->get(self::USERINFO) : null;
        if (! $profile?->successful() || ! $profile->json('sub') || ! $profile->json('email')) {
            return redirect('/login')->withErrors(['email' => 'Google tidak dapat memverifikasi akun Anda. Coba lagi.']);
        }

        try {
            $user = $accounts->userForGoogle([
                'sub' => (string) $profile->json('sub'),
                'email' => (string) $profile->json('email'),
                'email_verified' => (bool) $profile->json('email_verified'),
            ]);
        } catch (ValidationException $e) {
            return redirect('/login')->withErrors(['email' => $e->validator->errors()->first()]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    private function enabled(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    private function callbackUrl(): string
    {
        return config('services.google.redirect') ?: url('/auth/google/callback');
    }
}
