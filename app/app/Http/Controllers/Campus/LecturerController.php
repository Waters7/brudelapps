<?php

namespace App\Http\Controllers\Campus;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Lecturer;
use App\Services\Account\AccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Master Data > Dosen: where kemahasiswaan direktorat opens the accounts imported from the DUK.
 */
class LecturerController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->isStudentAffairs(), 403);
        $search = mb_substr(trim((string) $request->query('q')), 0, 80);
        $placement = (string) $request->query('jurusan');
        $status = (string) $request->query('status');
        $accounts = Lecturer::join('users', 'users.id', '=', 'lecturers.user_id');

        return view('manage.lecturers', [
            'departments' => Department::orderBy('id')->get(),
            'counts' => [
                'total' => (clone $accounts)->count(),
                'waiting' => (clone $accounts)->where('users.active', false)->count(),
                'active' => (clone $accounts)->where('users.active', true)->count(),
                'signed_in' => (clone $accounts)->whereNotNull('users.email_verified_at')->count(),
            ],
            'lecturers' => (clone $accounts)->with('user', 'department')->select('lecturers.*')
                ->when($placement === 'unit', fn ($q) => $q->whereNull('lecturers.department_id'))
                ->when(ctype_digit($placement), fn ($q) => $q->where('lecturers.department_id', (int) $placement))
                ->when($status === 'menunggu', fn ($q) => $q->where('users.active', false))
                ->when($status === 'aktif', fn ($q) => $q->where('users.active', true))
                ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->whereLike('users.name', '%'.$search.'%')->orWhereLike('users.username', '%'.$search.'%')->orWhereLike('users.email', '%'.$search.'%')))
                ->orderBy('users.name')->paginate(150)->withQueryString(),
        ]);
    }

    public function activate(Request $request, AccountService $accounts): RedirectResponse
    {
        $data = $request->validate(['lecturers' => 'required|array|min:1|max:200', 'lecturers.*' => 'integer']);
        $activated = $accounts->activateLecturers($request->user(), $data['lecturers']);

        return back()->with('success', $activated.' dosen diaktifkan dan kini dapat masuk.');
    }
}
