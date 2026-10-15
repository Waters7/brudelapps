<?php

use App\Models\Participant;
use App\Notifications\BrudelNotice;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Release team invitations that were never answered once registration has closed, so the
 * invited student is no longer reserved on that competition.
 */
Artisan::command('brudel:expire-invitations', function () {
    $expired = Participant::with('registration.user', 'user')->where('status', 'invited')
        ->whereHas('registration.competition', fn ($q) => $q->where('closes_at', '<', now()))->get();
    foreach ($expired as $participant) {
        DB::transaction(function () use ($participant) {
            DB::table('audit_logs')->insert([
                'user_id' => null,
                'action' => 'team.invitation_expired',
                'subject_type' => 'participants',
                'subject_id' => $participant->id,
                'details' => json_encode(['registration_id' => $participant->registration_id, 'user_id' => $participant->user_id]),
                'created_at' => now(),
            ]);
            $participant->delete();
        });
        $participant->registration->user->notify(new BrudelNotice('Undangan tim kedaluwarsa', $participant->user->name.' tidak menanggapi undangan sebelum pendaftaran ditutup.', '/pendaftaran'));
    }
    $this->info($expired->count().' undangan kedaluwarsa dilepas.');
})->purpose('Lepaskan undangan tim yang tidak ditanggapi setelah pendaftaran ditutup');

Schedule::command('brudel:expire-invitations')->hourly()->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=720')->daily();
