<?php

namespace App\Http\Controllers\Event;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\Competition;
use App\Models\Event;
use App\Models\StatusHistory;
use App\Services\Event\EventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventManageController extends Controller
{
    public function __construct(private EventService $events) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->isStudentAffairs() || $user->assigned('admin_event') || $user->assigned('pengurus'), 403);
        $leadClubs = $user->currentAssignments()->where('role', 'pengurus')->where('position', 'Ketua')->pluck('club_id');
        $events = Event::with('club')->withCount('competitions')
            ->when(! $user->isStudentAffairs(), fn ($q) => $q->where(fn ($w) => $w->whereIn('id', $user->scopeIds('admin_event', 'event_id'))->orWhereIn('club_id', $user->scopeIds('pengurus', 'club_id'))))
            ->latest()->get();

        return view('events.index', [
            'events' => $events,
            'pending' => $user->isStudentAffairs() ? $events->where('status', 'submitted') : collect(),
            'clubs' => $user->isStudentAffairs() ? Club::orderBy('name')->get() : Club::whereIn('id', $leadClubs)->orderBy('name')->get(),
        ]);
    }

    public function show(Request $request, Event $event): View
    {
        $user = $request->user();
        $canEdit = $this->events->canEdit($user, $event);
        abort_unless($canEdit || $user->managesEvent($event->id) || ($event->club_id && $user->managesClub($event->club_id)), 403);
        $event->load(['club', 'proposer', 'competitions' => fn ($q) => $q->withCount(['registrations as holding_count' => fn ($r) => $r->whereIn('status', ['program_review', 'submitted', 'accepted'])]), 'admins' => fn ($q) => $q->with('user')->where('ends_at', '>', now())]);

        return view('events.show', [
            'event' => $event,
            'canEdit' => $canEdit,
            'canSubmit' => $event->isEditableProposal() && $event->club_id && $user->leadsClub($event->club_id),
            'canDecide' => $user->isStudentAffairs() && $event->status === 'submitted' && $event->proposed_by !== $user->id,
            'histories' => StatusHistory::where('subject_type', 'events')->where('subject_id', $event->id)->oldest('id')->get(),
        ]);
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
            'field' => 'required|in:'.implode(',', config('brudel.fields')),
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
            'live_score' => 'nullable|boolean',
            'live_visibility' => 'nullable|in:public,auth',
            'live_show_provisional' => 'nullable|boolean',
            'external_info' => 'nullable|string|max:2000',
        ]);
        $competition = $this->events->saveCompetition($request->user(), $event, $competition, $data);

        return redirect('/kelola/event/'.$event->id.'#competition-'.$competition->id)->with('success', 'Nomor lomba disimpan.');
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
            'field' => 'nullable|in:'.implode(',', config('brudel.fields')),
            'description' => 'nullable|string|max:3000',
            'external_organizer' => 'nullable|string|max:190',
            'club_id' => 'nullable|integer|exists:clubs,id',
        ]);
    }
}
