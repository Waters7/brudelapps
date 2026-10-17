<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Account\AccountService;
use App\Services\PrivateFiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    public function __construct(private AccountService $accounts) {}

    public function initialPasswordForm(Request $request): View|RedirectResponse
    {
        return $request->user()->needsAccountSetup() ? view('auth.initial-password', ['user' => $request->user()]) : redirect('/');
    }

    /**
     * Completes the account on first sign-in: active email, active mobile number, and a new password if still initial.
     */
    public function initialPassword(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $this->validateContact($request, [
            'password' => [$user->must_change_password ? 'required' : 'nullable', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);
        $this->accounts->completeSetup($user, $data['email'], $data['phone'], $data['password'] ?? null, $request->session()->getId());
        $request->session()->regenerate();

        return redirect('/')->with('success', 'Akun lengkap. Selamat datang di BRUDEL!');
    }

    public function contact(Request $request): RedirectResponse
    {
        $data = $this->validateContact($request);
        $this->accounts->updateContact($request->user(), $data['email'], $data['phone']);

        return back()->with('success', 'Email dan nomor HP diperbarui.');
    }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);
        $this->accounts->changePassword($request->user(), $data['current_password'], $data['password'], $request->session()->getId());
        $request->session()->regenerate();

        return back()->with('success', 'Kata sandi diperbarui. Sesi di perangkat lain telah diakhiri.');
    }

    public function photo(Request $request): RedirectResponse
    {
        $request->validate(['photo' => PrivateFiles::PHOTO_RULES]);
        $this->accounts->updatePhoto($request->user(), $request->file('photo'));

        return back()->with('success', 'Foto profil diperbarui.');
    }

    public function removePhoto(Request $request): RedirectResponse
    {
        $this->accounts->removePhoto($request->user());

        return back()->with('success', 'Foto profil dihapus.');
    }

    /**
     * Active email (not a placeholder from an import, not used by another account) and an Indonesian mobile number.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function validateContact(Request $request, array $extra = []): array
    {
        $request->merge([
            'email' => mb_strtolower(trim((string) $request->input('email'))),
            'phone' => AccountService::normalizePhone((string) $request->input('phone')),
        ]);

        return $request->validate([
            'email' => ['required', 'email', 'max:190', 'not_regex:/\.invalid$/', Rule::unique('users', 'email')->ignore($request->user()->id)],
            'phone' => ['required', 'regex:/^08[0-9]{8,12}$/'],
        ] + $extra, [
            'email.not_regex' => 'Gunakan email aktif yang dapat menerima pesan.',
            'email.unique' => 'Email tersebut sudah dipakai akun lain.',
            'phone.regex' => 'Nomor HP tidak valid. Contoh: 081234567890.',
        ]);
    }

    /**
     * Profile photos are visible to signed-in campus users only, never publicly.
     */
    public function show(User $user, PrivateFiles $files): StreamedResponse
    {
        abort_unless($user->photo_path, 404);

        return $files->inlineImage($user->photo_path, $user->photo_disk);
    }
}
