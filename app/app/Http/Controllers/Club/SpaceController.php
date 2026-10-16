<?php

namespace App\Http\Controllers\Club;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubActivity;
use App\Models\ClubPost;
use App\Models\ClubQuest;
use App\Models\Membership;
use App\Models\QuestSubmission;
use App\Services\Club\SpaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Ruang organisasi: one page per organization for its members (beranda, obrolan, quest, peringkat, anggota).
 */
class SpaceController extends Controller
{
    public const TABS = ['beranda' => 'Beranda', 'obrolan' => 'Obrolan', 'quest' => 'Quest', 'peringkat' => 'Peringkat', 'anggota' => 'Anggota'];

    public function __construct(private SpaceService $space) {}

    public function show(Request $request, Club $club): View
    {
        $user = $request->user();
        abort_unless($this->space->canEnter($user, $club), 403, 'Ruang ini khusus anggota aktif dan pengurus '.$club->name.'.');
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'beranda';
        $leaderboard = $this->space->leaderboard($club);

        return view('clubs.space', [
            'club' => $club,
            'tab' => $tab,
            'canLead' => $this->space->canLead($user, $club),
            'isMember' => $this->space->isMember($user, $club),
            'me' => $leaderboard->firstWhere('user.id', $user->id),
            'leaderboard' => $leaderboard,
            'posts' => ClubPost::with('author')->where('club_id', $club->id)->orderByDesc('pinned')->latest('id')->limit(30)->get(),
            'activities' => ClubActivity::where('club_id', $club->id)->confirmed()->where('status', 'scheduled')->where('ends_at', '>', now())->orderBy('starts_at')->limit(5)->get(),
            'quests' => ClubQuest::where('club_id', $club->id)->withCount(['submissions as done_count' => fn ($q) => $q->where('status', 'approved')])
                ->with(['submissions' => fn ($q) => $q->where('user_id', $user->id)])->orderByRaw("status = 'open' desc")->latest('id')->limit(50)->get(),
            'reviews' => QuestSubmission::with('user', 'quest')->where('status', 'submitted')->whereHas('quest', fn ($q) => $q->where('club_id', $club->id))->oldest()->get(),
            'members' => Membership::with('user')->where('club_id', $club->id)->where('status', 'active')->get()->sortBy('user.name'),
            'officers' => $club->officers()->with('user')->where('starts_at', '<=', now())->where('ends_at', '>', now())->get(),
        ]);
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
