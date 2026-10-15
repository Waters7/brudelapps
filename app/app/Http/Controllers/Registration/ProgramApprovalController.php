<?php

namespace App\Http\Controllers\Registration;

use App\Http\Controllers\Controller;
use App\Models\ClubApplication;
use App\Models\Program;
use App\Models\ProgramApproval;
use App\Models\Registration;
use App\Services\Registration\EnrollmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProgramApprovalController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->isStudentAffairs() || $user->assigned('prodi'), 403);
        $programs = $user->isStudentAffairs() ? Program::pluck('id') : $user->scopeIds('prodi', 'program_id');

        return view('prodi.index', [
            'programs' => Program::whereIn('id', $programs)->orderBy('name')->get(),
            'pending' => ProgramApproval::with('program', 'registration.competition.event', 'registration.participants.user.program')
                ->whereIn('program_id', $programs)->where('status', 'pending')->oldest()->get(),
            'decided' => ProgramApproval::with('program', 'registration.competition', 'decider')->whereIn('program_id', $programs)
                ->where('status', '!=', 'pending')->latest('decided_at')->limit(15)->get(),
            'registrations' => Registration::with('competition', 'participants.user')->whereIn('status', ['program_review', 'submitted', 'accepted'])
                ->whereHas('participants.user', fn ($q) => $q->whereIn('program_id', $programs))->latest('submitted_at')->limit(30)->get(),
            'clubApplications' => ClubApplication::with('recruitment.club', 'user.program')->whereHas('user', fn ($q) => $q->whereIn('program_id', $programs))->latest()->limit(30)->get(),
        ]);
    }

    public function decide(Request $request, ProgramApproval $approval, EnrollmentService $service): RedirectResponse
    {
        $data = $request->validate(['decision' => 'required|in:approved,rejected', 'reason' => 'nullable|string|max:2000']);
        $service->decideProgram($request->user(), $approval->id, $data['decision'], $data['reason'] ?? null);

        return back()->with('success', 'Keputusan prodi tersimpan.');
    }
}
