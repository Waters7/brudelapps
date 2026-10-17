<?php

namespace App\Http\Controllers\Club;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\Department;
use App\Models\Membership;
use App\Models\Recruitment;
use App\Services\Campus\MasterData;
use App\Services\Club\ClubService;
use App\Services\PrivateFiles;
use App\Services\Report\ReportService;
use App\Services\Wita;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClubManageController extends Controller
{
    public function __construct(private ClubService $clubs) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->isStudentAffairs() || $user->assigned('pengurus') || $user->assigned('jurusan'), 403);
        $clubs = Club::withCount([
            'memberships as active_members' => fn ($q) => $q->where('status', 'active'),
            'recruitments as open_recruitments' => fn ($q) => $q->where('opens_at', '<=', now())->where('closes_at', '>', now()),
        ])->when(! $user->isStudentAffairs(), fn ($q) => $q->where(fn ($scope) => $scope->whereIn('id', $user->scopeIds('pengurus', 'club_id'))
            ->orWhere(fn ($himaju) => $himaju->where('category', 'himaju')->whereIn('department_id', $user->scopeIds('jurusan', 'department_id')))))
            ->ordered()->get();

        return view('clubs.manage-index', ['clubs' => $clubs, 'departments' => Department::orderBy('id')->get()]);
    }

    public function show(Request $request, Club $club): View
    {
        abort_unless($request->user()->managesClub($club->id), 403);
        $club->load(['recruitments' => fn ($q) => $q->withCount([
            'applications as pending_count' => fn ($a) => $a->whereIn('status', ['submitted', 'waitlisted']),
            'applications as accepted_count' => fn ($a) => $a->where('status', 'accepted'),
        ])->latest('opens_at')]);

        $search = trim((string) $request->query('cari'));
        $activeCount = Membership::where('club_id', $club->id)->where('status', 'active')->count();

        return view('clubs.manage', [
            'club' => $club,
            'search' => $search,
            // A HIMAJU can hold thousands of members: page and search instead of rendering them all.
            'members' => Membership::with('user.program', 'histories')->where('memberships.club_id', $club->id)
                ->join('users', 'users.id', '=', 'memberships.user_id')->select('memberships.*')
                ->when($request->query('status'), fn ($q, $status) => $q->where('memberships.status', $status))
                ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->whereRaw('LOWER(users.name) LIKE ?', ['%'.mb_strtolower($search).'%'])->orWhere('users.nim', $search)))
                ->orderByRaw("memberships.status = 'active' desc")->orderBy('memberships.division')->orderBy('users.name')->paginate(50)->withQueryString(),
            'officers' => $club->officers()->with('user')->latest('starts_at')->get(),
            'counts' => Membership::where('club_id', $club->id)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'canLead' => $request->user()->managesOfficers($club->id),
            'canAssignCoach' => $request->user()->leadsClub($club->id) || $request->user()->administersHimaju($club->id),
            // Large organizations pick officers by NIM (still checked as an active member) instead of a huge list.
            'candidates' => $activeCount <= 300 ? Membership::with('user')->where('club_id', $club->id)->where('status', 'active')->get()->sortBy('user.name') : null,
            'activeCount' => $activeCount,
            'departments' => Department::orderBy('id')->get(),
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

    public function banner(Request $request, Club $club): RedirectResponse
    {
        $request->validate(['banner' => PrivateFiles::BANNER_RULES]);
        $this->clubs->updateBanner($request->user(), $club, $request->file('banner'));

        return back()->with('success', 'Banner kartu diperbarui.');
    }

    public function removeBanner(Request $request, Club $club): RedirectResponse
    {
        $this->clubs->removeBanner($request->user(), $club);

        return back()->with('success', 'Banner kartu dihapus; kartu kembali memakai warna bidang.');
    }

    /**
     * Banner image for catalog cards and the profile header; public like the rest of the club profile.
     */
    public function showBanner(Club $club, PrivateFiles $files): StreamedResponse
    {
        abort_unless($club->banner_path, 404);

        return $files->inlineImage($club->banner_path, $club->banner_disk);
    }

    public function appointMember(Request $request, Club $club): RedirectResponse
    {
        $data = $request->validate([
            'user' => 'required|string|max:190',
            'division' => 'nullable|string|max:80',
            'basis' => 'required|string|max:500',
        ]);
        $membership = $this->clubs->appointMember($request->user(), $club, trim($data['user']), $data['division'] ?? null, $data['basis']);

        return back()->with('success', $membership->user->name.' ditetapkan sebagai anggota '.$club->name.'.');
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
            'category' => ($full ? 'required' : 'nullable').'|in:'.implode(',', array_keys(config('brudel.club_categories'))),
            'department_id' => 'nullable|integer|exists:departments,id',
            'field' => ['nullable', Rule::in(app(MasterData::class)->allFields())],
            'description' => 'required|string|max:3000',
            'mentor' => 'required|string|max:120',
            'contact' => 'required|string|max:190',
            'activities' => 'nullable|string|max:3000',
            'symbol' => 'nullable|in:'.implode(',', array_keys(config('brudel.club_symbols'))),
            'active' => 'nullable|boolean',
        ]);
    }
}
