<?php

namespace Tests\Feature;

use App\Livewire\LiveBoard;
use App\Models\Assignment;
use App\Models\AwardResult;
use App\Models\Competition;
use App\Models\Matchup;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\ScoreUpdate;
use App\Models\User;
use App\Services\Match\BracketService;
use App\Services\Scoring\ScoringService;
use Livewire\Livewire;

class CompetitionTest extends DemoTestCase
{
    private function generateTennisBracket(): Competition
    {
        $tennis = $this->competition('Tenis Meja Tunggal');
        $this->actingAs($this->user('event'))->post('/kelola/lomba/'.$tennis->id.'/bagan')->assertSessionHasNoErrors();

        return $tennis;
    }

    private function match(Competition $competition, int $round, int $position): Matchup
    {
        return Matchup::where('competition_id', $competition->id)->where('round', $round)->where('position', $position)->firstOrFail();
    }

    public function test_seed_order_places_top_seeds_apart(): void
    {
        $this->assertSame([1, 8, 4, 5, 2, 7, 3, 6], BracketService::seedOrder(8));
        $this->assertSame([1, 4, 2, 3], BracketService::seedOrder(4));
    }

    public function test_bracket_with_byes_advances_winners_and_publishes_ranks(): void
    {
        $tennis = $this->generateTennisBracket();
        $this->assertSame(7, Matchup::where('competition_id', $tennis->id)->count(), '5 peserta → bagan 8 → 7 pertandingan.');
        $this->assertSame(3, Matchup::where('competition_id', $tennis->id)->where('is_bye', true)->count());
        $seedOne = Registration::where('competition_id', $tennis->id)->where('seed', 1)->first();
        $first = $this->match($tennis, 1, 1);
        $this->assertTrue($first->is_bye);
        $this->assertSame($seedOne->id, $first->winner_registration_id);
        $this->assertSame($seedOne->id, $this->match($tennis, 2, 1)->registration_a_id);
        $this->post('/kelola/lomba/'.$tennis->id.'/bagan')->assertSessionHasErrors('action');

        $open = $this->match($tennis, 1, 2);
        $this->assertFalse($open->is_bye);
        $this->post('/pertandingan/'.$open->id.'/pemenang', ['winner' => $seedOne->id, 'version' => $open->version])->assertSessionHasErrors('action');
        $this->post('/pertandingan/'.$open->id.'/pemenang', ['winner' => $open->registration_a_id, 'score_a' => 3, 'score_b' => 1, 'version' => $open->version + 5])->assertSessionHasErrors('action');
        $this->post('/pertandingan/'.$open->id.'/pemenang', ['winner' => $open->registration_a_id, 'score_a' => 3, 'score_b' => 1, 'version' => $open->version])->assertSessionHasNoErrors();
        $semi = $this->match($tennis, 2, 1);
        $this->assertSame($open->registration_a_id, $semi->registration_b_id);

        $this->post('/kelola/lomba/'.$tennis->id.'/sahkan')->assertSessionHasErrors('action');
        $this->post('/pertandingan/'.$semi->id.'/pemenang', ['winner' => $semi->registration_a_id, 'version' => $semi->version])->assertSessionHasNoErrors();
        $this->post('/pertandingan/'.$open->id.'/koreksi', ['reason' => 'Salah input skor'])->assertSessionHasErrors('action');
        $this->assertSame('finished', $open->fresh()->status, 'Koreksi ditolak karena babak berikutnya sudah selesai.');

        $other = $this->match($tennis, 2, 2);
        $this->post('/pertandingan/'.$other->id.'/pemenang', ['winner' => $other->registration_b_id, 'version' => $other->version])->assertSessionHasNoErrors();
        $this->post('/pertandingan/'.$other->id.'/koreksi', ['reason' => 'Wasit mengoreksi hasil'])->assertSessionHasNoErrors();
        $this->assertNull($this->match($tennis, 3, 1)->registration_b_id);
        $other->refresh();
        $this->post('/pertandingan/'.$other->id.'/pemenang', ['winner' => $other->registration_a_id, 'version' => $other->version])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('score_updates', ['matchup_id' => $other->id, 'action' => 'correction', 'reason' => 'Wasit mengoreksi hasil']);

        $final = $this->match($tennis, 3, 1);
        $this->post('/pertandingan/'.$final->id.'/pemenang', ['winner' => $final->registration_b_id, 'score_a' => 1, 'score_b' => 3, 'version' => $final->version])->assertSessionHasNoErrors();
        $this->post('/kelola/lomba/'.$tennis->id.'/sahkan')->assertSessionHasNoErrors();
        $results = $tennis->results()->get();
        $this->assertSame($final->registration_b_id, $results->firstWhere('rank', 1)->registration_id);
        $this->assertSame($final->registration_a_id, $results->firstWhere('rank', 2)->registration_id);
        $this->assertSame(2, $results->where('rank', 3)->count());
        $this->assertSame(5, $results->count());
        $this->post('/pertandingan/'.$final->id.'/koreksi', ['reason' => 'Setelah disahkan'])->assertSessionHasErrors('action');
    }

