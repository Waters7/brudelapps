<?php

namespace App\Http\Controllers\Event;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\CompetitionAccount;
use App\Services\Event\CompetitionAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Generated logins of one competition, visible only to the event proposer and kemahasiswaan (superadmin).
 */
class CompetitionAccountController extends Controller
{
    public function __construct(private CompetitionAccountService $accounts) {}

    public function show(Request $request, Competition $competition): View
    {
        $competition->load('event');
        abort_unless($this->accounts->canView($request->user(), $competition), 403);
        $this->accounts->provision($competition, $request->user());

        return view('competitions.accounts', [
            'competition' => $competition,
            'accounts' => $this->accounts->reveal($request->user(), $competition),
        ]);
    }

    public function reset(Request $request, Competition $competition, CompetitionAccount $account): RedirectResponse
    {
        abort_if($account->competition_id !== $competition->id, 404);
        $this->accounts->rotate($request->user(), $account);

        return back()->with('success', 'Kata sandi '.$account->roleLabel().' diganti. Sesi lama akun tersebut diakhiri.');
    }
}
