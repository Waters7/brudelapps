<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubQuest;
use App\Models\Membership;
use App\Models\QuestSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Support\Facades\DB;

/**
 * Guards the bottlenecks fixed before deployment: every menu stays within a query budget, no query
 * repeats per row (N+1), and organization pages issue the same queries however many members they hold.
 */
class PerformanceBudgetTest extends DemoTestCase
{
    private const MENU_QUERY_BUDGET = 45;

    private const REPEAT_BUDGET = 10;

    private int $nextNim = 0;

    public function test_every_menu_of_every_role_stays_within_the_query_budget(): void
    {
        foreach (['admin', 'jurusan', 'pengurus', 'pembina', 'event', 'juri', 'operator', 'pimpinan', 'mahasiswa'] as $role) {
            $this->actingAs($this->user($role));
            preg_match_all('/href="([^"#]+)" class="nav-item/', (string) $this->get('/')->assertOk()->getContent(), $links);
            $menus = collect($links[1])->map(fn ($href) => parse_url($href, PHP_URL_PATH).(($query = parse_url($href, PHP_URL_QUERY)) ? '?'.$query : ''))->unique();
            $this->assertNotEmpty($menus, $role);
            foreach ($menus->prepend('/') as $menu) {
                ['total' => $total, 'repeated' => $repeated] = $this->measure($menu);
                $this->assertLessThanOrEqual(self::MENU_QUERY_BUDGET, $total, $role.' '.$menu);
                $this->assertLessThanOrEqual(self::REPEAT_BUDGET, $repeated, $role.' '.$menu.' mengulang query yang sama');
            }
        }
    }

    public function test_organization_pages_do_not_query_more_as_membership_grows(): void
    {
        $club = Club::where('name', 'UKM Olahraga')->firstOrFail();
        $officer = $this->user('pengurus');
        $quest = ClubQuest::create(['club_id' => $club->id, 'created_by' => $officer->id, 'title' => 'Latihan rutin', 'description' => 'Hadir di latihan rutin.', 'points' => 10]);
        $pages = ['/ruang/'.$club->id, '/ruang/'.$club->id.'?tab=anggota', '/ruang/'.$club->id.'?tab=peringkat', '/kelola/ukm/'.$club->id, '/ukm/'.$club->id];
        $this->actingAs($officer);

        // Both measurements sit past the page sizes (60 members, top 50 ranks) so only the row count differs.
        $this->addMembers($club, $quest, 70);
        $before = array_map(fn ($page) => $this->measure($page), $pages);
        $this->addMembers($club, $quest, 150);
        $after = array_map(fn ($page) => $this->measure($page), $pages);

        foreach ($pages as $index => $page) {
            $this->assertSame($before[$index]['total'], $after[$index]['total'], $page);
            $this->assertLessThanOrEqual(self::REPEAT_BUDGET, $after[$index]['repeated'], $page.' mengulang query yang sama');
        }
    }

    /**
     * Queries of one page view after a warm-up view, so per-process caches do not skew the count.
     *
     * @return array{total: int, repeated: int}
     */
    private function measure(string $url): array
    {
        $this->get($url)->assertOk();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get($url)->assertOk();
        DB::disableQueryLog();
        $shapes = collect(DB::getQueryLog())->countBy(fn (array $query) => preg_replace(['/\?(\s*,\s*\?)+/', '/\s+/'], ['?', ' '], $query['query']));

        return ['total' => $shapes->sum(), 'repeated' => $shapes->max() ?? 0];
    }

    private function addMembers(Club $club, ClubQuest $quest, int $count): void
    {
        User::factory()->count($count)->state(new Sequence(fn () => ['nim' => sprintf('UJIPERF%05d', $this->nextNim++), 'active' => true]))->create()
            ->each(function (User $user) use ($club, $quest) {
                Membership::create(['club_id' => $club->id, 'user_id' => $user->id, 'status' => 'active', 'joined_at' => now()]);
                QuestSubmission::create(['club_quest_id' => $quest->id, 'user_id' => $user->id, 'note' => 'Hadir latihan.', 'status' => 'approved', 'points_awarded' => 10]);
            });
    }
}
