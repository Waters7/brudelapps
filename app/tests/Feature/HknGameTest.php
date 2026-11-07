<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubQuest;
use App\Models\GameReward;
use App\Models\QuestSubmission;
use App\Models\User;
use App\Services\Game\HknGameService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HknGameTest extends TestCase
{
    use RefreshDatabase;

    private function player(): User
    {
        return User::factory()->create(['active' => true, 'must_change_password' => false]);
    }

    private function trace(): array
    {
        return json_decode(file_get_contents(base_path('tests/Fixtures/hkn-replays.json')), true)[0];
    }

    private function payload(User $user): array
    {
        $run = app(HknGameService::class)->start($user);
        $trace = $this->trace();
        $this->travel((int) ceil($trace['ticks'] / 60) + 1)->seconds();

        return ['token' => $run->token, 'ticks' => $trace['ticks'], 'jumps' => $trace['jumps'], 'answers' => $trace['answers']];
    }

    public function test_game_requires_a_personal_active_account(): void
    {
        $this->get('/')->assertOk()->assertSee('HKN Run: 62 km untuk semangat sehat.')->assertSee('href="/games/hkn-62"', false);
        $this->get('/games/hkn-62')->assertRedirect('/login');
        $this->postJson('/games/hkn-62/mulai')->assertUnauthorized();
        $user = $this->player();
        $this->actingAs($user)->get('/games/hkn-62')->assertOk()->assertSee('62 kilometer');
        $user->update(['active' => false]);
        $this->actingAs($user)->postJson('/games/hkn-62/mulai')->assertForbidden();
    }

    public function test_verified_finish_awards_62_points_and_badge_once_even_after_retry_or_replay(): void
    {
        $user = $this->player();
        $payload = $this->payload($user);
        $this->actingAs($user)->postJson('/games/hkn-62/finis', $payload + ['points' => 9999])
            ->assertOk()->assertJsonPath('points', 62)->assertJsonPath('awarded', true)->assertJsonPath('badge', HknGameService::BADGE);
        $this->postJson('/games/hkn-62/finis', $payload)->assertOk()->assertJsonPath('awarded', false);
        $this->postJson('/games/hkn-62/finis', $this->payload($user))->assertOk()->assertJsonPath('awarded', false);
        $this->assertDatabaseCount('game_rewards', 1);
        $this->assertDatabaseHas('game_rewards', ['user_id' => $user->id, 'points' => 62]);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->get('/profil')->assertOk()->assertSee('62 poin mini game BRUDEL')->assertSee(HknGameService::BADGE);
        $this->get('/')->assertOk()->assertViewHas('gamePoints', 62)->assertSee('Main HKN Run')->assertSee('62 poin BRUDEL');
        $this->actingAs($this->player())->get('/')->assertOk()->assertViewHas('gamePoints', 0);
    }

    public function test_trivia_logs_cannot_be_skipped_forged_or_duplicated(): void
    {
        $user = $this->player();
        $payload = $this->payload($user);
        $invalid = [[], [['tick' => 1, 'choice' => 1]], [$payload['answers'][0], $payload['answers'][0]]];
        $wrong = $payload['answers'];
        foreach ($wrong as &$answer) {
            $answer['choice'] = ($answer['choice'] + 1) % 3;
        }
        unset($answer);
        $invalid[] = $wrong;
        foreach ($invalid as $answers) {
            $this->actingAs($user)->postJson('/games/hkn-62/finis', array_replace($payload, ['answers' => $answers]))->assertUnprocessable();
        }
        $this->assertDatabaseCount('game_rewards', 0);
    }

    public function test_dashboard_counts_only_own_approved_quest_points(): void
    {
        $user = $this->player();
        $other = $this->player();
        $club = Club::create(['name' => 'Klub Uji', 'field' => 'Olahraga', 'description' => 'Uji', 'mentor' => '-', 'contact' => '-', 'symbol' => 'users']);
        foreach (['approved', 'submitted', 'rejected'] as $status) {
            $quest = ClubQuest::create(['club_id' => $club->id, 'created_by' => $user->id, 'title' => $status, 'description' => 'Uji', 'points' => 10]);
            QuestSubmission::create(['club_quest_id' => $quest->id, 'user_id' => $user->id, 'note' => 'Uji', 'status' => $status, 'points_awarded' => 10]);
            QuestSubmission::create(['club_quest_id' => $quest->id, 'user_id' => $other->id, 'note' => 'Uji', 'status' => 'approved', 'points_awarded' => 10]);
        }
        $this->actingAs($user)->get('/')->assertOk()->assertViewHas('gamePoints', 0)->assertViewHas('questPoints', 10);
    }

    public function test_old_rewards_are_preserved_but_rank_requires_current_rules(): void
    {
        $user = $this->player();
        GameReward::create(['user_id' => $user->id, 'game' => HknGameService::GAME, 'points' => 62, 'badge' => HknGameService::BADGE,
            'best_ticks' => 1, 'best_rules_version' => 1, 'earned_at' => now(), 'best_at' => now()]);
        $this->actingAs($user)->getJson('/games/hkn-62/peringkat')->assertJsonCount(0, 'rows')->assertJsonPath('mine.best_ticks', null);
        $this->postJson('/games/hkn-62/finis', $this->payload($user))->assertOk()->assertJsonPath('awarded', false);
        $this->assertDatabaseCount('game_rewards', 1);
        $this->assertDatabaseHas('game_rewards', ['user_id' => $user->id, 'points' => 62, 'best_rules_version' => 2]);
        $this->getJson('/games/hkn-62/peringkat')->assertJsonCount(1, 'rows');
    }

    public function test_client_cannot_claim_a_finish_before_time_or_without_surviving_the_obstacles(): void
    {
        $user = $this->player();
        $run = app(HknGameService::class)->start($user);
        $trace = $this->trace();
        $payload = ['token' => $run->token, 'ticks' => $trace['ticks'], 'jumps' => $trace['jumps'], 'answers' => $trace['answers']];
        $this->actingAs($user)->postJson('/games/hkn-62/finis', $payload)->assertUnprocessable();
        $this->travel(200)->seconds();
        $this->postJson('/games/hkn-62/finis', array_replace($payload, ['jumps' => []]))->assertUnprocessable();
        $this->postJson('/games/hkn-62/finis', array_replace($payload, ['ticks' => 100]))->assertUnprocessable();
        $this->assertDatabaseCount('game_rewards', 0);
    }

    public function test_attempt_ownership_expiry_and_replaced_tabs_are_enforced(): void
    {
        $user = $this->player();
        $payload = $this->payload($user);
        $this->actingAs($this->player())->postJson('/games/hkn-62/finis', $payload)->assertNotFound();
        $this->travel(61)->minutes();
        $this->actingAs($user)->postJson('/games/hkn-62/finis', $payload)->assertUnprocessable();
        $this->postJson('/games/hkn-62/mulai')->assertOk();
        $this->postJson('/games/hkn-62/finis', $payload)->assertNotFound();
        $this->assertDatabaseCount('game_rewards', 0);
        $this->assertDatabaseCount('game_runs', 1);
    }

    public function test_malformed_jump_logs_and_invalid_tick_counts_are_refused(): void
    {
        $user = $this->player();
        $payload = $this->payload($user);
        foreach ([[2, 1], [1, 1], ['invalid'], [999999]] as $jumps) {
            $this->actingAs($user)->postJson('/games/hkn-62/finis', array_replace($payload, ['jumps' => $jumps]))->assertUnprocessable();
        }
        $this->postJson('/games/hkn-62/finis', array_replace($payload, ['ticks' => 18001]))->assertUnprocessable();
        $this->assertDatabaseCount('game_rewards', 0);
    }

    public function test_leaderboard_hides_other_users_identity_and_sorts_by_best_time(): void
    {
        $first = $this->player();
        $second = $this->player();
        foreach ([[$first, 10000], [$second, 9000]] as [$user, $ticks]) {
            GameReward::create(['user_id' => $user->id, 'game' => HknGameService::GAME, 'points' => 62, 'badge' => HknGameService::BADGE,
                'best_ticks' => $ticks, 'best_rules_version' => HknGameService::RULES_VERSION, 'earned_at' => now(), 'best_at' => now()]);
        }
        $this->actingAs($first)->getJson('/games/hkn-62/peringkat')->assertOk()->assertJsonPath('rows.0.ticks', 9000)
            ->assertJsonPath('rows.1.name', 'Kamu')->assertDontSee($second->email)->assertDontSee($second->name);
    }

    public function test_frame_is_locked_until_finish_and_can_be_equipped_and_removed_without_changing_photo(): void
    {
        $user = $this->player();
        $user->forceFill(['photo_path' => 'photos/hkn-test.jpg', 'photo_disk' => 'local'])->save();
        $this->actingAs($user)->postJson('/games/hkn-62/frame', ['enabled' => true])->assertUnprocessable();
        $this->assertNull($user->fresh()->profile_frame);
        $this->postJson('/games/hkn-62/finis', $this->payload($user))->assertOk();
        $this->post('/games/hkn-62/frame', ['enabled' => true])->assertRedirect('/profil');
        $this->assertSame(HknGameService::GAME, $user->fresh()->profile_frame);
        $this->get('/profil')->assertOk()->assertSee('frame.svg')->assertSee('Lepas frame HKN');
        $this->post('/games/hkn-62/frame', ['enabled' => false])->assertRedirect('/profil');
        $this->assertNull($user->fresh()->profile_frame);
        $this->assertDatabaseCount('game_rewards', 1);
        $this->assertSame('photos/hkn-test.jpg', $user->fresh()->photo_path);
    }
}
