<?php

namespace App\Http\Controllers\Campus;

use App\Http\Controllers\Controller;
use App\Services\Campus\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pengaturan Sistem for kemahasiswaan direktorat: allowlisted .env values editable from the application.
 */
class SystemSettingsController extends Controller
{
    public function __construct(private SystemSettings $settings) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->isStudentAffairs(), 403);

        return view('manage.system', ['state' => $this->settings->state()]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isStudentAffairs(), 403);
        $data = $request->validate([
            'settings' => 'nullable|array',
            'settings.*' => 'nullable|string|max:500',
            'reset' => 'nullable|array',
            'reset.*' => 'string|in:'.implode(',', array_keys(SystemSettings::FIELDS)),
        ]);
        $this->settings->save($request->user(), $data['settings'] ?? [], $data['reset'] ?? []);

        return back()->with('success', 'Pengaturan sistem disimpan dan langsung berlaku.');
    }

    public function testMail(Request $request): RedirectResponse
    {
        $this->settings->sendTest($request->user());

        return back()->with('success', 'Email uji dikirim ke '.$request->user()->email.'.');
    }
}
