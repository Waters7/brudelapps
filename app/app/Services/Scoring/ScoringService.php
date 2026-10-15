<?php

namespace App\Services\Scoring;

use App\Models\Assignment;
use App\Models\Competition;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\RubricCriterion;
use App\Models\Score;
use App\Models\User;
use App\Services\DomainService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rubric scoring. Final score = average over judges of Σ(score × weight / 100), scores 0–100.
 * Ties: tie_break "criteria" compares criterion averages by descending weight; otherwise ranks are shared.
 */
class ScoringService extends DomainService
{
    /**
     * @param  list<array{name: string, weight: float|string, description?: ?string}>  $criteria
     */
    public function saveCriteria(User $actor, Competition $competition, array $criteria): void
    {
        abort_unless($actor->managesCompetition($competition), 403);
        $this->ensure($competition->assessment_type === 'rubric', 'Nomor lomba ini tidak menggunakan rubrik.');
        $criteria = array_values(array_filter($criteria, fn ($row) => trim($row['name'] ?? '') !== ''));
        $this->ensure(count($criteria) >= 1, 'Tambahkan minimal satu kriteria.');
        $total = array_sum(array_map(fn ($row) => round((float) $row['weight'], 2), $criteria));
        $this->ensure(abs($total - 100) < 0.001, 'Total bobot kriteria harus 100%. Saat ini '.rtrim(rtrim(number_format($total, 2, ',', ''), '0'), ',').'%.');
        foreach ($criteria as $row) {
            $this->ensure((float) $row['weight'] > 0, 'Bobot setiap kriteria harus lebih dari 0.');
        }

        DB::transaction(function () use ($actor, $competition, $criteria) {
            Competition::whereKey($competition->id)->lockForUpdate()->first();
            $this->ensure(! Score::where('competition_id', $competition->id)->exists(), 'Rubrik terkunci karena penilaian sudah dimulai.');
            RubricCriterion::where('competition_id', $competition->id)->delete();
            foreach ($criteria as $index => $row) {
                RubricCriterion::create([
                    'competition_id' => $competition->id,
                    'name' => trim($row['name']),
                    'description' => $row['description'] ?? null,
                    'weight' => round((float) $row['weight'], 2),
                    'position' => $index + 1,
                ]);
            }
            $this->audit($actor, 'rubric.saved', $competition, ['criteria' => $criteria]);
        });
    }

    /**
     * @param  array<int|string, array{score: float|string|null, note?: ?string}>  $entries  criterion id => score and note
     */
    public function score(User $actor, Competition $competition, Registration $registration, array $entries): void
    {
        abort_unless($actor->active && $actor->judges($competition), 403);
        abort_if($registration->competition_id !== $competition->id, 404);
        abort_if($registration->participants()->where('user_id', $actor->id)->exists(), 403, 'Penilai tidak boleh menilai diri atau timnya sendiri.');

        DB::transaction(function () use ($actor, $competition, $registration, $entries) {
            $competition = Competition::lockForUpdate()->findOrFail($competition->id);
            $this->ensure(! $competition->resultsPublished(), 'Hasil sudah disahkan. Nilai terkunci.');
            $this->ensure($registration->fresh()->status === 'accepted', 'Hanya peserta yang diterima yang dapat dinilai.');
            $criteria = $competition->criteria()->get();
            $this->ensure($criteria->isNotEmpty() && abs($criteria->sum('weight') - 100) < 0.001, 'Rubrik belum lengkap (total bobot 100%).');
            foreach ($criteria as $criterion) {
                $value = $entries[$criterion->id]['score'] ?? null;
                if ($value === null || $value === '') {
                    continue;
                }
                $this->ensure(is_numeric($value) && $value >= 0 && $value <= 100, 'Nilai '.$criterion->name.' harus 0–100.');
                Score::updateOrCreate(
                    ['registration_id' => $registration->id, 'judge_id' => $actor->id, 'rubric_criterion_id' => $criterion->id],
                    ['competition_id' => $competition->id, 'score' => round((float) $value, 2), 'note' => $entries[$criterion->id]['note'] ?? null],
                );
            }
            $this->audit($actor, 'score.saved', $registration, ['entries' => $entries]);
        }, 3);
    }

    /**
     * Judges assigned to the competition right now.
     *
     * @return Collection<int, int>
     */
    public function judgeIds(Competition $competition): Collection
    {
        return Assignment::where('role', 'penilai')->where('competition_id', $competition->id)
            ->where('starts_at', '<=', now())->where('ends_at', '>', now())->pluck('user_id')->unique()->values();
    }

