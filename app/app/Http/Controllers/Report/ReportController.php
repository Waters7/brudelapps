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
        abort_unless($user->isStudentAffairs() || $user->isLeadership() || $user->managesAnyCompetition() || $user->assigned('pengurus'), 403);
        $filters = $this->filters($request);

        return view('reports.index', [
            'filters' => $filters,
            'dashboard' => $this->reports->canViewDashboard($user),
            'summary' => $this->reports->canViewDashboard($user) ? $this->reports->summary($filters) : null,
            'awards' => $this->reports->canViewDashboard($user) ? $this->reports->awards($filters)->withCount('recipients')->latest('awarded_on')->limit(15)->get() : collect(),
            'iku' => $this->reports->canViewDashboard($user) ? $this->reports->ikuByDepartment($filters) : null,
            'departments' => Department::orderBy('name')->get(),
            'clubs' => Club::orderBy('name')->get(),
            'exports' => ReportService::EXPORTS,
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

        return [
            'year' => isset($data['year']) ? (int) $data['year'] : null,
            'level' => isset($data['level']) ? (int) $data['level'] : null,
            'field' => $data['field'] ?? null,
            'department_id' => isset($data['department_id']) ? (int) $data['department_id'] : null,
            'club_id' => isset($data['club_id']) ? (int) $data['club_id'] : null,
            'team' => $data['team'] ?? null,
        ];
    }
}
