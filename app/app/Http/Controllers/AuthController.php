<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
class AuthController extends Controller {
    public function login(Request $request) {
        $data=$request->validate(['email'=>'required|email','password'=>'required|string']);
        if (!Auth::attempt([...$data,'active'=>true])) throw ValidationException::withMessages(['email'=>'Email atau kata sandi tidak sesuai, atau akun nonaktif.']);
        if (!$request->user()->email_verified_at) { Auth::logout(); throw ValidationException::withMessages(['email'=>'Akun belum diaktivasi oleh kampus.']); }
        $request->session()->regenerate();
        return redirect()->intended('/');
    }
    public function logout(Request $request) {
        Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
        return redirect('/login');
    }
}
