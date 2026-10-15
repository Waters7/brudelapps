<?php

namespace App\Services\Account;

use App\Models\User;
use App\Notifications\AccountLink;
use App\Services\DomainService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Account module: activation of imported students, password recovery and active/inactive status.
 * Tokens are hashed in password_reset_tokens; responses never reveal whether an email exists.
 */
class AccountService extends DomainService
{
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
