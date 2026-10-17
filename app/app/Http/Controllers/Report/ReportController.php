<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\Department;
use App\Services\Campus\MasterData;
use App\Services\Report\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->isStudentAffairs() || $user->isLeadership() || $user->managesAnyCompetition() || $user->assigned('pengurus') || $user->assigned('jurusan'), 403);
        $filters = $this->filters($request);
        $scope = $this->reports->departmentScope($user);
        $dashboard = $this->reports->canViewDashboard($user);

        return view('reports.index', [
            'filters' => $filters,
            'dashboard' => $dashboard,
            'summary' => $dashboard ? $this->reports->summary($filters) : null,
            'awards' => $dashboard ? $this->reports->awards($filters)->withCount('recipients')->latest('awarded_on')->limit(15)->get() : collect(),
            'iku' => $dashboard ? $this->reports->ikuByDepartment($filters, $scope) : null,
            'departments' => Department::when($scope !== null, fn ($q) => $q->whereIn('id', $scope))->orderBy('name')->get(),
            'scoped' => $scope !== null,
            'clubs' => Club::orderBy('name')->get(),
            'exports' => $this->reports->exportsFor($user),
        ]);
    }

    public function export(Request $request, string $type): StreamedResponse
    {
        abort_unless(array_key_exists($type, ReportService::EXPORTS), 404);
        [$header, $rows] = $this->reports->export($request->user(), $type, $this->filters($request));
        DB::table('audit_logs')->insert([
            'user_id' => $request->user()->id,
            'action' => 'report.exported',
            'subject_type' => 'reports',
            'subject_id' => 0,
            'details' => json_encode(['type' => $type, 'filters' => array_filter($this->filters($request))]),
            'created_at' => now(),
        ]);

        return ReportService::csv('brudel-laporan-'.$type.'-'.now()->format('Ymd').'.csv', $header, $rows);
    }

    /**
     * @return array{year: ?int, level: ?int, field: ?string, department_id: ?int, club_id: ?int, team: ?string}
     */
    private function filters(Request $request): array
    {
        $data = $request->validate([
            'year' => 'nullable|integer|between:2000,2100',
            'level' => 'nullable|integer|between:1,6',
            'field' => ['nullable', Rule::in(app(MasterData::class)->allFields())],
            'department_id' => 'nullable|integer',
            'club_id' => 'nullable|integer',
            'team' => 'nullable|in:tim,individu',
        ]);

        $department = isset($data['department_id']) ? (int) $data['department_id'] : null;
        // Kemahasiswaan jurusan always reports on its own jurusan, whatever the query string says.
        $scope = $this->reports->departmentScope($request->user());
        if ($scope !== null && ! $scope->contains($department)) {
            $department = $scope->first();
        }

        return [
            'year' => isset($data['year']) ? (int) $data['year'] : null,
            'level' => isset($data['level']) ? (int) $data['level'] : null,
            'field' => $data['field'] ?? null,
            'department_id' => $department,
            'club_id' => isset($data['club_id']) ? (int) $data['club_id'] : null,
            'team' => $data['team'] ?? null,
        ];
    }
}
