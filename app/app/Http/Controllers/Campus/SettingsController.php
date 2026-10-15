<?php

namespace App\Http\Controllers\Campus;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\PaymentAccount;
use App\Models\Program;
use App\Models\Setting;
use App\Services\Campus\CampusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(private CampusService $campus) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->isStudentAffairs(), 403);

        return view('manage.settings', [
            'programs' => Program::with('department')->withCount('users')->orderBy('department_id')->orderBy('name')->get(),
            'departments' => Department::orderBy('name')->get(),
            'accounts' => PaymentAccount::latest()->get(),
            'settings' => collect(CampusService::SETTING_KEYS)->mapWithKeys(fn ($key) => [$key => Setting::value($key)]),
        ]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'regional_definition' => 'required|string|max:1000',
            'refund_policy' => 'required|string|max:3000',
            'certificate_prefix' => 'required|string|max:30',
        ]);
        $this->campus->saveSettings($request->user(), $data);

        return back()->with('success', 'Pengaturan kampus disimpan.');
    }

    public function saveProgram(Request $request, ?Program $program = null): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120|unique:programs,name'.($program ? ','.$program->id : ''),
            'department_id' => 'required|integer|exists:departments,id',
            'code' => 'nullable|string|max:20|alpha_dash|unique:programs,code'.($program ? ','.$program->id : ''),
        ]);
        $this->campus->saveProgram($request->user(), $program, $data);

        return back()->with('success', 'Program studi disimpan.');
    }

    public function addAccount(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'bank_name' => 'required|string|max:80',
            'account_number' => 'required|string|max:40|regex:/^[0-9 .\-]+$/',
            'account_holder' => 'required|string|max:120',
            'notes' => 'nullable|string|max:500',
        ]);
        $this->campus->addAccount($request->user(), $data);

        return back()->with('success', 'Rekening resmi ditambahkan. Pastikan data sesuai dokumen resmi kampus.');
    }

    public function toggleAccount(Request $request, PaymentAccount $account): RedirectResponse
    {
        $this->campus->toggleAccount($request->user(), $account, ! $account->active);

        return back()->with('success', 'Status rekening diperbarui.');
    }
}
