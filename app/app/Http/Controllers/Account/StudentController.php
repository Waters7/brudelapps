<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Program;
use App\Models\StudentImport;
use App\Models\User;
use App\Services\Account\AccountService;
use App\Services\Account\StudentImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->isStudentAffairs() || $user->assigned('jurusan'), 403);
        // Kemahasiswaan jurusan sees only the students of its own jurusan; importing stays with direktorat.
        $scope = $user->studentDepartments();
        $search = mb_substr(trim((string) $request->query('q')), 0, 80);
        $students = User::with('program')->whereNotNull('nim')
            ->when($scope !== null, fn ($q) => $q->whereHas('program', fn ($p) => $p->whereIn('department_id', $scope)))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->whereLike('nim', '%'.$search.'%')->orWhereLike('name', '%'.$search.'%')->orWhereLike('email', '%'.$search.'%')))
            ->when($request->query('program'), fn ($q, $program) => $q->where('program_id', $program))
            ->when($request->query('status') === 'nonaktif', fn ($q) => $q->where('active', false))
            ->when($request->query('status') === 'belum', fn ($q) => $q->whereNull('email_verified_at'))
            ->orderBy('name')->paginate(25)->withQueryString();

        return view('manage.students', [
            'students' => $students,
            'canImport' => $scope === null,
            'departments' => $scope === null ? collect() : Department::whereIn('id', $scope)->orderBy('name')->get(),
            'programs' => Program::when($scope !== null, fn ($q) => $q->whereIn('department_id', $scope))->orderBy('name')->get(),
            'imports' => $scope === null ? StudentImport::with('user')->latest()->limit(8)->get() : collect(),
            'lastImport' => $scope === null && session('import_id') ? StudentImport::find(session('import_id')) : null,
        ]);
    }

    public function import(Request $request, StudentImportService $service): RedirectResponse
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:2048', 'mode' => 'required|in:preview,commit']);
        $import = $service->import($request->user(), $request->file('file'), $request->input('mode') === 'commit');
        $message = $import->mode === 'commit'
            ? "Impor selesai: {$import->created_count} akun baru, {$import->updated_count} diperbarui, {$import->failed_count} baris gagal."
            : "Pratinjau: {$import->total_rows} baris, {$import->failed_count} baris bermasalah. Belum ada data yang disimpan.";

        return back()->with('success', $message)->with('import_id', $import->id);
    }

    public function status(Request $request, User $student, AccountService $accounts): RedirectResponse
    {
        $data = $request->validate(['active' => 'required|boolean', 'reason' => 'required|string|min:5|max:500']);
        $accounts->setStatus($request->user(), $student, (bool) $data['active'], $data['reason']);

        return back()->with('success', 'Status '.$student->name.' diperbarui.');
    }

    public function sendActivation(Request $request, User $student, AccountService $accounts): RedirectResponse
    {
        abort_unless($request->user()->administersStudent($student), 403);
        $accounts->sendActivation($student->email);

        return back()->with('success', 'Tautan aktivasi diproses untuk '.$student->email.' (dikirim bila akun belum aktif dan tidak dikirim dalam 1 menit terakhir).');
    }
}
