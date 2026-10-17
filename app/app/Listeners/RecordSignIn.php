<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;

/**
 * Every sign-in (password or Google) lands in the audit log, for Pengaturan › Pengguna.
 */
class RecordSignIn
{
    public function handle(Login $event): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => $event->user->getAuthIdentifier(),
            'action' => 'auth.login',
            'subject_type' => 'users',
            'subject_id' => $event->user->getAuthIdentifier(),
            'details' => json_encode(['ip' => request()->ip(), 'agent' => mb_substr((string) request()->userAgent(), 0, 255)]),
            'created_at' => now(),
        ]);
    }
}
