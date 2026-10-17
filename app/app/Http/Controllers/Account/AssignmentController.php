<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Club;
use App\Models\Competition;
use App\Models\Department;
use App\Models\Event;
use App\Services\Account\AssignmentService;
use App\Services\Account\OfficerImportService;
use App\Services\Report\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssignmentController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->isStudentAffairs(), 403);
        ['role' => $role, 'scope_id' => $department] = self::splitRole(['role' => (string) $request->query('role')]);

        return view('manage.assignments', [
            'assignments' => Assignment::with('user', 'club', 'event', 'department', 'competition')
                ->when($role !== '', fn ($q) => $q->where('role', $role))
                ->when($department, fn ($q) => $q->where('department_id', $department))
                ->when($request->query('aktif') !== '0', fn ($q) => $q->where('ends_at', '>', now()))
                ->latest()->paginate(30)->withQueryString(),
            'scopes' => self::scopes(),
            'roleOptions' => self::roleOptions(),
            'staffRoleOptions' => self::staffRoleOptions(),
        ]);
    }

    public function store(Request $request, AssignmentService $service): RedirectResponse
    {
        $data = $request->validate([
            'user' => 'required|string|max:190',
            'role' => ['required', 'string', Rule::in(array_keys(self::roleOptions()))],
            'scope_id' => 'nullable|integer',
            'position' => 'nullable|string|max:60',
            'period' => 'nullable|string|max:30',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date',
        ]);
        $service->grant($request->user(), self::splitRole($data));

        return back()->with('success', 'Penugasan disimpan dan tercatat pada audit.');
    }

    /**
     * New staff account (e.g. kemahasiswaan jurusan) with an initial password that must be changed at first sign-in.
     */
    public function storeStaff(Request $request, AssignmentService $service): RedirectResponse
    {
        abort_unless($request->user()->isStudentAffairs(), 403);
        $request->merge(['username' => mb_strtolower(trim((string) $request->input('username')))]);
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'username' => ['required', 'regex:/^[a-z0-9._-]{3,60}$/', 'unique:users,username'],
            'email' => 'nullable|email|max:190|unique:users,email',
            'password' => ['required', 'string', 'confirmed', Password::min(10)->letters()->numbers()],
            'role' => ['required', Rule::in(array_keys(self::staffRoleOptions()))],
            'ends_at' => 'required|date|after:now',
        ]);
        $user = $service->createStaff($request->user(), self::splitRole($data));

        return back()->with('success', 'Akun '.$user->username.' dibuat sebagai '.self::roleOptions()[$data['role']].'. Sampaikan username dan sandi awal secara langsung; sandi wajib diganti saat pertama masuk.');
    }

    /**
     * Preview or import organization chairs from a CSV (organisasi, nama, nim, prodi, jabatan, email).
     */
    public function importOfficers(Request $request, OfficerImportService $officers): RedirectResponse
    {
        $data = $request->validate(['file' => 'required|file|mimes:csv,txt|extensions:csv,txt|max:1024', 'mode' => 'required|in:preview,commit']);
        $result = $officers->import($request->user(), (string) file_get_contents($request->file('file')->getRealPath()), $data['mode'] === 'commit');

        return back()->with('officer_import', $result)->with('success', $result['committed']
            ? $result['assigned'].' penugasan pengurus dibuat, '.$result['created_users'].' akun mahasiswa baru.'
            : 'Pratinjau selesai: periksa hasil per baris sebelum mengimpor.');
    }

    public function officerTemplate(Request $request, OfficerImportService $officers): StreamedResponse
    {
        abort_unless($request->user()->isStudentAffairs(), 403);

        return ReportService::csv('template-ketua-organisasi.csv', ['organisasi', 'nama', 'nim', 'prodi', 'jabatan', 'email'], $officers->template());
    }

    public function end(Request $request, Assignment $assignment, AssignmentService $service): RedirectResponse
    {
        $data = $request->validate(['reason' => 'required|string|min:5|max:500']);
        $service->end($request->user(), $assignment, $data['reason']);

        return back()->with('success', 'Penugasan diakhiri. Riwayat tetap tersimpan.');
    }

    /**
     * Role choices for the forms; kemahasiswaan jurusan is offered once per jurusan as "jurusan:{id}".
     *
     * @return array<string, string>
     */
    public static function roleOptions(): array
    {
        $options = [];
        foreach (config('brudel.roles') as $key => $role) {
            if ($key !== 'jurusan') {
                $options[$key] = $role['label'];

                continue;
            }
            foreach (Department::orderBy('name')->get() as $department) {
                $options['jurusan:'.$department->id] = $role['label'].' '.$department->name;
            }
        }

        return $options;
    }

    /**
     * Roles a new staff account can start with: kemahasiswaan per jurusan or kemahasiswaan direktorat.
     *
     * @return array<string, string>
     */
    public static function staffRoleOptions(): array
    {
        return array_filter(self::roleOptions(), fn ($label, $key) => str_starts_with($key, 'jurusan:') || $key === 'kemahasiswaan', ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Turn a "jurusan:{id}" choice into the jurusan role with its jurusan scope.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function splitRole(array $data): array
    {
        if (preg_match('/^jurusan:(\d+)$/', $data['role'], $match)) {
            return ['role' => 'jurusan', 'scope_id' => (int) $match[1]] + $data;
        }

        return $data + ['scope_id' => null];
    }

    /**
     * Options for each scope column used by role assignments.
     *
     * @return array<string, Collection<int, string>>
     */
    public static function scopes(): array
    {
        return [
            'club_id' => Club::orderBy('name')->pluck('name', 'id'),
            'event_id' => Event::where('status', 'approved')->orderBy('name')->pluck('name', 'id'),
            'department_id' => Department::orderBy('name')->pluck('name', 'id'),
            'competition_id' => Competition::with('event')->get()->mapWithKeys(fn ($c) => [$c->id => $c->name.' · '.$c->event->name]),
        ];
    }
}
