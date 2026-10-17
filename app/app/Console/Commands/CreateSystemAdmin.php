<?php

namespace App\Console\Commands;

use App\Models\Assignment;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Creates (or resets) a kemahasiswaan direktorat superadmin that signs in with a username. The password is
 * asked at runtime and never stored in the repository.
 */
class CreateSystemAdmin extends Command
{
    protected $signature = 'brudel:buat-admin
        {username : Nama pengguna untuk masuk, mis. admin}
        {--name=Admin Sistem BRUDEL : Nama tampilan}
        {--email= : Email untuk pemulihan kata sandi (opsional)}
        {--password= : Kata sandi (tanpa opsi ini ditanyakan secara tersembunyi)}';

    protected $description = 'Buat atau atur ulang akun superadmin (Kemahasiswaan Direktorat) yang masuk dengan username';

    public function handle(): int
    {
        $username = mb_strtolower(trim($this->argument('username')));
        $password = $this->option('password') ?? $this->secret('Kata sandi');
        // Resetting the password keeps the active email the admin confirmed; a new admin starts with a placeholder.
        $email = mb_strtolower(trim((string) $this->option('email'))) ?: (User::where('username', $username)->value('email') ?? $username.'@admin.brudel.invalid');
        $validator = Validator::make(compact('username', 'password', 'email'), [
            'username' => ['required', 'regex:/^[a-z0-9._-]{3,60}$/'],
            'password' => ['required', 'string', Password::min(10)->letters()->numbers()],
            'email' => ['required', 'email', 'max:190'],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        $user = User::where('username', $username)->first();
        if (User::where('email', $email)->when($user, fn ($q) => $q->whereKeyNot($user->id))->exists()) {
            $this->error('Email '.$email.' sudah dipakai akun lain.');

            return self::FAILURE;
        }

        DB::transaction(function () use (&$user, $username, $password, $email) {
            $user ??= new User(['username' => $username]);
            $user->forceFill([
                'name' => $this->option('name'), 'email' => $email, 'password' => Hash::make($password), 'active' => true,
                'email_verified_at' => $user->email_verified_at ?? now(), 'must_change_password' => false,
            ])->save();
            if (! Assignment::where('user_id', $user->id)->where('role', 'kemahasiswaan')->where('starts_at', '<=', now())->where('ends_at', '>', now())->exists()) {
                Assignment::create(['user_id' => $user->id, 'role' => 'kemahasiswaan', 'starts_at' => now(), 'ends_at' => now()->addYears(5), 'created_by' => $user->id]);
            }
            DB::table('audit_logs')->insert(['user_id' => $user->id, 'action' => 'account.system_admin_set', 'subject_type' => 'users', 'subject_id' => $user->id,
                'details' => json_encode(['username' => $username, 'via' => 'console']), 'created_at' => now()]);
        });
        $this->info('Superadmin "'.$username.'" siap. Masuk dengan username dan kata sandi tersebut; segera ganti kata sandinya bila dibagikan ke orang lain.');

        return self::SUCCESS;
    }
}
