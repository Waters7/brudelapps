<?php

namespace App\Http\Controllers\Campus;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Services\Account\EmployeeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Master Data > Dosen and Tendik: where kemahasiswaan direktorat opens the accounts imported from the DUK.
 */
class EmployeeController extends Controller
{
    public function __construct(private EmployeeService $employees) {}

    public function index(Request $request, string $kind): View
    {
        abort_unless($request->user()->isStudentAffairs(), 403);
        $search = mb_substr(trim((string) $request->query('q')), 0, 80);
        $placement = (string) $request->query('jurusan');
        $status = (string) $request->query('status');
        $accounts = Employee::where('employees.kind', $kind)->join('users', 'users.id', '=', 'employees.user_id');

        return view('manage.employees', [
            'kind' => $kind,
            'label' => Employee::KINDS[$kind],
            'departments' => Department::orderBy('id')->get(),
            'tasks' => $this->employees->tasks(),
            'counts' => [
                'total' => (clone $accounts)->count(),
                'waiting' => (clone $accounts)->where('users.active', false)->count(),
                'active' => (clone $accounts)->where('users.active', true)->count(),
                'signed_in' => (clone $accounts)->whereNotNull('users.email_verified_at')->count(),
            ],
            'employees' => (clone $accounts)->with('user.currentAssignments.club', 'user.currentAssignments.department', 'department')->select('employees.*')
                ->when($placement === 'unit', fn ($q) => $q->whereNull('employees.department_id'))
                ->when(ctype_digit($placement), fn ($q) => $q->where('employees.department_id', (int) $placement))
                ->when($status === 'menunggu', fn ($q) => $q->where('users.active', false))
                ->when($status === 'aktif', fn ($q) => $q->where('users.active', true))
                ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->whereLike('users.name', '%'.$search.'%')->orWhereLike('users.username', '%'.$search.'%')->orWhereLike('users.email', '%'.$search.'%')))
                ->orderBy('users.name')->paginate(150)->withQueryString(),
        ]);
    }

    public function activate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employees' => 'required|array|min:1|max:200',
            'employees.*' => 'integer',
            'task' => ['nullable', 'string', Rule::in(array_keys(Arr::collapse($this->employees->tasks())))],
            'ends_at' => 'required_with:task|nullable|date|after:now',
        ]);
        $result = $this->employees->apply($request->user(), $data['employees'], $data['task'] ?? null, $data['ends_at'] ?? null);

        return back()->with('success', collect([
            $result['activated'] ? $result['activated'].' akun diaktifkan' : null,
            $result['assigned'] ? $result['assigned'].' akun ditugaskan' : null,
            $result['already'] ? 'sudah memiliki tugas itu: '.implode(', ', $result['already']) : null,
        ])->filter()->join('; ') ?: 'Tidak ada perubahan.');
    }
}
