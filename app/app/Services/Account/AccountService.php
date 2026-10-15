<?php

namespace App\Services\Account;

use App\Models\User;
use App\Notifications\AccountLink;
use App\Services\DomainService;
use App\Services\PrivateFiles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Account module: activation of imported students, password recovery and active/inactive status.
 * Tokens are hashed in password_reset_tokens; responses never reveal whether an email exists.
 */
class AccountService extends DomainService
{
    public function __construct(private PrivateFiles $files) {}

    /**
     * Change the password of a signed-in user and end every other session of that user.
     */
    public function changePassword(User $user, string $current, string $new, ?string $keepSession): void
    {
        $this->ensure(! $user->isCompetitionAccount(), 'Kata sandi akun lomba diatur oleh pengaju lomba atau kemahasiswaan.');
        $this->ensure(Hash::check($current, $user->password), 'Kata sandi saat ini tidak sesuai.');
        $this->ensure(! Hash::check($new, $user->password), 'Kata sandi baru harus berbeda dari kata sandi lama.');
        DB::transaction(function () use ($user, $new, $keepSession) {
            $user->forceFill(['password' => $new, 'remember_token' => Str::random(60)])->save();
            DB::table('sessions')->where('user_id', $user->id)->when($keepSession, fn ($q) => $q->where('id', '!=', $keepSession))->delete();
            $this->audit($user, 'account.password_changed', $user);
        });
    }

    public function updatePhoto(User $user, UploadedFile $photo): void
    {
        $stored = $this->files->storePhoto($photo, 'photos/'.$user->id);
        $previous = [$user->photo_path, $user->photo_disk];
        DB::transaction(function () use ($user, $stored) {
            $user->forceFill(['photo_path' => $stored[0], 'photo_disk' => $stored[1]])->save();
            $this->audit($user, 'account.photo_updated', $user);
        });
        $this->files->delete(...$previous);
    }

    public function removePhoto(User $user): void
    {
        $previous = [$user->photo_path, $user->photo_disk];
        $user->forceFill(['photo_path' => null, 'photo_disk' => null])->save();
        $this->audit($user, 'account.photo_removed', $user);
        $this->files->delete(...$previous);
    }

    /**
     * Sign in with a verified Google identity. Accounts come only from official campus data, so Google never
     * creates users; it links by verified email the first time and by Google subject afterwards.
     *
     * @param  array{sub: string, email: string, email_verified: bool}  $profile
     */
    public function userForGoogle(array $profile): User
    {
        $this->ensure($profile['email_verified'], 'Email Google belum terverifikasi.');
        $email = mb_strtolower($profile['email']);
        $domain = config('services.google.allowed_domain');
        $this->ensure(! $domain || str_ends_with($email, '@'.ltrim($domain, '@')), 'Gunakan akun Google dengan domain kampus ('.$domain.').');

        return DB::transaction(function () use ($profile, $email) {
            $user = User::where('google_sub', $profile['sub'])->lockForUpdate()->first()
                ?? User::where('email', $email)->lockForUpdate()->first();
            $this->ensure($user !== null && $user->active, 'Akun dengan email ini tidak terdaftar atau nonaktif. Hubungi kemahasiswaan.');
            $this->ensure($user->google_sub === null || $user->google_sub === $profile['sub'], 'Email ini sudah tertaut ke akun Google lain.');
            $this->ensure(! $user->isCompetitionAccount(), 'Akun lomba tidak dapat masuk dengan Google.');
            $changes = ['google_sub' => $profile['sub']];
            if ($user->email_verified_at === null) {
                $changes['email_verified_at'] = now();
                $this->history($user, 'imported', 'activated', $user, 'Aktivasi melalui Google');
            }
            $user->forceFill($changes)->save();
            $this->audit($user, 'account.google_login', $user);

            return $user;
        });
    }

    public function sendActivation(string $email): void
    {
        $user = User::where('email', mb_strtolower(trim($email)))->where('active', true)
            ->whereNotNull('nim')->whereNull('email_verified_at')->first();
        if ($user && $this->issueToken($user)) {
            $user->notify(new AccountLink($this->lastToken, 'activation'));
        }
    }

    public function activate(string $email, string $token, string $password): User
    {
        return DB::transaction(function () use ($email, $token, $password) {
            $user = $this->consumeToken($email, $token, (int) config('brudel.activation_expire_minutes'));
            $this->ensure($user !== null && $user->email_verified_at === null && $user->active, 'Tautan aktivasi tidak valid atau sudah kedaluwarsa.');
            $user->forceFill(['password' => $password, 'email_verified_at' => now(), 'remember_token' => Str::random(60)])->save();
            $this->history($user, 'imported', 'activated', $user);
            $this->audit($user, 'account.activated', $user);

            return $user;
        });
    }

    public function sendReset(string $email): void
    {
        $user = User::where('email', mb_strtolower(trim($email)))->where('active', true)->whereNotNull('email_verified_at')->first();
        if ($user && $this->issueToken($user)) {
            $user->notify(new AccountLink($this->lastToken, 'reset'));
        }
    }

    public function resetPassword(string $email, string $token, string $password): User
    {
        return DB::transaction(function () use ($email, $token, $password) {
            $user = $this->consumeToken($email, $token, (int) config('auth.passwords.users.expire'));
            $this->ensure($user !== null && $user->email_verified_at !== null && $user->active, 'Tautan pemulihan tidak valid atau sudah kedaluwarsa.');
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $this->audit($user, 'account.password_reset', $user);

            return $user;
        });
    }

    public function setStatus(User $actor, User $student, bool $active, string $reason): void
    {
        abort_unless($actor->isStudentAffairs(), 403);
        abort_if($actor->id === $student->id, 403, 'Tidak dapat mengubah status akun sendiri.');
        $this->ensure(mb_strlen(trim($reason)) >= 5, 'Alasan perubahan status minimal 5 karakter.');
        DB::transaction(function () use ($actor, $student, $active, $reason) {
            $from = $student->active ? 'active' : 'inactive';
            $student->forceFill(['active' => $active, 'status_changed_at' => now()])->save();
            if (! $active) {
                DB::table('sessions')->where('user_id', $student->id)->delete();
            }
            $this->history($student, $from, $active ? 'active' : 'inactive', $actor, $reason);
            $this->audit($actor, $active ? 'account.activated_by_admin' : 'account.deactivated', $student, ['reason' => $reason]);
        });
    }

    private string $lastToken = '';

    /**
     * Store a fresh hashed token unless one was issued within the last minute.
     */
    private function issueToken(User $user): bool
    {
        $existing = DB::table('password_reset_tokens')->where('email', $user->email)->first();
        if ($existing && now()->subMinute()->lt($existing->created_at)) {
            return false;
        }
        $this->lastToken = Str::random(64);
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => Hash::make($this->lastToken), 'created_at' => now()],
        );

        return true;
    }

    private function consumeToken(string $email, string $token, int $minutes): ?User
    {
        $email = mb_strtolower(trim($email));
        $row = DB::table('password_reset_tokens')->where('email', $email)->first();
        if (! $row || ! Hash::check($token, $row->token) || now()->subMinutes($minutes)->gt($row->created_at)) {
            return null;
        }
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        return User::where('email', $email)->lockForUpdate()->first();
    }
}
