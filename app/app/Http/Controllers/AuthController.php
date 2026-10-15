<?php

namespace App\Http\Controllers;

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
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt([...$data, 'active' => true])) {
            throw ValidationException::withMessages(['email' => 'Email atau kata sandi tidak sesuai, atau akun nonaktif.']);
        }
        if (! $request->user()->email_verified_at) {
            Auth::logout();
            throw ValidationException::withMessages(['email' => 'Akun belum diaktivasi. Gunakan menu Aktivasi akun.']);
        }
        $request->session()->regenerate();

        return redirect()->intended('/');
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
        $data = $request->validate(['email' => 'required|email|max:190']);
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
        $data = $request->validate(['email' => 'required|email|max:190']);
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
