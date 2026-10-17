<?php

namespace App\Http\Controllers\Club;

use App\Http\Controllers\Controller;
use App\Models\ActivityAttendance;
use App\Models\Club;
use App\Models\ClubActivity;
use App\Models\Membership;
use App\Services\Campus\MasterData;
use App\Services\Club\ActivityService;
use App\Services\PrivateFiles;
use App\Services\Report\ReportService;
use App\Services\Wita;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityController extends Controller
{
    public function __construct(private ActivityService $activities) {}

    /**
     * Jelajah Kegiatan: upcoming public activities, activities of my UKM, and the check-in box.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $myClubs = Membership::where('user_id', $user->id)->where('status', 'active')->pluck('club_id');
        $coached = Club::coachedBy($user)->orderBy('name')->get();

        return view('activities.index', [
            'upcoming' => ClubActivity::with('club')->visibleTo($user)->where('status', '!=', 'cancelled')->where('ends_at', '>', now())
                ->when($request->query('ukm'), fn ($q, $club) => $q->where('club_id', $club))
                ->when($request->query('jenis'), fn ($q, $type) => $q->where('type', $type))
                ->orderBy('starts_at')->limit(40)->get(),
            'past' => ClubActivity::with('club')->withCount('attendances')->whereIn('club_id', $myClubs->merge($coached->pluck('id')))
                ->where('ends_at', '<=', now())->latest('starts_at')->limit(20)->get(),
            'attended' => $user->nim ? ActivityAttendance::where('user_id', $user->id)->pluck('club_activity_id') : collect(),
            'coached' => $coached,
            'clubs' => Club::where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $club = Club::findOrFail((int) $request->input('club_id'));
        $data = $request->validate([
            'club_id' => 'required|integer',
            'type' => ['required', Rule::in(array_keys(app(MasterData::class)->activityTypes()))],
            'title' => 'required|string|max:150',
            'description' => 'nullable|string|max:3000',
            'location' => 'required|string|max:190',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date',
            'is_public' => 'nullable|boolean',
            'needs_room' => 'nullable|boolean',
            'room' => 'nullable|string|max:150',
        ]);
        $activity = $this->activities->create($request->user(), $club, $data);

        return redirect('/kegiatan/'.$activity->id)->with('success', $activity->awaitsPermit()
            ? 'Kegiatan dibuat dan menunggu izin '.$activity->permitLevelLabel().' ('.$activity->permit_reasons.'). Anggota diberi tahu setelah izin disetujui.'
            : 'Kegiatan dibuat dan anggota aktif mendapat notifikasi.');
    }

    /**
     * Permit requests the user decides: HIMAJU for kemahasiswaan jurusan, all other organizations for direktorat.
     */
    public function permits(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->isStudentAffairs() || $user->assigned('jurusan'), 403);
        $candidates = ClubActivity::with('club.department', 'creator', 'decider')->whereNotNull('permit_status')->latest('id')->limit(200)->get()
            ->filter(fn ($activity) => $this->activities->canDecidePermit($user, $activity) || $activity->permit_decided_by === $user->id);

        return view('activities.permits', [
            'pending' => $candidates->where('permit_status', 'pending')->sortBy('starts_at'),
            'decided' => $candidates->where('permit_status', '!=', 'pending')->take(20),
        ]);
    }

    public function decidePermit(Request $request, ClubActivity $activity): RedirectResponse
    {
        $data = $request->validate(['decision' => 'required|in:approved,rejected', 'note' => 'nullable|string|max:1000']);
        $this->activities->decidePermit($request->user(), $activity, $data['decision'], $data['note'] ?? null);

        return back()->with('success', $data['decision'] === 'approved' ? 'Izin disetujui; anggota mendapat notifikasi.' : 'Izin ditolak dan kegiatan dibatalkan.');
    }

    public function show(Request $request, ClubActivity $activity): View
    {
        abort_unless($this->activities->canView($request->user(), $activity), 403);
        $canManage = $this->activities->canManage($request->user(), $activity);

        return view('activities.show', [
            'activity' => $activity->load('club', 'creator', 'decider'),
            'canManage' => $canManage,
            'canDecide' => $this->activities->canDecidePermit($request->user(), $activity),
            'summary' => $canManage ? $this->activities->attendanceSummary($activity) : null,
            'attended' => $activity->attendances()->where('user_id', $request->user()->id)->first(),
        ]);
    }

    public function openCode(Request $request, ClubActivity $activity): RedirectResponse
    {
        $data = $request->validate(['minutes' => 'nullable|integer|min:5|max:480']);
        $code = $this->activities->openCode($request->user(), $activity, $data['minutes'] ?? null);

        return back()->with('success', 'Kode kehadiran '.$code.' aktif. Tampilkan kepada anggota yang hadir.');
    }

    public function closeCode(Request $request, ClubActivity $activity): RedirectResponse
    {
        $this->activities->closeCode($request->user(), $activity);

        return back()->with('success', 'Kode kehadiran ditutup.');
    }

    public function checkIn(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => 'required|string|size:6']);
        $activity = $this->activities->checkIn($request->user(), $data['code']);

        return redirect('/kegiatan/'.$activity->id)->with('success', 'Kehadiran tercatat untuk '.$activity->title.'.');
    }

    public function markPresent(Request $request, ClubActivity $activity): RedirectResponse
    {
        $data = $request->validate(['user_id' => 'required|integer']);
        $this->activities->markPresent($request->user(), $activity, $data['user_id']);

        return back()->with('success', 'Kehadiran dicatat manual.');
    }

    public function report(Request $request, ClubActivity $activity): RedirectResponse
    {
        $data = $request->validate([
            'opponent' => 'nullable|string|max:150',
            'score_home' => 'nullable|integer|min:0|max:1000',
            'score_away' => 'nullable|integer|min:0|max:1000',
            'notes' => 'nullable|string|max:5000',
            'minutes' => 'nullable|file|mimes:pdf,docx|extensions:pdf,docx|max:5120',
        ]);
        $this->activities->report($request->user(), $activity, $data, $request->file('minutes'));

        return back()->with('success', 'Laporan kegiatan disimpan. Unduh laporan dari halaman ini.');
    }

    public function cancel(Request $request, ClubActivity $activity): RedirectResponse
    {
        $data = $request->validate(['reason' => 'required|string|min:5|max:500']);
        $this->activities->cancel($request->user(), $activity, $data['reason']);

        return back()->with('success', 'Kegiatan dibatalkan.');
    }

    /**
     * Printable report (save as PDF from the browser).
     */
    public function printable(Request $request, ClubActivity $activity): View
    {
        abort_unless($this->activities->canManage($request->user(), $activity) || $request->user()->managesClub($activity->club_id), 403);

        return view('activities.report', ['activity' => $activity->load('club', 'creator'), 'summary' => $this->activities->attendanceSummary($activity)]);
    }

    public function csv(Request $request, ClubActivity $activity): StreamedResponse
    {
        abort_unless($this->activities->canManage($request->user(), $activity) || $request->user()->managesClub($activity->club_id), 403);
        $summary = $this->activities->attendanceSummary($activity);
        $rows = $summary['present']->map(fn ($a) => ['Hadir', $a->user->name, $a->user->nim, $a->user->program?->name, Wita::show($a->checked_in_at, 'd/m/Y H:i'), $a->method === 'code' ? 'Kode' : 'Manual'])
            ->concat($summary['absent']->map(fn ($u) => ['Tidak hadir', $u->name, $u->nim, $u->program?->name, '', '']));

        return ReportService::csv('laporan-kegiatan-'.$activity->id.'.csv', ['Kehadiran', 'Nama', 'NIM', 'Prodi', 'Waktu WITA', 'Cara'], $rows);
    }

    public function minutes(Request $request, ClubActivity $activity, PrivateFiles $files): StreamedResponse
    {
        abort_unless($this->activities->canView($request->user(), $activity), 403);
        abort_unless($activity->minutes_path, 404);

        return $files->download($activity->minutes_path, $activity->minutes_disk, 'notulen-'.$activity->id.'.'.pathinfo($activity->minutes_path, PATHINFO_EXTENSION));
    }
}
