<?php

namespace App\Http\Controllers\Event;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Club;
use App\Models\Competition;
use App\Models\CompetitionAccount;
use App\Models\Event;
use App\Models\StatusHistory;
use App\Services\Account\AssignmentService;
use App\Services\Campus\MasterData;
use App\Services\Event\EventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EventManageController extends Controller
{
    public const TEAM_ROLES = ['operator' => 'Operator skor & live score', 'pengelola_lomba' => 'Pengelola lomba', 'penilai' => 'Juri/penilai', 'admin_event' => 'Admin event (semua nomor lomba)'];

    public function __construct(private EventService $events) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->managesAnyCompetition() || $user->assigned('pengurus') || $user->assigned('jurusan') || Event::where('proposed_by', $user->id)->orWhere('responsible_id', $user->id)->exists(), 403);
        $leadClubs = $user->scopeIds('pengurus', 'club_id')->filter(fn ($club) => $user->leadsClub($club))->values();
        $clubs = Club::orderBy('name')->get()->when(! $user->isStudentAffairs(), fn ($all) => $all->filter(fn (Club $club) => $leadClubs->contains($club->id) || $user->administersClub($club->id))->values());
        $events = Event::with('club')->withCount('competitions')
            ->when(! $user->isStudentAffairs(), fn ($q) => $q->where(fn ($w) => $w->whereIn('id', $user->scopeIds('admin_event', 'event_id'))
                ->orWhereIn('club_id', $user->scopeIds('pengurus', 'club_id')->merge($clubs->pluck('id')))->orWhere('proposed_by', $user->id)->orWhere('responsible_id', $user->id)))
            ->latest()->get();

        return view('events.index', [
            'managed' => $user->assigned('pengelola_lomba') ? Competition::with('event')->whereIn('id', $user->scopeIds('pengelola_lomba', 'competition_id'))->get() : collect(),
            'events' => $events,
            'pending' => $user->isStudentAffairs() ? $events->where('status', 'submitted') : collect(),
            'clubs' => $clubs,
        ]);
    }

    public function show(Request $request, Event $event): View
    {
        $user = $request->user();
        $canEdit = $this->events->canEdit($user, $event);
        abort_unless($canEdit || $user->managesEvent($event->id) || $event->proposed_by === $user->id || $event->responsible_id === $user->id || ($event->club_id && $user->managesClub($event->club_id)), 403);
        $event->load(['club', 'proposer', 'responsible', 'competitions' => fn ($q) => $q->withCount(['registrations as holding_count' => fn ($r) => $r->whereIn('status', ['program_review', 'submitted', 'accepted'])])]);

        return view('events.show', [
            'event' => $event,
            'canEdit' => $canEdit,
            'canSubmit' => $event->isEditableProposal() && $this->events->mayPropose($user, $event),
            'canDecide' => $user->isStudentAffairs() && $event->status === 'submitted' && $event->proposed_by !== $user->id,
            'canSeeAccounts' => $event->status === 'approved' && ($user->isStudentAffairs() || $user->answersForEvent($event)),
            'canAppoint' => $user->appointsEventTeam($event),
            'canSetResponsible' => $this->events->canSetResponsible($user, $event),
            'responsibleVacant' => $this->events->responsibleVacant($event),
            'candidates' => $this->events->canSetResponsible($user, $event) ? $this->events->responsibleCandidates($event) : collect(),
            'team' => $this->team($event),
            'teamRoles' => self::TEAM_ROLES,
            'teamUntil' => $event->competitions->map->staffUntil()->push(now()->addMonth())->max(),
            'histories' => StatusHistory::where('subject_type', 'events')->where('subject_id', $event->id)->oldest('id')->get(),
        ]);
    }

    public function setResponsible(Request $request, Event $event): RedirectResponse
    {
        $data = $request->validate(['user_id' => 'required|integer']);
        $this->events->setResponsible($request->user(), $event, $data['user_id']);

        return back()->with('success', 'Penanggung jawab event diperbarui.');
    }

    /**
     * Admin event covers every competition of the event; the other roles belong to one competition of it.
     */
    public function appoint(Request $request, Event $event, AssignmentService $assignments): RedirectResponse
    {
        abort_unless($request->user()->appointsEventTeam($event), 403);
        $data = $request->validate([
            'user' => 'required|string|max:190',
            'role' => ['required', Rule::in(array_keys(self::TEAM_ROLES))],
            'competition_id' => ['nullable', 'required_unless:role,admin_event', 'integer', Rule::exists('competitions', 'id')->where('event_id', $event->id)],
            'starts_at' => 'required|date',
            'ends_at' => 'required|date',
        ], ['competition_id.required_unless' => 'Pilih nomor lomba untuk peran ini.']);
        $scope = $data['role'] === 'admin_event' ? $event->id : (int) $data['competition_id'];
        $assignments->grant($request->user(), ['user' => $data['user'], 'role' => $data['role'], 'scope_id' => $scope, 'starts_at' => $data['starts_at'], 'ends_at' => $data['ends_at']]);

        return back()->with('success', 'Tim penyelenggara diperbarui dan tercatat pada audit.');
    }

    public function store(Request $request): RedirectResponse
    {
        $event = $this->events->saveEvent($request->user(), null, $this->validated($request));

        return redirect('/kelola/event/'.$event->id)->with('success', $event->status === 'approved' ? 'Event resmi dibuat. Tambahkan nomor lomba dan tugaskan admin event.' : 'Draf pengajuan dibuat. Tambahkan nomor lomba lalu ajukan ke kemahasiswaan.');
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        abort_unless($this->events->canEdit($request->user(), $event), 403);
        $this->events->saveEvent($request->user(), $event, $this->validated($request));

        return back()->with('success', 'Event diperbarui.');
    }

    public function submit(Request $request, Event $event): RedirectResponse
    {
        $this->events->submit($request->user(), $event);

        return back()->with('success', 'Pengajuan dikirim ke kemahasiswaan.');
    }

    public function decide(Request $request, Event $event): RedirectResponse
    {
        $data = $request->validate(['decision' => 'required|in:approved,revision,rejected', 'reason' => 'nullable|string|max:2000']);
        $this->events->decide($request->user(), $event, $data['decision'], $data['reason'] ?? null);

        return back()->with('success', 'Keputusan pengajuan tersimpan.');
    }

    public function saveCompetition(Request $request, Event $event, ?Competition $competition = null): RedirectResponse
    {
        abort_unless($this->events->canEdit($request->user(), $event), 403);
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'field' => ['required', Rule::in(app(MasterData::class)->allFields())],
            'level' => 'required|integer|between:1,6',
            'description' => 'required|string|max:3000',
            'requirements' => 'required|string|max:3000',
            'format' => 'required|in:individual,team',
            'min_members' => 'nullable|integer|min:1|max:50',
            'max_members' => 'nullable|integer|min:1|max:50',
            'quota' => 'required|integer|min:1|max:5000',
            'fee' => 'required|integer|min:0|max:100000000',
            'fee_basis' => 'required|in:team,participant',
            'fee_notes' => 'nullable|string|max:1000',
            'requires_document' => 'nullable|boolean',
            'requires_program_approval' => 'nullable|boolean',
            'opens_at' => 'required|date',
            'closes_at' => 'required|date',
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date',
            'location' => 'required|string|max:190',
            'assessment_type' => 'required|in:none,bracket,rubric',
            'tie_break' => 'nullable|in:criteria,shared',
            'judge_count' => 'nullable|integer|between:1,'.config('brudel.max_judges'),
            'live_score' => 'nullable|boolean',
            'live_visibility' => 'nullable|in:public,auth',
            'live_show_provisional' => 'nullable|boolean',
            'external_info' => 'nullable|string|max:2000',
        ]);
        $competition = $this->events->saveCompetition($request->user(), $event, $competition, $data);

        return redirect('/kelola/event/'.$event->id.'#competition-'.$competition->id)->with('success', 'Nomor lomba disimpan.');
    }

    /**
     * People appointed to the event. Generated competition logins stay on their own page.
     *
     * @return Collection<int, Assignment>
     */
    private function team(Event $event): Collection
    {
        $competitions = $event->competitions->pluck('id');

        return Assignment::with('user', 'competition')->whereIn('role', array_keys(self::TEAM_ROLES))->where('ends_at', '>', now())
            ->where(fn ($q) => $q->where('event_id', $event->id)->orWhereIn('competition_id', $competitions))
            ->whereNotIn('user_id', CompetitionAccount::whereIn('competition_id', $competitions)->select('user_id'))
            ->orderBy('role')->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:150',
            'organizer' => 'required|string|max:190',
            'pathway' => 'required|in:internal,delegation',
            'field' => ['nullable', Rule::in(app(MasterData::class)->allFields())],
            'description' => 'nullable|string|max:3000',
            'external_organizer' => 'nullable|string|max:190',
            'club_id' => 'nullable|integer|exists:clubs,id',
        ]);
    }
}
