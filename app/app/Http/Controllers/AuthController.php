<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Rules\Turnstile;
use App\Services\Account\AccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    private const NEUTRAL = 'Jika email terdaftar dan memenuhi syarat, tautan telah dikirim. Periksa kotak masuk email kampus Anda.';

    public function __construct(private AccountService $accounts) {}

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => 'required|string|max:190', 'password' => 'required|string', 'cf-turnstile-response' => [new Turnstile]]);
        // Students may sign in with their NIM and staff with a username; the account is still found by its email.
        $identifier = trim($data['email']);
        $email = str_contains($identifier, '@') ? mb_strtolower($identifier)
            : User::where('nim', $identifier)->orWhere('username', mb_strtolower($identifier))->value('email');
        if (! $email || ! Auth::attempt(['email' => $email, 'password' => $data['password'], 'active' => true])) {
            throw ValidationException::withMessages(['email' => 'Email/NIM atau kata sandi tidak sesuai, atau akun nonaktif.']);
        }
        $user = $request->user();
        if (! $user->email_verified_at && ! $user->must_change_password) {
            Auth::logout();
            throw ValidationException::withMessages(['email' => 'Akun belum diaktivasi. Gunakan menu Aktivasi akun.']);
        }
        $request->session()->regenerate();

        return $user->must_change_password ? redirect('/sandi-baru') : redirect()->intended('/');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    public function requestActivation(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => 'required|email|max:190', 'cf-turnstile-response' => [new Turnstile]]);
        $this->accounts->sendActivation($data['email']);

        return back()->with('success', self::NEUTRAL);
    }

    public function activationForm(Request $request, string $token): View
    {
        return view('auth.set-password', ['token' => $token, 'email' => (string) $request->query('email'), 'mode' => 'activation']);
    }

    public function activate(Request $request, string $token): RedirectResponse
    {
        $data = $request->validate(['email' => 'required|email', 'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()]]);
        $this->accounts->activate($data['email'], $token, $data['password']);

        return redirect('/login')->with('success', 'Akun aktif. Silakan masuk dengan kata sandi baru.');
    }

    public function requestReset(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => 'required|email|max:190', 'cf-turnstile-response' => [new Turnstile]]);
        $this->accounts->sendReset($data['email']);

        return back()->with('success', self::NEUTRAL);
    }

    public function resetForm(Request $request, string $token): View
    {
        return view('auth.set-password', ['token' => $token, 'email' => (string) $request->query('email'), 'mode' => 'reset']);
    }

    public function reset(Request $request, string $token): RedirectResponse
    {
        $data = $request->validate(['email' => 'required|email', 'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()]]);
        $this->accounts->resetPassword($data['email'], $token, $data['password']);

        return redirect('/login')->with('success', 'Kata sandi diperbarui. Silakan masuk kembali.');
    }
}
