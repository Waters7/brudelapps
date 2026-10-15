<?php

namespace App\Http\Controllers\Department;

use App\Http\Controllers\Controller;
use App\Models\AwardResult;
use App\Models\Certificate;
use App\Models\Department;
use App\Models\DepartmentApproval;
use App\Models\User;
use App\Services\Registration\EnrollmentService;
use App\Services\Report\ReportService;
use App\Services\Wita;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Jurusan workspace: approvals for the jurusan's students, their UKM participation, and their
 * winner certificates and verified achievements. Every query is limited to the reviewer's own jurusan.
 */
class DepartmentController extends Controller
{
    public function index(Request $request): View
    {
        $departments = $this->departments($request);
        $selected = $departments->firstWhere('id', (int) $request->query('jurusan')) ?? $departments->first();
        $filter = $request->query('ukm');
        // Every student is in their HIMAJU automatically, so participation means an active UKM, BEM, or MPM membership.
        $elective = fn ($q) => $q->whereHas('club', fn ($c) => $c->where('category', '!=', 'himaju'));
        $activeElective = fn ($m) => $m->where('status', 'active')->where($elective);
        $students = User::with(['program', 'memberships' => fn ($q) => $q->where($elective)->with('club')->orderByRaw("status = 'active' desc")])
            ->whereNotNull('nim')->where('active', true)
            ->whereHas('program', fn ($q) => $q->where('department_id', $selected?->id))
            ->when($filter === 'aktif', fn ($q) => $q->whereHas('memberships', $activeElective))
            ->when($filter === 'tidak', fn ($q) => $q->whereDoesntHave('memberships', $activeElective))
            ->orderBy('name')->paginate(30)->withQueryString();
        $inDepartment = fn ($q) => $q->whereHas('program', fn ($p) => $p->where('department_id', $selected?->id));
        $total = User::whereNotNull('nim')->where('active', true)->where($inDepartment)->count();
        $activeInClub = User::whereNotNull('nim')->where('active', true)->where($inDepartment)->whereHas('memberships', $activeElective)->count();

        return view('departments.index', [
            'departments' => $departments,
            'selected' => $selected,
            'students' => $students,
            'filter' => $filter,
            'counts' => ['total' => $total, 'active' => $activeInClub, 'inactive' => $total - $activeInClub],
            'pending' => DepartmentApproval::with('department', 'registration.competition.event', 'registration.participants.user.program')
                ->where('department_id', $selected?->id)->where('status', 'pending')->oldest()->get(),
            'decided' => DepartmentApproval::with('department', 'registration.competition', 'decider')->where('department_id', $selected?->id)
                ->where('status', '!=', 'pending')->latest('decided_at')->limit(10)->get(),
            'certificates' => Certificate::with('user.program', 'competition')->where('status', 'valid')->whereNotNull('award_recipient_id')
                ->whereHas('user', $inDepartment)->latest('issued_on')->limit(50)->get(),
            'awards' => AwardResult::with(['recipients' => fn ($q) => $q->with('user.program')])->where('status', 'verified')
                ->whereHas('recipients.user', $inDepartment)->latest('awarded_on')->limit(50)->get(),
        ]);
    }

    public function decide(Request $request, DepartmentApproval $approval, EnrollmentService $service): RedirectResponse
    {
        $data = $request->validate(['decision' => 'required|in:approved,rejected', 'reason' => 'nullable|string|max:2000']);
        $service->decideDepartment($request->user(), $approval->id, $data['decision'], $data['reason'] ?? null);

        return back()->with('success', 'Keputusan jurusan tersimpan.');
    }

    public function export(Request $request, Department $department): StreamedResponse
    {
        abort_unless($request->user()->reviewsDepartment($department->id), 403);
        $type = $request->query('data', 'ukm');
        $inDepartment = fn ($q) => $q->whereHas('program', fn ($p) => $p->where('department_id', $department->id));

        if ($type === 'prestasi') {
            $rows = AwardResult::with('recipients.user.program')->where('status', 'verified')->whereHas('recipients.user', $inDepartment)->cursor()
                ->map(fn ($award) => [$award->title, $award->award, $award->levelLabel(), $award->field, $award->awarded_on->format('d/m/Y'), $award->is_team ? 'Tim' : 'Individu',
                    $award->recipients->filter(fn ($r) => $r->user->program?->department_id === $department->id)->map(fn ($r) => $r->user->name.' ('.$r->user->nim.')')->join('; ')]);

            return ReportService::csv('prestasi-jurusan-'.$department->code.'.csv', ['Kegiatan', 'Penghargaan', 'Tingkat', 'Bidang', 'Tanggal', 'Format', 'Mahasiswa jurusan'], $rows);
        }

        $elective = fn ($q) => $q->whereHas('club', fn ($c) => $c->where('category', '!=', 'himaju'))->with('club');
        $rows = User::with(['program', 'memberships' => $elective])->whereNotNull('nim')->where('active', true)->where($inDepartment)->orderBy('name')->lazy()
            ->map(fn ($student) => [$student->nim, $student->name, $student->program?->name,
                $student->memberships->where('status', 'active')->map(fn ($m) => $m->club->name.($m->division ? ' ('.$m->division.')' : ''))->join('; ') ?: 'Tidak aktif di UKM, BEM, maupun MPM',
                $student->memberships->where('status', '!=', 'active')->map(fn ($m) => $m->club->name.' – '.(config('brudel.status_labels')[$m->status] ?? $m->status))->join('; '),
                Wita::show($student->memberships->max('joined_at'), 'd/m/Y')]);

        return ReportService::csv('ukm-mahasiswa-jurusan-'.$department->code.'.csv', ['NIM', 'Nama', 'Prodi', 'UKM/BEM/MPM aktif', 'Riwayat lain', 'Bergabung terakhir'], $rows);
    }

    /**
     * Jurusan the user may review; kemahasiswaan sees all seven.
     *
     * @return Collection<int, Department>
     */
    private function departments(Request $request): Collection
    {
        $user = $request->user();
        abort_unless($user->isStudentAffairs() || $user->assigned('jurusan'), 403);

        return Department::when(! $user->isStudentAffairs(), fn ($q) => $q->whereIn('id', $user->scopeIds('jurusan', 'department_id')))->orderBy('name')->get();
    }
}