    /**
     * Missing (judge, entry, criterion) combinations. Conflicted pairs (judge in the team) are excluded.
     *
     * @return list<string>
     */
    public function missing(Competition $competition): array
    {
        $criteria = $competition->criteria()->get();
        $judges = $this->judgeIds($competition);
        if ($criteria->isEmpty()) {
            return ['Rubrik belum disusun.'];
        }
        if ($judges->isEmpty()) {
            return ['Belum ada penilai yang ditugaskan.'];
        }
        $entries = $competition->registrations()->where('status', 'accepted')->with('user')->get();
        $members = Participant::whereIn('registration_id', $entries->pluck('id'))->get()->groupBy('registration_id');
        $scores = Score::where('competition_id', $competition->id)->get()->groupBy(fn ($score) => $score->registration_id.'-'.$score->judge_id);
        $names = User::whereIn('id', $judges)->pluck('name', 'id');

        $missing = [];
        foreach ($entries as $entry) {
            $eligible = $judges->reject(fn ($judge) => ($members[$entry->id] ?? collect())->contains('user_id', $judge));
            if ($eligible->isEmpty()) {
                $missing[] = $entry->displayName().': tidak ada penilai yang bebas konflik kepentingan.';
            }
            foreach ($eligible as $judge) {
                $count = ($scores[$entry->id.'-'.$judge] ?? collect())->count();
                if ($count < $criteria->count()) {
                    $missing[] = $entry->displayName().' belum dinilai lengkap oleh '.$names[$judge].' ('.$count.'/'.$criteria->count().' kriteria).';
                }
            }
        }

        return $missing;
    }

    /**
     * @return list<array{registration: Registration, total: float, rank: int, note: ?string}>
     */
    public function ranking(Competition $competition): array
    {
        $criteria = $competition->criteria()->get()->keyBy('id');
        $entries = $competition->registrations()->where('status', 'accepted')->with('user')->get();
        $scores = Score::where('competition_id', $competition->id)->get()->groupBy('registration_id');

        $rows = [];
        foreach ($entries as $entry) {
            $byJudge = ($scores[$entry->id] ?? collect())->groupBy('judge_id');
            if ($byJudge->isEmpty()) {
                continue;
            }
            $totals = $byJudge->map(fn ($judgeScores) => $judgeScores->sum(fn ($score) => $score->score * ($criteria[$score->rubric_criterion_id]->weight ?? 0) / 100));
            $perCriterion = ($scores[$entry->id])->groupBy('rubric_criterion_id')->map(fn ($group) => $group->avg('score'));
            $rows[] = ['registration' => $entry, 'total' => round($totals->avg(), 3), 'criteria' => $perCriterion, 'note' => null];
        }

        $tieOrder = $criteria->sortBy([['weight', 'desc'], ['position', 'asc']])->keys();
        $useCriteria = $competition->tie_break === 'criteria';
        usort($rows, function ($a, $b) use ($tieOrder, $useCriteria) {
            if ($a['total'] !== $b['total']) {
                return $b['total'] <=> $a['total'];
            }
            if ($useCriteria) {
                foreach ($tieOrder as $criterionId) {
                    $difference = round(($b['criteria'][$criterionId] ?? 0) - ($a['criteria'][$criterionId] ?? 0), 3);
                    if ($difference != 0) {
                        return $difference <=> 0;
                    }
                }
            }

            return 0;
        });

        $ranked = [];
        foreach ($rows as $index => $row) {
            $previous = $ranked[$index - 1] ?? null;
            $sameTotal = $previous && $previous['total'] === $row['total'];
            $tied = $sameTotal && (! $useCriteria || $this->sameCriteria($previous['criteria'], $row['criteria'], $tieOrder));
            $rank = $tied ? $previous['rank'] : $index + 1;
            if ($sameTotal && ! $tied) {
                $row['note'] = 'Nilai sama, diputus kriteria berbobot terbesar.';
            }
            if ($tied) {
                $row['note'] = 'Seri — peringkat bersama.';
                $ranked[$index - 1]['note'] = 'Seri — peringkat bersama.';
            }
            $row['rank'] = $rank;
            $ranked[] = $row;
        }

        return array_map(fn ($row) => ['registration' => $row['registration'], 'total' => $row['total'], 'rank' => $row['rank'], 'note' => $row['note']], $ranked);
    }

    /**
     * @param  Collection<int, float>  $a
     * @param  Collection<int, float>  $b
     * @param  Collection<int, int>  $order
     */
    private function sameCriteria(Collection $a, Collection $b, Collection $order): bool
    {
        return $order->every(fn ($id) => round(($a[$id] ?? 0) - ($b[$id] ?? 0), 3) == 0);
    }
}
