<?php

namespace App\Services\Match;

use App\Models\Competition;
use App\Models\Matchup;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\ScoreUpdate;
use App\Models\User;
use App\Services\DomainService;
use App\Services\Wita;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Single-elimination brackets: seeding, byes, scheduling, winner propagation and audited corrections.
 */
class BracketService extends DomainService
{
    /**
     * Standard seeding order for a power-of-two bracket, e.g. 8 → [1, 8, 4, 5, 2, 7, 3, 6].
     *
     * @return list<int>
     */
    public static function seedOrder(int $size): array
    {
        $order = [1];
        while (count($order) < $size) {
            $next = [];
            $total = count($order) * 2 + 1;
            foreach ($order as $seed) {
                $next[] = $seed;
                $next[] = $total - $seed;
            }
            $order = $next;
        }

        return $order;
    }

    /**
     * @param  array<int|string, ?int>  $seeds  registration id => seed number (null for unseeded)
     */
    public function setSeeds(User $actor, Competition $competition, array $seeds): void
    {
        abort_unless($actor->managesCompetition($competition), 403);
        $this->ensure($competition->assessment_type === 'bracket', 'Nomor lomba ini tidak menggunakan bagan.');
        $this->ensure(! $competition->matchups()->exists(), 'Bagan sudah dibuat. Unggulan dikunci.');
        $values = array_values(array_filter($seeds, fn ($seed) => $seed !== null && $seed !== ''));
        $this->ensure(count($values) === count(array_unique($values)), 'Nomor unggulan tidak boleh sama.');

        DB::transaction(function () use ($actor, $competition, $seeds) {
            Competition::whereKey($competition->id)->lockForUpdate()->first();
            foreach ($seeds as $registrationId => $seed) {
                $competition->registrations()->whereKey($registrationId)->where('status', 'accepted')
                    ->update(['seed' => $seed === null || $seed === '' ? null : max(1, (int) $seed)]);
            }
            $this->audit($actor, 'bracket.seeded', $competition, ['seeds' => $seeds]);
        });
    }

    public function generate(User $actor, Competition $competition): void
    {
        abort_unless($actor->managesCompetition($competition), 403);

        DB::transaction(function () use ($actor, $competition) {
            $competition = Competition::lockForUpdate()->findOrFail($competition->id);
            $this->ensure($competition->assessment_type === 'bracket', 'Nomor lomba ini tidak menggunakan bagan.');
            $this->ensure(! $competition->matchups()->exists(), 'Bagan sudah dibuat.');
            $entrants = $this->orderedEntrants($competition);
            $this->ensure($entrants->count() >= 2, 'Minimal dua peserta diterima untuk membuat bagan.');

            $size = 2 ** (int) ceil(log($entrants->count(), 2));
            $rounds = (int) log($size, 2);
            $matches = [];
            for ($round = $rounds; $round >= 1; $round--) {
                $count = $size / (2 ** $round);
                for ($position = 1; $position <= $count; $position++) {
                    $next = $round < $rounds ? $matches[$round + 1][(int) ceil($position / 2)] : null;
                    $matches[$round][$position] = Matchup::create([
                        'competition_id' => $competition->id,
                        'round' => $round,
                        'position' => $position,
                        'next_matchup_id' => $next?->id,
                        'next_slot' => $next ? ($position % 2 === 1 ? 'a' : 'b') : null,
                        'location' => $competition->location,
                        'status' => 'awaiting',
                    ]);
                }
            }

            $slots = self::seedOrder($size);
            foreach ($matches[1] as $position => $match) {
                $seedA = $slots[($position - 1) * 2];
                $seedB = $slots[($position - 1) * 2 + 1];
                $a = $entrants->get($seedA - 1);
                $b = $entrants->get($seedB - 1);
                $match->update([
                    'registration_a_id' => $a?->id,
                    'registration_b_id' => $b?->id,
                    'seed_a' => $a?->seed,
                    'seed_b' => $b?->seed,
                    'status' => 'scheduled',
                ]);
                if (! $a || ! $b) {
                    $winner = $a ?? $b;
                    $match->update(['is_bye' => true, 'status' => 'finished', 'winner_registration_id' => $winner->id]);
                    $this->advance($match->fresh(), $winner->id);
                }
            }
            $this->audit($actor, 'bracket.generated', $competition, ['entrants' => $entrants->count(), 'size' => $size]);
        });
    }

    public function schedule(User $actor, Matchup $matchup, ?string $scheduledAt, ?string $location): void
    {
        abort_unless($actor->operates($matchup->competition), 403);
        DB::transaction(function () use ($actor, $matchup, $scheduledAt, $location) {
            $matchup = Matchup::lockForUpdate()->findOrFail($matchup->id);
            $matchup->update(['scheduled_at' => Wita::toUtc($scheduledAt), 'location' => $location]);
            $this->audit($actor, 'match.scheduled', $matchup);
        });
    }

