<?php

namespace App\Http\Controllers\Club;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\Membership;
use App\Models\Recruitment;
use App\Services\Club\ClubService;
use App\Services\Report\ReportService;
use App\Services\Wita;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClubManageController extends Controller
{
    public function __construct(private ClubService $clubs) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->isStudentAffairs() || $user->assigned('pengurus'), 403);
        $clubs = Club::withCount([
            'memberships as active_members' => fn ($q) => $q->where('status', 'active'),
            'recruitments as open_recruitments' => fn ($q) => $q->where('opens_at', '<=', now())->where('closes_at', '>', now()),
        ])->when(! $user->isStudentAffairs(), fn ($q) => $q->whereIn('id', $user->scopeIds('pengurus', 'club_id')))->orderBy('name')->get();

        return view('clubs.manage-index', ['clubs' => $clubs]);
    }

    public function show(Request $request, Club $club): View
    {
        abort_unless($request->user()->managesClub($club->id), 403);
        $club->load(['recruitments' => fn ($q) => $q->withCount([
            'applications as pending_count' => fn ($a) => $a->whereIn('status', ['submitted', 'waitlisted']),
            'applications as accepted_count' => fn ($a) => $a->where('status', 'accepted'),
        ])->latest('opens_at')]);

        return view('clubs.manage', [
            'club' => $club,
            'members' => Membership::with('user.program', 'histories')->where('club_id', $club->id)
                ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
                ->orderByRaw("status = 'active' desc")->orderBy('division')->get(),
            'officers' => $club->officers()->with('user')->latest('starts_at')->get(),
            'counts' => Membership::where('club_id', $club->id)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'canLead' => $request->user()->leadsClub($club->id),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $club = $this->clubs->saveClub($request->user(), null, $this->validated($request, true));

        return redirect('/kelola/ukm/'.$club->id)->with('success', 'UKM dibuat. Tambahkan pengurus melalui penugasan.');
    }

    public function update(Request $request, Club $club): RedirectResponse
    {
        $this->clubs->saveClub($request->user(), $club, $this->validated($request, $request->user()->isStudentAffairs()));

        return back()->with('success', 'Profil UKM diperbarui.');
    }

    public function saveRecruitment(Request $request, Club $club, ?Recruitment $recruitment = null): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'opens_at' => 'required|date',
            'closes_at' => 'required|date',
            'quota' => 'required|integer|min:1|max:5000',
            'requirements' => 'required|string|max:3000',
            'selection_notes' => 'nullable|string|max:3000',
            'requires_document' => 'nullable|boolean',
        ]);
        $this->clubs->saveRecruitment($request->user(), $club, $recruitment, $data);

        return back()->with('success', 'Periode rekrutmen disimpan.');
    }

    public function updateMember(Request $request, Membership $membership): RedirectResponse
    {
        $data = $request->validate([
            'status' => 'required|in:'.implode(',', array_keys(config('brudel.membership_statuses'))),
            'division' => 'nullable|string|max:80',
            'reason' => 'nullable|string|max:500',
        ]);
        $this->clubs->updateMembership($request->user(), $membership, $data);

        return back()->with('success', 'Data anggota diperbarui.');
    }

    public function export(Request $request, Club $club): StreamedResponse
    {
        abort_unless($request->user()->managesClub($club->id), 403);
        $rows = Membership::with('user.program')->where('club_id', $club->id)->cursor()
            ->map(fn ($m) => [$m->user->name, $m->user->nim, $m->user->program?->name, $m->division, config('brudel.status_labels')[$m->status] ?? $m->status, Wita::show($m->joined_at, 'd/m/Y'), Wita::show($m->left_at, 'd/m/Y')]);

        return ReportService::csv('anggota-'.str($club->name)->slug().'.csv', ['Nama', 'NIM', 'Prodi', 'Divisi', 'Status', 'Bergabung', 'Keluar/alumni'], $rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $full): array
    {
        return $request->validate([
            'name' => ($full ? 'required' : 'nullable').'|string|max:120',
            'field' => 'required|in:'.implode(',', config('brudel.fields')),
            'description' => 'required|string|max:3000',
            'mentor' => 'required|string|max:120',
            'contact' => 'required|string|max:190',
            'activities' => 'nullable|string|max:3000',
            'symbol' => 'nullable|in:racket,music,book,ball,spark,globe,trophy,users',
            'active' => 'nullable|boolean',
        ]);
    }
}
