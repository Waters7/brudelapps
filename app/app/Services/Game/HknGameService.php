<?php

namespace App\Services\Game;

use App\Models\GameReward;
use App\Models\GameRun;
use App\Models\User;
use App\Services\DomainService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HknGameService extends DomainService
{
    public const GAME = 'hkn-62-v1';

    public const POINTS = 62;

    public const RULES_VERSION = 2;

    public const BADGE = 'Finisher 62 KM — HKN ke-62';

    // Everyone gets the same course, so ranking does not depend on random luck.
    public const SEED = 6262026;

    public function authorize(User $user): void
    {
        abort_unless($user->active && ! $user->isCompetitionAccount(), 403, 'Gunakan akun pribadi BRUDEL yang aktif.');
    }

    public function start(User $user): GameRun
    {
        $this->authorize($user);

        return DB::transaction(function () use ($user) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            // A fresh attempt replaces unfinished attempts, including those in other tabs.
            GameRun::where('user_id', $user->id)->where('game', self::GAME)->whereNull('finished_at')->delete();

            return GameRun::create(['token' => (string) Str::uuid(), 'user_id' => $user->id, 'game' => self::GAME,
                'seed' => self::SEED, 'rules_version' => self::RULES_VERSION, 'started_at' => now()]);
        });
    }

    public function finish(User $user, string $token, int $ticks, array $jumps, array $answers = []): array
    {
        $this->authorize($user);

        return DB::transaction(function () use ($user, $token, $ticks, $jumps, $answers) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $run = GameRun::where('token', $token)->where('user_id', $user->id)->where('game', self::GAME)->lockForUpdate()->firstOrFail();
            $reward = GameReward::where('user_id', $user->id)->where('game', self::GAME)->first();
            if ($run->finished_at) {
                return ['awarded' => false, 'reward' => $reward];
            }
            $elapsed = $run->started_at->diffInMilliseconds(now(), false);
            $this->ensure((int) $run->rules_version === self::RULES_VERSION, 'Aturan game diperbarui. Muat ulang dan mulai permainan baru.');
            $this->ensure($elapsed >= $ticks * 1000 / 60 - 500 && $elapsed <= 3600000, 'Waktu permainan tidak valid atau sesi sudah kedaluwarsa. Mulai ulang permainan.');
            $last = 0;
            foreach ($jumps as $jump) {
                $this->ensure(is_int($jump) && $jump > $last && $jump <= $ticks, 'Rekaman lompatan tidak valid.');
                $last = $jump;
            }
            $last = 0;
            foreach ($answers as $answer) {
                $this->ensure(is_int($answer['tick']) && $answer['tick'] > $last && $answer['tick'] <= $ticks && is_int($answer['choice']) && $answer['choice'] >= 0 && $answer['choice'] <= 2, 'Rekaman jawaban tidak valid.');
                $last = $answer['tick'];
            }
            $result = app(HknEngine::class)->replay($run->seed, $ticks, $jumps, $answers);
            $this->ensure($result['won'], 'Lintasan belum selesai dengan sah. Hadiah belum diberikan.');
            $awarded = ! $reward;
            if (! $reward) {
                $reward = GameReward::create(['user_id' => $user->id, 'game' => self::GAME, 'points' => self::POINTS,
                    'badge' => self::BADGE, 'best_ticks' => $ticks, 'best_rules_version' => self::RULES_VERSION, 'earned_at' => now(), 'best_at' => now()]);
                $this->audit($user, 'game.rewarded', $reward, ['points' => self::POINTS, 'game' => self::GAME]);
            } elseif ((int) $reward->best_rules_version !== self::RULES_VERSION || $ticks < $reward->best_ticks) {
                $reward->update(['best_ticks' => $ticks, 'best_rules_version' => self::RULES_VERSION, 'best_at' => now()]);
            }
            $run->update(['finished_at' => now(), 'ticks' => $ticks]);

            return ['awarded' => $awarded, 'reward' => $reward];
        });
    }

    public function setFrame(User $user, bool $enabled): void
    {
        $this->authorize($user);
        $this->ensure(! $enabled || GameReward::where('user_id', $user->id)->where('game', self::GAME)->exists(), 'Selesaikan 62 km untuk membuka frame HKN ke-62.');
        DB::transaction(function () use ($user, $enabled) {
            $user->forceFill(['profile_frame' => $enabled ? self::GAME : null])->save();
            $this->audit($user, 'game.frame_changed', $user, ['frame' => $user->profile_frame]);
        });
    }
}
