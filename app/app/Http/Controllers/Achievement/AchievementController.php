<?php

namespace App\Http\Controllers\Achievement;

use App\Http\Controllers\Controller;
use App\Models\AwardRecipient;
use App\Models\AwardResult;
use App\Models\User;
use App\Services\Achievement\AchievementService;
use App\Services\Campus\MasterData;
use App\Services\PrivateFiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AchievementController extends Controller
{
    public function __construct(private AchievementService $achievements) {}

    public function mine(Request $request): View
    {
        return view('achievements.mine', [
            'recipients' => AwardRecipient::with('award')->where('user_id', $request->user()->id)->latest()->get(),
            'user' => $request->user(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:190',
            'organizer' => 'required|string|max:190',
            'field' => ['required', Rule::in(app(MasterData::class)->allFields())],
            'level' => 'required|integer|between:1,6',
            'award' => 'required|string|max:80',
            'awarded_on' => 'required|date|before_or_equal:today',
            'team_name' => 'nullable|string|max:80',
            'members' => 'nullable|string|max:500',
            'documentation_url' => 'nullable|url:https|max:255',
            'evidence' => PrivateFiles::RULES,
        ]);
        $this->achievements->submitExternal($request->user(), $data, $request->file('evidence'));

        return back()->with('success', 'Prestasi eksternal dikirim dan menunggu verifikasi kemahasiswaan.');
    }

    public function evidence(Request $request, AwardResult $award, PrivateFiles $files): StreamedResponse
    {
        abort_unless($this->achievements->canViewEvidence($request->user(), $award), 403);
        abort_unless($award->evidence_path, 404);

        return $files->download($award->evidence_path, $award->evidence_disk, 'bukti-prestasi-'.$award->id.'.pdf');
    }

    public function manage(Request $request): View
    {
        abort_unless($request->user()->isStudentAffairs(), 403);

        return view('achievements.manage', [
            'pending' => AwardResult::with('recipients.user.program', 'submitter')->where('status', 'pending')->oldest()->get(),
            'recent' => AwardResult::withCount('recipients')->where('status', '!=', 'pending')->latest('updated_at')->limit(20)->get(),
        ]);
    }

    public function verify(Request $request, AwardResult $award): RedirectResponse
    {
        $data = $request->validate(['decision' => 'required|in:verified,rejected', 'reason' => 'nullable|string|max:1000']);
        $this->achievements->verify($request->user(), $award, $data['decision'], $data['reason'] ?? null);

        return back()->with('success', 'Verifikasi prestasi tersimpan.');
    }

    public function profile(string $slug): View
    {
        $user = User::where('public_slug', $slug)->where('publication_consent', true)->where('active', true)->firstOrFail();
        $awards = AwardRecipient::with('award')->where('user_id', $user->id)
            ->whereHas('award', fn ($q) => $q->where('status', 'verified'))->get()
            ->sortByDesc(fn ($r) => $r->award->awarded_on)->groupBy(fn ($r) => $r->award->field);

        return view('achievements.public', ['student' => $user, 'awards' => $awards]);
    }
}
