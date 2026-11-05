<?php

namespace App\Http\Controllers\Campus;

use App\Http\Controllers\Controller;
use App\Models\CompetitionAccount;
use App\Models\Department;
use App\Models\Program;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Master data of user accounts: students grouped by the jurusan of their program, everyone else in one group.
 */
class UserDirectoryController extends Controller
{
    public const STATUSES = ['sudah' => 'Sudah masuk', 'belum' => 'Belum masuk', 'nonaktif' => 'Nonaktif', 'pengurus' => 'Ketua & pengurus'];

    private const OTHERS = 'lainnya';

    public function index(Request $request): View
    {
        abort_unless($request->user()->isStudentAffairs(), 403);
        $departments = Department::orderBy('id')->get();
        $chosen = (string) $request->query('jurusan');
        $selected = $chosen === self::OTHERS ? null : ($departments->firstWhere('id', (int) $chosen) ?? $departments->first());
        $search = mb_substr(trim((string) $request->query('q')), 0, 80);
        $status = (string) $request->query('status');

        return view('manage.users-by-department', [
            'groups' => $this->groups($departments, $selected),
            'selected' => $selected,
            'programs' => $selected ? Program::where('department_id', $selected->id)->orderBy('name')->get() : collect(),
            'users' => $this->inGroup($selected)->with('program', 'currentAssignments.club', 'currentAssignments.department')
                ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->whereLike('nim', '%'.$search.'%')->orWhereLike('name', '%'.$search.'%')->orWhereLike('email', '%'.$search.'%')))
                ->when($selected && $request->query('prodi'), fn ($q) => $q->where('program_id', $request->query('prodi')))
                ->when($status === 'sudah', fn ($q) => $q->whereNotNull('email_verified_at'))
                ->when($status === 'belum', fn ($q) => $q->whereNull('email_verified_at'))
                ->when($status === 'nonaktif', fn ($q) => $q->where('active', false))
                ->when($status === 'pengurus', fn ($q) => $q->whereHas('currentAssignments', fn ($a) => $a->where('role', 'pengurus')))
                ->orderBy('name')->paginate(50)->withQueryString(),
        ]);
    }

    /**
     * Every group with its totals; two grouped queries regardless of how many students there are.
     *
     * @param  Collection<int, Department>  $departments
     * @return Collection<int, array{key: int|string, label: string, total: int, signed_in: int, officers: int, selected: bool}>
     */
    private function groups(Collection $departments, ?Department $selected): Collection
    {
        $inDepartment = fn () => User::join('programs', 'programs.id', '=', 'users.program_id')->whereNotNull('users.nim')->whereNotNull('programs.department_id')->groupBy('programs.department_id');
        $totals = $inDepartment()->selectRaw('programs.department_id, count(*) as total, count(users.email_verified_at) as signed_in')->get()->keyBy('department_id');
        $officers = $inDepartment()->whereHas('currentAssignments', fn ($q) => $q->where('role', 'pengurus'))->selectRaw('programs.department_id, count(*) as total')->pluck('total', 'department_id');

        return $departments->map(fn (Department $department) => [
            'key' => $department->id,
            'label' => $department->name,
            'total' => (int) ($totals[$department->id]->total ?? 0),
            'signed_in' => (int) ($totals[$department->id]->signed_in ?? 0),
            'officers' => (int) ($officers[$department->id] ?? 0),
            'selected' => $selected?->id === $department->id,
        ])->push([
            'key' => self::OTHERS,
            'label' => 'Staf & tanpa jurusan',
            'total' => $this->inGroup(null)->count(),
            'signed_in' => $this->inGroup(null)->whereNotNull('email_verified_at')->count(),
            'officers' => 0,
            'selected' => $selected === null,
        ]);
    }

    /**
     * @return Builder<User>
     */
    private function inGroup(?Department $department): Builder
    {
        return $department
            ? User::whereNotNull('nim')->whereHas('program', fn ($q) => $q->where('department_id', $department->id))
            : User::whereNotIn('id', CompetitionAccount::select('user_id'))->where(fn ($q) => $q->whereNull('nim')->orWhereDoesntHave('program', fn ($p) => $p->whereNotNull('department_id')));
    }
}