    public function test_live_score_versions_prevent_silent_overwrite_and_mark_official(): void
    {
        $tennis = $this->generateTennisBracket();
        $match = $this->match($tennis, 1, 2);
        $operator = $this->user('operator');
        $payload = ['score_a' => 5, 'score_b' => 3, 'period_label' => 'Gim 1', 'status' => 'live'];

        $this->actingAs($operator)->post('/operator/pertandingan/'.$match->id, $payload + ['version' => $match->version])->assertSessionHasNoErrors();
        $this->post('/operator/pertandingan/'.$match->id, ['score_a' => 6] + $payload + ['version' => $match->version])->assertSessionHasErrors('action');
        $this->assertSame([5, 3, 'live'], [$match->fresh()->score_a, $match->fresh()->score_b, $match->fresh()->status]);
        $this->assertSame(1, ScoreUpdate::where('matchup_id', $match->id)->where('action', 'score')->count());

        $this->get('/live/'.$tennis->id)->assertOk();
        Livewire::withoutLazyLoading()->test(LiveBoard::class, ['competitionId' => $tennis->id])->assertSee('SKOR SEMENTARA')->assertSee('BERLANGSUNG');

        $poster = $this->competition('Lomba Poster Kesehatan');
        $this->post('/operator/tampil/'.Registration::where('competition_id', $tennis->id)->first()->id, ['status' => 'performing'])->assertSessionHasErrors('action');
        $this->assertFalse($operator->operates($poster));

        $participantOperator = User::where('nim', 'DEMO004')->first();
        Assignment::create(['user_id' => $participantOperator->id, 'role' => 'operator', 'competition_id' => $tennis->id, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
        $own = Matchup::where('competition_id', $tennis->id)->where(fn ($q) => $q->whereIn('registration_a_id', Participant::where('user_id', $participantOperator->id)->pluck('registration_id'))->orWhereIn('registration_b_id', Participant::where('user_id', $participantOperator->id)->pluck('registration_id')))->where('is_bye', false)->first();
        $this->assertNotNull($own);
        $this->actingAs($participantOperator)->post('/operator/pertandingan/'.$own->id, $payload + ['version' => $own->version])->assertForbidden();

        $match->refresh();
        $this->actingAs($operator)->post('/pertandingan/'.$match->id.'/pemenang', ['winner' => $match->registration_a_id, 'score_a' => 11, 'score_b' => 7, 'version' => $match->version])->assertSessionHasNoErrors();
        $this->post('/operator/pertandingan/'.$match->id, $payload + ['version' => $match->fresh()->version])->assertSessionHasErrors('action');
        $this->post('/pertandingan/'.$match->id.'/koreksi', ['reason' => 'Operator bukan admin event'])->assertForbidden();
    }

    public function test_auth_only_live_score_requires_login_and_official_badge_after_publish(): void
    {
        $poster = $this->competition('Lomba Poster Kesehatan');
        $this->get('/live/'.$poster->id)->assertRedirect('/login');
        $poster->update(['results_published_at' => now()]);
        $this->actingAs($this->user('anggota'));
        Livewire::test(LiveBoard::class, ['competitionId' => $poster->id])->assertSee('HASIL RESMI');
        $poster->update(['live_score' => false]);
        $this->get('/live/'.$poster->id)->assertNotFound();
    }

    /**
     * @return array{0: Competition, 1: Registration, 2: Registration}
     */
    private function posterWithTwoEntries(): array
    {
        $poster = $this->competition('Lomba Poster Kesehatan');
        $entries = [];
        foreach (['mahasiswa', 'anggota'] as $key) {
            $user = $this->user($key);
            $registration = Registration::create(['competition_id' => $poster->id, 'user_id' => $user->id, 'motivation' => 'Poster tentang cuci tangan pakai sabun.', 'status' => 'accepted', 'submitted_at' => now()]);
            Participant::create(['registration_id' => $registration->id, 'competition_id' => $poster->id, 'user_id' => $user->id, 'status' => 'confirmed']);
            $entries[] = $registration;
        }

        return [$poster, $entries[0], $entries[1]];
    }

    /**
     * @param  list<float>  $values
     * @return array<int, array{score: float}>
     */
    private function scores(Competition $competition, array $values): array
    {
        return $competition->criteria()->get()->values()->mapWithKeys(fn ($criterion, $index) => [$criterion->id => ['score' => $values[$index]]])->all();
    }

    public function test_rubric_requires_100_percent_complete_scores_and_no_self_judging(): void
    {
        [$poster, $first, $second] = $this->posterWithTwoEntries();
        $this->actingAs($this->user('event'))->post('/kelola/lomba/'.$poster->id.'/rubrik', ['criteria' => [['name' => 'Isi', 'weight' => 60], ['name' => 'Visual', 'weight' => 30]]])->assertSessionHasErrors('action');

        $judge = $this->user('juri');
        $this->actingAs($judge)->post('/penilaian/'.$poster->id.'/'.$first->id, ['scores' => $this->scores($poster, [80, 90, 70])])->assertSessionHasNoErrors();
        $this->actingAs($this->user('event'))->post('/kelola/lomba/'.$poster->id.'/rubrik', ['criteria' => [['name' => 'Isi', 'weight' => 100]]])->assertSessionHasErrors('action');
        $this->post('/kelola/lomba/'.$poster->id.'/sahkan')->assertSessionHasErrors('action');
        $this->assertNull($poster->fresh()->results_published_at);

        $this->actingAs($this->user('anggota'))->post('/penilaian/'.$poster->id.'/'.$first->id, ['scores' => $this->scores($poster, [100, 100, 100])])->assertForbidden();
        Assignment::create(['user_id' => $this->user('anggota')->id, 'role' => 'penilai', 'competition_id' => $poster->id, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
        $this->post('/penilaian/'.$poster->id.'/'.$second->id, ['scores' => $this->scores($poster, [100, 100, 100])])->assertForbidden();
        $this->assertContains('Michelle Sumual belum dinilai lengkap oleh Juri Poster Kesehatan (0/3 kriteria).', app(ScoringService::class)->missing($poster));

        $this->post('/penilaian/'.$poster->id.'/'.$first->id, ['scores' => $this->scores($poster, [70, 80, 90])])->assertSessionHasNoErrors();
        $this->actingAs($judge)->post('/penilaian/'.$poster->id.'/'.$second->id, ['scores' => $this->scores($poster, [90, 70, 80])])->assertSessionHasNoErrors();
        $this->post('/penilaian/'.$poster->id.'/'.$second->id, ['scores' => $this->scores($poster, [101, 70, 80])])->assertSessionHasErrors();

        $this->actingAs($this->user('event'))->post('/kelola/lomba/'.$poster->id.'/sahkan')->assertSessionHasNoErrors();
        $ranking = $poster->results()->get();
        $this->assertSame($second->id, $ranking->firstWhere('rank', 1)->registration_id);
        $this->assertEqualsWithDelta(90 * .4 + 70 * .35 + 80 * .25, $ranking->firstWhere('rank', 1)->total, 0.001);
        $this->assertEqualsWithDelta((80 * .4 + 90 * .35 + 70 * .25 + 70 * .4 + 80 * .35 + 90 * .25) / 2, $ranking->firstWhere('rank', 2)->total, 0.001);
        $this->actingAs($judge)->post('/penilaian/'.$poster->id.'/'.$second->id, ['scores' => $this->scores($poster, [10, 10, 10])])->assertSessionHasErrors('action');
    }

    public function test_tie_break_uses_heaviest_criterion_or_shares_rank(): void
    {
        [$poster, $first, $second] = $this->posterWithTwoEntries();
        $judge = $this->user('juri');
        $this->actingAs($judge)->post('/penilaian/'.$poster->id.'/'.$first->id, ['scores' => $this->scores($poster, [70, 90, 82])])->assertSessionHasNoErrors();
        $this->post('/penilaian/'.$poster->id.'/'.$second->id, ['scores' => $this->scores($poster, [80, 80, 80])])->assertSessionHasNoErrors();
        $ranking = app(ScoringService::class)->ranking($poster->fresh());
        $this->assertEqualsWithDelta($ranking[0]['total'], $ranking[1]['total'], 0.001);
        $this->assertSame($second->id, $ranking[0]['registration']->id, 'Kriteria berbobot terbesar (40%) memutus nilai sama.');
        $this->assertSame([1, 2], [$ranking[0]['rank'], $ranking[1]['rank']]);

        $poster->update(['tie_break' => 'shared']);
        $shared = app(ScoringService::class)->ranking($poster->fresh());
        $this->assertSame([1, 1], [$shared[0]['rank'], $shared[1]['rank']]);
        $this->assertSame('Seri — peringkat bersama.', $shared[0]['note']);
    }

    public function test_reopening_results_requires_reason_and_no_dependent_awards(): void
    {
        [$poster, $first, $second] = $this->posterWithTwoEntries();
        $judge = $this->user('juri');
        $this->actingAs($judge)->post('/penilaian/'.$poster->id.'/'.$first->id, ['scores' => $this->scores($poster, [70, 70, 70])]);
        $this->post('/penilaian/'.$poster->id.'/'.$second->id, ['scores' => $this->scores($poster, [90, 90, 90])]);
        $this->actingAs($this->user('event'))->post('/kelola/lomba/'.$poster->id.'/sahkan')->assertSessionHasNoErrors();
        $this->post('/kelola/lomba/'.$poster->id.'/penghargaan', ['registration_id' => $second->id, 'award' => 'Juara 1'])->assertSessionHasNoErrors();
        $this->post('/kelola/lomba/'.$poster->id.'/penghargaan', ['registration_id' => $second->id, 'award' => 'Juara 1'])->assertSessionHasErrors('action');
        $this->assertSame('verified', AwardResult::where('competition_id', $poster->id)->first()->status);
        $this->post('/kelola/lomba/'.$poster->id.'/buka-koreksi', ['reason' => 'Ada kesalahan input nilai juri'])->assertForbidden();
        $this->actingAs($this->user('admin'))->post('/kelola/lomba/'.$poster->id.'/buka-koreksi', ['reason' => 'Ada kesalahan input nilai juri'])->assertSessionHasErrors('action');
        AwardResult::where('competition_id', $poster->id)->delete();
        $this->post('/kelola/lomba/'.$poster->id.'/buka-koreksi', ['reason' => 'Ada kesalahan input nilai juri'])->assertSessionHasNoErrors();
        $this->assertNull($poster->fresh()->results_published_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'results.reopened', 'subject_id' => $poster->id]);
    }
}
