<?php

namespace App\Services\Event;

use App\Models\Assignment;
use App\Models\Competition;
use App\Models\CompetitionAccount;
use App\Models\User;
use App\Notifications\BrudelNotice;
use App\Services\DomainService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Logins generated for every competition of an approved event: one pengelola lomba, one operator for scores
 * and live score, and up to five juri (rubric competitions). Only the event proposer and kemahasiswaan
 * (superadmin) can see or reset them; both actions are audited. Access ends 30 days after the competition.
 */
class CompetitionAccountService extends DomainService
{
    /**
     * @return list<array{0: string, 1: int}> role and slot of every account the competition needs
     */
    public function required(Competition $competition): array
    {
        $slots = [['pengelola_lomba', 1], ['operator', 1]];
        if ($competition->assessment_type === 'rubric') {
            for ($slot = 1; $slot <= min($competition->judge_count, (int) config('brudel.max_judges')); $slot++) {
                $slots[] = ['penilai', $slot];
            }
        }

        return $slots;
    }

    /**
     * Create missing accounts, extend validity to the competition dates, and retire accounts no longer needed.
     */
    public function provision(Competition $competition, ?User $actor = null): void
    {
        $competition->loadMissing('event');
        if ($competition->event?->status !== 'approved') {
            return;
        }

        DB::transaction(function () use ($competition, $actor) {
            Competition::whereKey($competition->id)->lockForUpdate()->first();
            $required = collect($this->required($competition))->map(fn ($pair) => $pair[0].':'.$pair[1]);
            $existing = CompetitionAccount::with('user')->where('competition_id', $competition->id)->get()->keyBy(fn ($a) => $a->role.':'.$a->slot);
            $created = 0;

            foreach ($required as $key) {
                [$role, $slot] = explode(':', $key);
                $account = $existing->get($key) ?? $this->create($competition, $role, (int) $slot);
                $created += $account->wasRecentlyCreated ? 1 : 0;
                $account->user->forceFill(['active' => true])->save();
                $this->assignment($account)->update(['starts_at' => $competition->event->decided_at ?? now(), 'ends_at' => $competition->staffUntil(), 'ended_reason' => null]);
            }
            foreach ($existing->reject(fn ($account, $key) => $required->contains($key)) as $retired) {
                $retired->user->forceFill(['active' => false])->save();
                $this->assignment($retired)->where('ends_at', '>', now())->update(['ends_at' => now(), 'ended_reason' => 'Tidak diperlukan lagi oleh pengaturan lomba.']);
                DB::table('sessions')->where('user_id', $retired->user_id)->delete();
            }

            $owner = $competition->event->proposer;
            if ($created > 0 && ($actor ?? $owner)) {
                $this->audit($actor ?? $owner, 'competition_accounts.provisioned', $competition, ['created' => $created]);
                $owner?->notify(new BrudelNotice('Akun lomba dibuat', $created.' akun untuk '.$competition->name.' siap dibagikan. Lihat di halaman event.', '/kelola/lomba/'.$competition->id.'/akun'));
            }
        });
    }

    public function canView(User $user, Competition $competition): bool
    {
        $competition->loadMissing('event');

        return $user->isStudentAffairs() || ($competition->event && $competition->event->proposed_by === $user->id);
    }

    /**
     * @return Collection<int, CompetitionAccount>
     */
    public function reveal(User $viewer, Competition $competition): Collection
    {
        abort_unless($this->canView($viewer, $competition), 403);
        $accounts = $competition->accounts()->with('user')->get();
        $this->audit($viewer, 'competition_accounts.viewed', $competition, ['accounts' => $accounts->pluck('id')->all()]);

        return $accounts;
    }

    public function rotate(User $actor, CompetitionAccount $account): string
    {
        abort_unless($this->canView($actor, $account->competition), 403);
        $password = $this->password();
        DB::transaction(function () use ($actor, $account, $password) {
            $account->user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            $account->update(['secret' => $password, 'rotated_at' => now()]);
            DB::table('sessions')->where('user_id', $account->user_id)->delete();
            $this->audit($actor, 'competition_accounts.rotated', $account, ['role' => $account->role, 'slot' => $account->slot]);
        });

        return $password;
    }

    private function create(Competition $competition, string $role, int $slot): CompetitionAccount
    {
        $password = $this->password();
        $suffix = ['pengelola_lomba' => 'pengelola', 'operator' => 'operator', 'penilai' => 'juri'.$slot][$role];
        $label = ['pengelola_lomba' => 'Pengelola', 'operator' => 'Operator', 'penilai' => 'Juri '.$slot][$role];
        $user = User::create([
            'name' => $label.' · '.Str::limit($competition->name, 60, ''),
            'email' => 'lomba'.$competition->id.'-'.$suffix.'@'.config('brudel.competition_account_domain'),
            'password' => $password,
            'active' => true,
            'email_verified_at' => now(),
        ]);
        Assignment::create([
            'user_id' => $user->id,
            'role' => $role,
            'competition_id' => $competition->id,
            'starts_at' => now(),
            'ends_at' => $competition->staffUntil(),
            'created_by' => $competition->event->proposed_by,
        ]);

        return CompetitionAccount::create(['competition_id' => $competition->id, 'user_id' => $user->id, 'role' => $role, 'slot' => $slot, 'secret' => $password])->setRelation('user', $user);
    }

    private function assignment(CompetitionAccount $account): Builder
    {
        return Assignment::where('user_id', $account->user_id)->where('role', $account->role)->where('competition_id', $account->competition_id);
    }

    /**
     * Readable random password: no symbols, no look-alike characters.
     */
    private function password(): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $password = '';
        for ($i = 0; $i < 12; $i++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return preg_match('/\d/', $password) && preg_match('/[a-z]/i', $password) ? $password : $this->password();
    }
}
