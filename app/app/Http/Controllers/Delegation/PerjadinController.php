<?php

namespace App\Http\Controllers\Delegation;

use App\Http\Controllers\Controller;
use App\Models\Delegation;
use App\Services\Delegation\PerjadinService;
use App\Services\Report\ReportService;
use App\Services\Wita;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Kemahasiswaan direktorat menu for student official-travel letters (perjadin) of delegations, prepared for Pangi.
 */
class PerjadinController extends Controller
{
    public function __construct(private PerjadinService $perjadin) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->isStudentAffairs(), 403);
        $delegations = $this->perjadin->delegations();

        return view('delegations.perjadin-index', [
            'delegations' => $delegations,
            'readiness' => $delegations->mapWithKeys(fn ($delegation) => [$delegation->id => $this->perjadin->readiness($delegation)]),
            'connected' => $this->perjadin->connected(),
        ]);
    }

    public function show(Request $request, Delegation $delegation): View
    {
        abort_unless($request->user()->isStudentAffairs(), 403);
        $delegation->load('members.user.program.department', 'competition.event', 'decider');

        return view('delegations.perjadin-show', [
            'delegation' => $delegation,
            'readiness' => $this->perjadin->readiness($delegation),
            'ready' => $this->perjadin->isReady($delegation),
            'connected' => $this->perjadin->connected(),
        ]);
    }

    public function json(Request $request, Delegation $delegation): JsonResponse
    {
        abort_unless($request->user()->isStudentAffairs(), 403);
        $delegation->load('members.user.program.department', 'competition.event', 'decider');
        $this->perjadin->audit($request->user(), 'perjadin.exported', $delegation);

        return response()->json($this->perjadin->payload($delegation), 200, [
            'Content-Disposition' => 'attachment; filename="perjadin-delegasi-'.$delegation->id.'.json"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function csv(Request $request): StreamedResponse
    {
        abort_unless($request->user()->isStudentAffairs(), 403);
        $rows = $this->perjadin->delegations()->flatMap(fn (Delegation $delegation) => $delegation->members->map(fn ($member) => [
            $delegation->id, $delegation->title, $delegation->destination_event, $delegation->location,
            Wita::show($delegation->starts_at, 'd/m/Y'), Wita::show($delegation->ends_at, 'd/m/Y'), $delegation->letter_number,
            $member->role, $member->user->name, $member->user->nim, $member->user->program?->name, $member->user->program?->department?->name,
            $this->perjadin->isReady($delegation) ? 'Siap' : 'Belum lengkap',
        ]));

        return ReportService::csv('perjadin-delegasi.csv', ['ID delegasi', 'Delegasi', 'Kegiatan', 'Lokasi', 'Berangkat', 'Kembali', 'No. surat tugas', 'Peran', 'Nama', 'NIM', 'Prodi', 'Jurusan', 'Kesiapan data'], $rows);
    }
}