    /**
     * Record the official winner. The expected version prevents silently overwriting a concurrent update.
     */
    public function recordWinner(User $actor, Matchup $matchup, int $winnerId, ?int $scoreA, ?int $scoreB, ?string $detail, int $version): void
    {
        $competition = $matchup->competition;
        abort_unless($actor->operates($competition), 403);

        DB::transaction(function () use ($actor, $matchup, $competition, $winnerId, $scoreA, $scoreB, $detail, $version) {
            Competition::whereKey($competition->id)->lockForUpdate()->first();
            $matchup = Matchup::lockForUpdate()->findOrFail($matchup->id);
            $this->guardConflict($actor, $matchup);
            $this->ensure(! $competition->fresh()->resultsPublished(), 'Hasil sudah disahkan. Buka koreksi terlebih dahulu.');
            $this->ensure($matchup->registration_a_id && $matchup->registration_b_id, 'Kedua peserta pertandingan belum lengkap.');
            $this->ensure(in_array($winnerId, [$matchup->registration_a_id, $matchup->registration_b_id], true), 'Pemenang harus salah satu peserta pertandingan.');
            $this->ensure($matchup->status !== 'finished', 'Hasil sudah resmi. Gunakan koreksi beralasan.');
            $this->ensure($matchup->version === $version, 'Data pertandingan sudah diperbarui petugas lain. Muat ulang halaman sebelum menyimpan.');

            $matchup->update([
                'winner_registration_id' => $winnerId,
                'score_a' => $scoreA,
                'score_b' => $scoreB,
                'score_detail' => $detail,
                'status' => 'finished',
                'version' => $version + 1,
                'updated_by' => $actor->id,
            ]);
            $this->advance($matchup, $winnerId);
            $this->log($actor, $matchup, 'winner', ['winner' => $winnerId, 'score_a' => $scoreA, 'score_b' => $scoreB, 'detail' => $detail]);
            $this->audit($actor, 'match.winner', $matchup, ['winner' => $winnerId]);
        }, 3);
    }

    /**
     * Reset an official result with a reason. Refused when a later round already uses the winner.
     */
    public function correct(User $actor, Matchup $matchup, string $reason): void
    {
        $competition = $matchup->competition;
        abort_unless($actor->managesCompetition($competition), 403);
        $this->ensure(mb_strlen(trim($reason)) >= 5, 'Alasan koreksi minimal 5 karakter.');

        DB::transaction(function () use ($actor, $matchup, $competition, $reason) {
            Competition::whereKey($competition->id)->lockForUpdate()->first();
            $matchup = Matchup::lockForUpdate()->findOrFail($matchup->id);
            $this->ensure(! $competition->fresh()->resultsPublished(), 'Hasil sudah disahkan. Buka koreksi hasil terlebih dahulu.');
            $this->ensure($matchup->status === 'finished' && ! $matchup->is_bye, 'Hanya hasil pertandingan resmi yang dapat dikoreksi.');
            if ($matchup->next_matchup_id) {
                $next = Matchup::lockForUpdate()->findOrFail($matchup->next_matchup_id);
                $this->ensure(! in_array($next->status, ['live', 'finished'], true) && $next->score_a === null && $next->score_b === null,
                    'Koreksi berdampak pada babak '.$next->round.' pertandingan '.$next->position.' yang sudah berjalan. Koreksi pertandingan tersebut terlebih dahulu.');
                $next->update([$matchup->next_slot === 'a' ? 'registration_a_id' : 'registration_b_id' => null, 'status' => 'awaiting', 'version' => $next->version + 1]);
            }
            $previous = $matchup->only(['winner_registration_id', 'score_a', 'score_b', 'score_detail']);
            $matchup->update(['winner_registration_id' => null, 'status' => 'live', 'version' => $matchup->version + 1, 'updated_by' => $actor->id]);
            $this->log($actor, $matchup, 'correction', $previous, $reason);
            $this->audit($actor, 'match.corrected', $matchup, ['previous' => $previous, 'reason' => $reason]);
        }, 3);
    }

    /**
     * Accepted registrations ordered for seeding: seeded entries first, then by submission time.
     *
     * @return Collection<int, Registration>
     */
    private function orderedEntrants(Competition $competition): Collection
    {
        $accepted = $competition->registrations()->where('status', 'accepted')->get();

        return $accepted->sortBy([
            fn ($a, $b) => ($a->seed === null) <=> ($b->seed === null),
            fn ($a, $b) => ($a->seed ?? PHP_INT_MAX) <=> ($b->seed ?? PHP_INT_MAX),
            fn ($a, $b) => $a->submitted_at <=> $b->submitted_at,
            fn ($a, $b) => $a->id <=> $b->id,
        ])->values();
    }

    private function advance(Matchup $matchup, int $winnerId): void
    {
        if (! $matchup->next_matchup_id) {
            return;
        }
        $next = Matchup::lockForUpdate()->findOrFail($matchup->next_matchup_id);
        $seed = Registration::find($winnerId)?->seed;
        $next->update($matchup->next_slot === 'a'
            ? ['registration_a_id' => $winnerId, 'seed_a' => $seed, 'version' => $next->version + 1]
            : ['registration_b_id' => $winnerId, 'seed_b' => $seed, 'version' => $next->version + 1]);
        if ($next->registration_a_id && $next->registration_b_id && $next->status === 'awaiting') {
            $next->update(['status' => 'scheduled']);
        }
    }

    private function guardConflict(User $actor, Matchup $matchup): void
    {
        $sides = array_filter([$matchup->registration_a_id, $matchup->registration_b_id]);
        abort_if(Participant::whereIn('registration_id', $sides)->where('user_id', $actor->id)->exists(), 403, 'Petugas tidak dapat mencatat pertandingan yang diikutinya.');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function log(User $actor, Matchup $matchup, string $action, array $payload, ?string $reason = null): void
    {
        ScoreUpdate::create([
            'competition_id' => $matchup->competition_id,
            'matchup_id' => $matchup->id,
            'user_id' => $actor->id,
            'action' => $action,
            'payload' => $payload,
            'version' => $matchup->version,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }
}
