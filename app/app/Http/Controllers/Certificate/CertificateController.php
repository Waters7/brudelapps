<?php

namespace App\Http\Controllers\Certificate;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Competition;
use App\Services\Certificate\CertificateService;
use App\Services\PrivateFiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CertificateController extends Controller
{
    public function __construct(private CertificateService $certificates) {}

    public function mine(Request $request): View
    {
        return view('certificates.mine', ['certificates' => Certificate::with('competition')->where('user_id', $request->user()->id)->latest('issued_on')->get()]);
    }

    public function download(Request $request, Certificate $certificate, PrivateFiles $files): StreamedResponse
    {
        abort_unless($this->certificates->canDownload($request->user(), $certificate), 403);
        $version = $request->query('versi') && $request->user()->managesCompetition($certificate->competition)
            ? $certificate->versions()->where('version', (int) $request->query('versi'))->firstOrFail()
            : $certificate->current();
        abort_unless($version, 404);

        return $files->download($version->path, $version->disk, 'sertifikat-'.str($certificate->number)->slug().'.pdf');
    }

    public function manage(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->isStudentAffairs() || $user->assigned('admin_event'), 403);
        $competitions = Competition::with(['event', 'registrations' => fn ($q) => $q->where('status', 'accepted')->with('participants.user')])
            ->when(! $user->isStudentAffairs(), fn ($q) => $q->whereIn('event_id', $user->scopeIds('admin_event', 'event_id')))
            ->whereHas('registrations', fn ($q) => $q->where('status', 'accepted'))->get();
        $selected = $competitions->firstWhere('id', (int) $request->query('lomba'));

        return view('certificates.manage', [
            'competitions' => $competitions,
            'selected' => $selected,
            'certificates' => Certificate::with('user', 'versions')->whereIn('competition_id', $competitions->pluck('id'))
                ->when($selected, fn ($q) => $q->where('competition_id', $selected->id))->latest()->limit(100)->get(),
        ]);
    }

    public function issue(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'competition_id' => 'required|integer',
            'user_id' => 'required|integer',
            'number' => 'required|string|max:80|unique:certificates,number',
            'award_type' => 'required|string|max:80',
            'issued_on' => 'required|date',
            'file' => PrivateFiles::RULES,
        ]);
        $this->certificates->issue($request->user(), $data, $request->file('file'));

        return back()->with('success', 'Sertifikat diterbitkan dan tersedia di Sertifikat Saya penerima.');
    }

    public function replace(Request $request, Certificate $certificate): RedirectResponse
    {
        $data = $request->validate(['file' => PrivateFiles::RULES, 'reason' => 'required|string|min:5|max:500']);
        $this->certificates->replace($request->user(), $certificate, $request->file('file'), $data['reason']);

        return back()->with('success', 'Versi baru sertifikat disimpan. Versi lama tetap tercatat.');
    }

    public function revoke(Request $request, Certificate $certificate): RedirectResponse
    {
        $data = $request->validate(['reason' => 'required|string|min:5|max:500']);
        $this->certificates->revoke($request->user(), $certificate, $data['reason']);

        return back()->with('success', 'Sertifikat dicabut. Tautan verifikasi kini menampilkan status dicabut.');
    }

    public function verify(string $token): View
    {
        $certificate = Certificate::with('user')->where('verification_token', $token)->first();

        return view('certificates.verify', ['certificate' => $certificate]);
    }
}
