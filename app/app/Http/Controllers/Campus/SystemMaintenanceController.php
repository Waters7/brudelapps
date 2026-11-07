<?php

namespace App\Http\Controllers\Campus;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Campus\DatabaseBackup;
use App\Services\Campus\StorageMonitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Pengelolaan sistem for kemahasiswaan direktorat: storage use with an early warning, and database backup and restore.
 */
class SystemMaintenanceController extends Controller
{
    public function __construct(private StorageMonitor $storage, private DatabaseBackup $backups) {}

    public function storage(Request $request): View
    {
        $this->authorizeAdmin($request);

        return view('manage.storage', [
            'disk' => $this->storage->disk(),
            'application' => $this->storage->application($request->boolean('hitung')),
            'thresholds' => $this->storage->thresholds(),
        ]);
    }

    public function thresholds(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate(['warning' => 'required|integer|min:50|max:95', 'critical' => 'required|integer|min:55|max:99|gt:warning']);
        DB::transaction(function () use ($data) {
            Setting::updateOrCreate(['key' => 'storage_warning_percent'], ['value' => (string) $data['warning']]);
            Setting::updateOrCreate(['key' => 'storage_critical_percent'], ['value' => (string) $data['critical']]);
        });
        $this->storage->alert();

        return back()->with('success', 'Ambang peringatan penyimpanan disimpan.');
    }

    public function backups(Request $request): View
    {
        $this->authorizeAdmin($request);

        return view('manage.backups', ['backups' => $this->backups->all(), 'disk' => $this->storage->disk()]);
    }

    public function createBackup(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $name = $this->backups->create($request->user());

        return back()->with('success', 'Cadangan '.$name.' dibuat.');
    }

    public function uploadBackup(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $request->validate(['backup' => 'required|file|max:512000']);
        $name = $this->backups->upload($request->user(), $request->file('backup'));

        return back()->with('success', 'Cadangan '.$name.' diunggah dan tanda tangannya cocok. Pulihkan dari daftar bila diperlukan.');
    }

    public function download(Request $request, string $name): BinaryFileResponse
    {
        $this->authorizeAdmin($request);
        $this->backups->verify($name);
        $this->backups->audit($request->user(), 'backup.downloaded', $request->user(), ['file' => $name]);

        return response()->download($this->backups->path($name), $name, ['Content-Type' => 'application/gzip']);
    }

    public function restore(Request $request, string $name): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate(['confirmation' => 'required|string', 'password' => 'required|string']);
        if ($data['confirmation'] !== 'PULIHKAN') {
            throw ValidationException::withMessages(['confirmation' => 'Ketik PULIHKAN dengan huruf kapital untuk melanjutkan.']);
        }
        if (! Hash::check($data['password'], $request->user()->password)) {
            throw ValidationException::withMessages(['password' => 'Kata sandi Anda salah.']);
        }
        $result = $this->backups->restore($request->user(), $name);

        return redirect('/kelola/sistem/backup')->with('success', 'Database dipulihkan dari '.$name.' ('.$result['rows'].' baris). Kondisi sebelumnya tersimpan sebagai '.$result['safety'].'.');
    }

    public function destroy(Request $request, string $name): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $this->backups->delete($request->user(), $name);

        return back()->with('success', 'Cadangan dihapus.');
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()->isStudentAffairs(), 403);
    }
}
