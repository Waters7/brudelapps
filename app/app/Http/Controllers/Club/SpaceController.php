<?php

namespace App\Http\Controllers\Club;

use App\Http\Controllers\Controller;
use App\Models\Broadcast;
use App\Models\Club;
use App\Models\ClubActivity;
use App\Models\ClubPost;
use App\Models\ClubQuest;
use App\Models\Membership;
use App\Models\MembershipRequest;
use App\Models\QuestSubmission;
use App\Models\User;
use App\Services\Club\MembershipRequestService;
use App\Services\Club\SpaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Ruang organisasi: one page per organization for its members (beranda, obrolan, quest, peringkat, anggota).
 */
class SpaceController extends Controller
{
    public const TABS = ['beranda' => 'Beranda', 'obrolan' => 'Obrolan', 'quest' => 'Quest', 'peringkat' => 'Peringkat', 'anggota' => 'Anggota'];

    public function __construct(private SpaceService $space, private MembershipRequestService $requests) {}

    public function show(Request $request, Club $club): View
    {
        $user = $request->user();
        abort_unless($this->space->canEnter($user, $club), 403, 'Ruang ini khusus anggota aktif dan pengurus '.$club->name.'.');
        $canLead = $this->space->canLead($user, $club);
        // Members do not see who else belongs to the organization; only those who run it do.
        $tabs = $canLead ? self::TABS : array_diff_key(self::TABS, ['anggota' => true]);
        $tab = array_key_exists((string) $request->query('tab'), $tabs) ? $request->query('tab') : 'beranda';
        $board = $this->space->leaderboard($club, $tab === 'peringkat' ? 50 : 5, $user);
        $search = trim((string) $request->query('cari'));
        $joinQueue = $tab === 'anggota' ? MembershipRequest::with('user.program')->where('club_id', $club->id)->pending()->oldest('id')->get() : collect();

        return view('clubs.space', [
            'club' => $club,
            'tab' => $tab,
            'tabs' => $tabs,
            'canLead' => $canLead,
            'canInvite' => $this->requests->canInvite($user, $club),
            'joinRequests' => $joinQueue->reject->isInvitation()->values(),
            'invitations' => $joinQueue->filter->isInvitation()->values(),
            'isMember' => $this->space->isMember($user, $club),
            'me' => $board['me'],
            'leaderboard' => $canLead ? $board['rows'] : $this->anonymous($board['rows'], $user),
            'memberCount' => $board['total'],
            'search' => $search,
            'broadcasts' => Broadcast::with('author')->shown()->forClubs([$club->id])->latest('id')->limit(5)->get(),
            'posts' => ClubPost::with('author')->where('club_id', $club->id)->orderByDesc('pinned')->latest('id')->limit(30)->get(),
            'activities' => ClubActivity::where('club_id', $club->id)->confirmed()->where('status', 'scheduled')->where('ends_at', '>', now())->orderBy('starts_at')->limit(5)->get(),
            'quests' => ClubQuest::where('club_id', $club->id)->withCount(['submissions as done_count' => fn ($q) => $q->where('status', 'approved')])
                ->with(['submissions' => fn ($q) => $q->where('user_id', $user->id)])->orderByRaw("status = 'open' desc")->latest('id')->limit(50)->get(),
            'reviews' => $canLead ? QuestSubmission::with('user', 'quest')->where('status', 'submitted')->whereHas('quest', fn ($q) => $q->where('club_id', $club->id))->oldest()->get() : collect(),
            'members' => $tab === 'anggota' ? Membership::with('user')->where('memberships.club_id', $club->id)->where('memberships.status', 'active')
                ->join('users', 'users.id', '=', 'memberships.user_id')->select('memberships.*')
                ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->whereRaw('LOWER(users.name) LIKE ?', ['%'.mb_strtolower($search).'%'])->orWhere('users.nim', $search)))
                ->orderBy('users.name')->paginate(60)->withQueryString() : collect(),
            'officers' => $club->officers()->with('user')->where('starts_at', '<=', now())->where('ends_at', '>', now())->get(),
        ]);
    }

    /**
     * The leaderboard a plain member sees: ranks and points stay, everyone else's name does not.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function anonymous(Collection $rows, User $viewer): Collection
    {
        return $rows->map(fn (array $row) => $row['user']->id === $viewer->id ? $row : ['user' => new User(['name' => 'Anggota #'.$row['rank']])] + $row);
    }

    public function storePost(Request $request, Club $club): RedirectResponse
    {
        $data = $request->validate(['title' => 'required|string|max:150', 'body' => 'required|string|max:5000', 'pinned' => 'nullable|boolean']);
        $this->space->post($request->user(), $club, $data['title'], $data['body'], (bool) ($data['pinned'] ?? false));

        return back()->with('success', 'Pengumuman diterbitkan dan anggota mendapat notifikasi.');
    }

    public function pinPost(Request $request, ClubPost $post): RedirectResponse
    {
        $this->space->togglePin($request->user(), $post);

        return back();
    }

    public function destroyPost(Request $request, ClubPost $post): RedirectResponse
    {
        $this->space->deletePost($request->user(), $post);

        return back()->with('success', 'Pengumuman dihapus.');
    }

    public function storeQuest(Request $request, Club $club): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:150',
            'description' => 'required|string|max:3000',
            'points' => 'required|integer|min:5|max:500',
            'due_at' => 'nullable|date',
        ]);
        $this->space->createQuest($request->user(), $club, $data);

        return redirect('/ruang/'.$club->id.'?tab=quest')->with('success', 'Quest dibuat.');
    }

    public function closeQuest(Request $request, ClubQuest $quest): RedirectResponse
    {
        $this->space->closeQuest($request->user(), $quest);

        return back()->with('success', 'Quest ditutup.');
    }

    public function submitQuest(Request $request, ClubQuest $quest): RedirectResponse
    {
        $data = $request->validate(['note' => 'required|string|max:2000']);
        $this->space->submit($request->user(), $quest, $data['note']);

        return back()->with('success', 'Laporan quest dikirim. Pengurus akan memeriksanya.');
    }

    public function reviewQuest(Request $request, QuestSubmission $submission): RedirectResponse
    {
        $data = $request->validate(['decision' => 'required|in:approved,rejected', 'note' => 'nullable|string|max:1000']);
        $this->space->review($request->user(), $submission, $data['decision'], $data['note'] ?? null);

        return back()->with('success', $data['decision'] === 'approved' ? 'Quest disetujui; poin ditambahkan.' : 'Laporan quest dikembalikan.');
    }
}
