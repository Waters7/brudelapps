<?php

namespace App\Http\Controllers;

use App\Models\AwardRecipient;
use App\Models\AwardResult;
use App\Models\Certificate;
use App\Models\Club;
use App\Models\ClubActivity;
use App\Models\ClubApplication;
use App\Models\Competition;
use App\Models\Delegation;
use App\Models\DepartmentApproval;
use App\Models\Event;
use App\Models\Membership;
use App\Models\Participant;
use App\Models\PaymentAccount;
use App\Models\PaymentProof;
use App\Models\Recruitment;
use App\Models\Registration;
use App\Models\Setting;
use App\Models\User;
use App\Services\Achievement\AchievementService;
use App\Services\Report\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortalController extends Controller
{
    /**
     * Public front page for guests; the personal dashboard for signed-in users.
     */
    public function home(Request $request, ReportService $reports): View
    {
        if ($request->user()) {
            return $this->dashboard($request, $reports);
        }

        return view('landing', [
            'competitions' => Competition::with('event')->whereHas('event', fn ($q) => $q->where('status', 'approved'))
                ->where('closes_at', '>', now())->orderBy('closes_at')->limit(6)->get(),
            'activities' => ClubActivity::with('club')->where('is_public', true)->where('status', '!=', 'cancelled')
                ->where('ends_at', '>', now())->orderBy('starts_at')->limit(6)->get(),
            'recruitments' => Recruitment::with('club')->whereHas('club', fn ($q) => $q->where('active', true))
                ->where('opens_at', '<=', now())->where('closes_at', '>', now())->orderBy('closes_at')->limit(6)->get(),
            'stats' => [
                'clubs' => Club::where('active', true)->count(),
                'open' => Competition::whereHas('event', fn ($q) => $q->where('status', 'approved'))->where('opens_at', '<=', now())->where('closes_at', '>', now())->count(),
                'awards' => AwardResult::where('status', 'verified')->count(),
            ],
        ]);
    }

    public function dashboard(Request $request, ReportService $reports): View
    {
        $user = $request->user();
        if ($user->isLeadership() && ! $user->isStudentAffairs() && ! $user->nim) {
            return $this->leadershipDashboard($request, $reports);
        }
        $uid = $user->id;

        return view('dashboard', [
            'clubs' => Club::where('active', true)->withCount(['memberships' => fn ($q) => $q->where('status', 'active')])->limit(3)->get(),
            'competitions' => Competition::with('event')->whereHas('event', fn ($q) => $q->where('status', 'approved'))->where('closes_at', '>', now())->orderBy('closes_at')->limit(3)->get(),
            'memberCount' => Membership::where('user_id', $uid)->where('status', 'active')->count(),
            'applicationCount' => ClubApplication::where('user_id', $uid)->count() + Registration::whereNot('status', 'cancelled')->whereHas('participants', fn ($q) => $q->where('user_id', $uid))->count(),
            'invitationCount' => Participant::where('user_id', $uid)->where('status', 'invited')->count(),
            'openCount' => Competition::whereHas('event', fn ($q) => $q->where('status', 'approved'))->where('opens_at', '<=', now())->where('closes_at', '>', now())->count(),
            'certificateCount' => Certificate::where('user_id', $uid)->where('status', 'valid')->count(),
            'awardCount' => AwardRecipient::where('user_id', $uid)->whereHas('award', fn ($q) => $q->where('status', 'verified'))->count(),
            'recent' => ClubApplication::with('recruitment.club')->where('user_id', $uid)->latest('updated_at')->limit(3)->get(),
            'notifications' => $user->unreadNotifications()->limit(4)->get(),
            'tasks' => $this->staffTasks($user),
        ]);
    }

    /**
     * Direktur and wakil direktur see the report summary and delegation matters only.
     */
    private function leadershipDashboard(Request $request, ReportService $reports): View
    {
        $filters = ['year' => (int) $request->query('year', now()->year)];

        return view('leadership', [
            'filters' => $filters,
            'summary' => $reports->summary($filters),
            'iku' => $reports->ikuByDepartment($filters),
            'pending' => Delegation::with('members.user')->where('status', 'submitted')->oldest('submitted_at')->get(),
            'notifications' => $request->user()->notifications()->latest()->limit(40)->get()
                ->filter(fn ($notification) => str_starts_with($notification->data['url'] ?? '', '/delegasi'))->take(8),
            'position' => $request->user()->currentAssignments()->where('role', 'pimpinan')->value('position'),
        ]);
    }

    /**
     * Pending work in the user's assignment scope, shown on the dashboard.
     *
     * @return list<array{0: string, 1: int, 2: string}>
     */
    private function staffTasks(User $user): array
    {
        $all = $user->isStudentAffairs();
        $clubs = $user->scopeIds('pengurus', 'club_id');
        $managed = $user->managedCompetitionIds();
        $departments = $user->scopeIds('jurusan', 'department_id');
        $tasks = [];
        if ($all || $clubs->isNotEmpty()) {
            $tasks[] = ['Pendaftar UKM menunggu', ClubApplication::whereIn('status', ['submitted', 'waitlisted'])->when(! $all, fn ($q) => $q->whereHas('recruitment', fn ($r) => $r->whereIn('club_id', $clubs)))->count(), '/tinjauan'];
        }
        if ($managed === null || $managed->isNotEmpty()) {
            $inScope = fn ($q) => $managed === null ? $q : $q->whereIn('competition_id', $managed);
            $tasks[] = ['Pendaftaran lomba menunggu', Registration::where('status', 'submitted')->where($inScope)->count(), '/tinjauan'];
            $tasks[] = ['Bukti pembayaran menunggu', PaymentProof::where('status', 'pending')->whereHas('registration', $inScope)->count(), '/pembayaran'];
        }
        if ($all || $departments->isNotEmpty()) {
            $tasks[] = ['Persetujuan jurusan menunggu', DepartmentApproval::where('status', 'pending')->when(! $all, fn ($q) => $q->whereIn('department_id', $departments))->count(), '/jurusan'];
        }
        if ($all) {
            $tasks[] = ['Pengajuan event menunggu', Event::where('status', 'submitted')->count(), '/kelola/event'];
            $tasks[] = ['Prestasi menunggu verifikasi', AwardResult::where('status', 'pending')->count(), '/kelola/prestasi'];
        }
        if ($user->isLeadership()) {
            $tasks[] = ['Usulan delegasi menunggu keputusan', Delegation::where('status', 'submitted')->count(), '/delegasi'];
        }
        if ($user->assigned('penilai')) {
            $tasks[] = ['Nomor lomba untuk dinilai', $user->scopeIds('penilai', 'competition_id')->count(), '/penilaian'];
        }
        $coached = $user->scopeIds('pembina', 'club_id')->merge($user->currentAssignments()->where('role', 'pengurus')->where('position', 'Ketua')->pluck('club_id'))->unique();
        if ($coached->isNotEmpty()) {
            $tasks[] = ['Laporan kegiatan UKM belum dibuat', ClubActivity::whereIn('club_id', $coached)->where('status', 'scheduled')->where('ends_at', '<', now())->count(), '/kegiatan'];
        }

        return $tasks;
    }

    public function club(int $id): View
    {
        $club = Club::with([
            'recruitments' => fn ($q) => $q->latest('opens_at'),
            'officers' => fn ($q) => $q->where('starts_at', '<=', now())->where('ends_at', '>', now())->with('user'),
            'events' => fn ($q) => $q->where('status', 'approved')->latest(),
            'awards' => fn ($q) => $q->latest('awarded_on')->limit(6),
            'clubActivities' => fn ($q) => $q->where('is_public', true)->where('ends_at', '>', now())->orderBy('starts_at')->limit(5),
        ])->findOrFail($id);

        return view('club', [
            'club' => $club,
            'activeMembers' => $club->memberships()->where('status', 'active')->count(),
            'applications' => ClubApplication::where('user_id', auth()->id())->whereIn('recruitment_id', $club->recruitments->pluck('id'))->get()->keyBy('recruitment_id'),
        ]);
    }

    public function competition(int $id): View
    {
        $competition = Competition::with('event')->whereHas('event', fn ($q) => $q->where('status', 'approved'))->findOrFail($id);

        return view('competition', [
            'competition' => $competition,
            'draft' => Registration::where('competition_id', $id)->where('user_id', auth()->id())->first(),
            'holding' => $competition->registrations()->whereIn('status', Registration::HOLDING)->count(),
            'accounts' => $competition->isPaid() ? PaymentAccount::where('active', true)->get() : collect(),
            'refundPolicy' => Setting::value('refund_policy'),
        ]);
    }

    public function applications(Request $request): View
    {
        $uid = $request->user()->id;

        return view('applications', [
            'applications' => ClubApplication::with('recruitment.club', 'histories')->where('user_id', $uid)->latest()->get(),
            'registrations' => Registration::with('competition.event', 'participants.user', 'approvals.department', 'payments', 'histories')
                ->whereNot('status', 'cancelled')->whereHas('participants', fn ($q) => $q->where('user_id', $uid))->latest()->get(),
            'invitations' => Participant::with('registration.competition', 'registration.user')->where('user_id', $uid)->where('status', 'invited')->get(),
            'accounts' => PaymentAccount::where('active', true)->get(),
            'refundPolicy' => Setting::value('refund_policy'),
        ]);
    }

    public function memberships(Request $request): View
    {
        return view('memberships', ['memberships' => Membership::with('club', 'histories')->where('user_id', $request->user()->id)->orderByRaw("status = 'active' desc")->latest('joined_at')->get()]);
    }

    public function review(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->isReviewer(), 403);
        $clubs = ClubApplication::with('user.program', 'recruitment.club')->whereIn('status', ['submitted', 'waitlisted']);
        $registrations = Registration::with('user', 'competition', 'participants.user.program', 'approvals.department')->where('status', 'submitted');
        if (! $user->isStudentAffairs()) {
            $clubs->whereHas('recruitment', fn ($q) => $q->whereIn('club_id', $user->scopeIds('pengurus', 'club_id')));
            $registrations->whereIn('competition_id', $user->managedCompetitionIds());
        }

        return view('review', ['applications' => $clubs->oldest()->get(), 'registrations' => $registrations->oldest()->get()]);
    }

    public function profile(Request $request): View
    {
        return view('profile', [
            'user' => $request->user()->load('program.department'),
            'managedAccount' => $request->user()->isCompetitionAccount(),
            'googleEnabled' => filled(config('services.google.client_id')),
        ]);
    }

    public function saveProfile(Request $request, AchievementService $achievements): RedirectResponse
    {
        $request->validate(['publication_consent' => 'nullable|boolean']);
        $achievements->setConsent($request->user(), $request->boolean('publication_consent'));

        return back()->with('success', 'Preferensi publikasi tersimpan.');
    }

    public function notifications(Request $request): View
    {
        return view('notifications', ['notifications' => $request->user()->notifications()->latest()->paginate(20)]);
    }

    public function readNotifications(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }
}
