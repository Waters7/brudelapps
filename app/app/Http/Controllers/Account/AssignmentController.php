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
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssignmentController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->isStudentAffairs(), 403);

        return view('manage.assignments', [
            'assignments' => Assignment::with('user', 'club', 'event', 'department', 'competition')
                ->when($request->query('role'), fn ($q, $role) => $q->where('role', $role))
                ->when($request->query('aktif') !== '0', fn ($q) => $q->where('ends_at', '>', now()))
                ->latest()->paginate(30)->withQueryString(),
            'scopes' => self::scopes(),
        ]);
    }

    public function store(Request $request, AssignmentService $service): RedirectResponse
    {
        $data = $request->validate([
            'user' => 'required|string|max:190',
            'role' => 'required|string|in:'.implode(',', array_keys(config('brudel.roles'))),
            'scope_id' => 'nullable|integer',
            'position' => 'nullable|string|max:60',
            'period' => 'nullable|string|max:30',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date',
        ]);
        $service->grant($request->user(), $data);

        return back()->with('success', 'Penugasan disimpan dan tercatat pada audit.');
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
