<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Account\AccountService;
use App\Services\PrivateFiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    public function __construct(private AccountService $accounts) {}

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
     * Profile photos are visible to signed-in campus users only, never publicly.
     */
    public function show(User $user, PrivateFiles $files): StreamedResponse
    {
        abort_unless($user->photo_path, 404);

        return $files->inlineImage($user->photo_path, $user->photo_disk);
    }
}
