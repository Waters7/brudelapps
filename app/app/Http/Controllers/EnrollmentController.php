<?php

namespace App\Http\Controllers;

use App\Models\ClubApplication;
use App\Models\Competition;
use App\Models\Registration;
use App\Services\PrivateFiles;
use App\Services\Registration\EnrollmentService;
use App\Services\Report\ReportService;
use App\Services\Wita;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EnrollmentController extends Controller
{
    public function __construct(private EnrollmentService $service, private PrivateFiles $files) {}

    public function club(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['motivation' => 'required|string|min:20|max:2000', 'document' => 'nullable|file|mimes:pdf|extensions:pdf|max:5120']);
        $this->service->applyClub($request->user(), $id, $data['motivation'], $request->file('document'));

        return redirect('/pendaftaran')->with('success', 'Pendaftaran UKM berhasil dikirim. Pantau hasil peninjauan di sini.');
    }

    public function clubDocument(Request $request, int $id): StreamedResponse
    {
        $application = ClubApplication::with('recruitment')->findOrFail($id);
        abort_unless($application->user_id === $request->user()->id || $request->user()->managesClub($application->recruitment->club_id), 403);
        abort_unless($application->document_path, 404);

        return $this->files->download($application->document_path, $application->document_disk, 'dokumen-ukm.pdf');
    }

    public function draft(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['motivation' => 'required|string|min:20|max:2000', 'team_name' => 'nullable|string|max:80']);
        if (Competition::findOrFail($id)->format === 'team') {
            $request->validate(['team_name' => 'required|string|min:3|max:80']);
        }
        $registration = $this->service->draft($request->user(), $id, $data);

        return redirect('/pendaftaran#registration-'.$registration->id)->with('success', 'Draf tersimpan. Lengkapi persyaratan, lalu kirim pendaftaran.');
    }

    public function invite(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['nim' => 'required|string|max:30']);
        $this->service->invite($request->user(), $id, $data['nim']);

        return back()->with('success', 'Undangan dikirim. Anggota dapat menyetujuinya melalui akun sendiri.');
    }

    public function respond(Request $request, int $id): RedirectResponse
    {
        $request->validate(['decision' => 'required|in:accept,decline']);
        $this->service->respond($request->user(), $id, $request->input('decision') === 'accept');

        return back()->with('success', 'Tanggapan undangan tersimpan.');
    }

    public function removeMember(Request $request, int $id, int $participant): RedirectResponse
    {
        $this->service->removeMember($request->user(), $id, $participant);

        return back()->with('success', 'Anggota dihapus dari susunan tim.');
    }

    public function cancel(Request $request, int $id): RedirectResponse
    {
        $this->service->cancel($request->user(), $id);

        return back()->with('success', 'Draf dibatalkan dan reservasi anggota dilepas.');
    }

    public function submit(Request $request, int $id): RedirectResponse
    {
        $this->service->submit($request->user(), $id);
        $registration = Registration::findOrFail($id);
        $message = $registration->status === 'program_review' ? 'Pendaftaran dikirim ke prodi untuk persetujuan. Susunan tim dikunci.' : 'Pendaftaran dikirim. Susunan tim telah dikunci.';
        if ($registration->payment_status === 'unpaid') {
            $message .= ' Unggah bukti transfer sebesar Rp '.number_format($registration->fee_snapshot, 0, ',', '.').'.';
        }

        return back()->with('success', $message);
    }

    public function review(Request $request, string $type, int $id): RedirectResponse
    {
        $data = $request->validate(['decision' => 'required|in:accepted,rejected,revision,waitlisted', 'reason' => 'nullable|string|max:2000']);
        if ($type === 'ukm') {
            $this->service->reviewClub($request->user(), $id, $data['decision'], $data['reason'] ?? null);
        } else {
            $this->service->reviewRegistration($request->user(), $id, $data['decision'], $data['reason'] ?? null);
        }

        return back()->with('success', 'Keputusan tersimpan dan tercatat pada riwayat audit.');
    }

    public function upload(Request $request, int $id): RedirectResponse
    {
        $request->validate(['document' => PrivateFiles::RULES]);
        $stored = $this->files->storePdf($request->file('document'), 'registrations/'.$id);
        try {
            DB::transaction(function () use ($request, $id, $stored) {
                $reference = Registration::findOrFail($id);
                Competition::whereKey($reference->competition_id)->lockForUpdate()->firstOrFail();
                $registration = Registration::lockForUpdate()->findOrFail($id);
                abort_unless($registration->user_id === $request->user()->id, 403);
                $this->service->ensure($registration->isEditable(), 'Dokumen pendaftaran sudah dikunci.');
                $registration->update(['document_path' => $stored[0], 'document_disk' => $stored[1]]);
                $this->service->audit($request->user(), 'document.uploaded', $registration);
            }, 3);
        } catch (\Throwable $e) {
            $this->files->delete($stored[0], $stored[1]);
            throw $e;
        }

        return back()->with('success', 'PDF tersimpan privat.');
    }

    public function document(Request $request, int $id): StreamedResponse
    {
        $registration = Registration::with('competition', 'participants')->findOrFail($id);
        $user = $request->user();
        abort_unless($registration->participants->contains('user_id', $user->id) || $user->managesCompetition($registration->competition), 403);
        abort_unless($registration->document_path, 404);

        return $this->files->download($registration->document_path, $registration->document_disk, 'dokumen-pendaftaran.pdf');
    }

    public function export(Request $request, string $type): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user->isReviewer(), 403);
        $query = $type === 'ukm' ? ClubApplication::with('user', 'recruitment.club') : Registration::with('user', 'competition')->whereNot('status', 'cancelled');
        if (! $user->isStudentAffairs()) {
            if ($type === 'ukm') {
                $query->whereHas('recruitment', fn ($q) => $q->whereIn('club_id', $user->scopeIds('pengurus', 'club_id')));
            } else {
                $query->whereIn('competition_id', $user->managedCompetitionIds());
            }
        }

        return ReportService::csv('brudel-'.$type.'.csv', ['ID', 'Nama', 'Kegiatan', 'Status', 'Waktu WITA'], $query->cursor()->map(fn ($row) => [
            $row->id,
            $row->user->name,
            $type === 'ukm' ? $row->recruitment->club->name : $row->competition->name,
            $row->status,
            Wita::show($row->created_at, 'd/m/Y H:i'),
        ]));
    }
}
