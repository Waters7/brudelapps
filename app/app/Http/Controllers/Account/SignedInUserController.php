<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Account\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Pengaturan › Pengguna: who is signed in now (database sessions) and the sign-in history (audit log).
 */
class SignedInUserController extends Controller
{
    /** A session used within this many minutes counts as online. */
    public const ONLINE_MINUTES = 5;

    public function index(Request $request): View
    {
        abort_unless($request->user()->isStudentAffairs(), 403);
        $search = mb_substr(trim((string) $request->query('cari')), 0, 80);
        $matches = fn ($q) => $q->where(fn ($w) => $w->whereLike('users.name', '%'.$search.'%')->orWhereLike('users.nim', '%'.$search.'%')
            ->orWhereLike('users.username', '%'.$search.'%')->orWhereLike('users.email', '%'.$search.'%'));

        $sessions = DB::table('sessions')->join('users', 'users.id', '=', 'sessions.user_id')
            ->where('sessions.last_activity', '>=', now()->subMinutes((int) config('session.lifetime'))->getTimestamp())
            ->when($search !== '', $matches)
            ->orderByDesc('sessions.last_activity')->limit(500)
            ->get(['sessions.user_id', 'sessions.ip_address', 'sessions.user_agent', 'sessions.last_activity']);
        $users = User::with('currentAssignments.department')->whereIn('id', $sessions->pluck('user_id')->unique())->get()->keyBy('id');
        $signedIn = $sessions->groupBy('user_id')->map(fn (Collection $rows, $id) => [
            'user' => $users->get($id),
            'last' => Carbon::createFromTimestamp($rows->first()->last_activity),
            'ip' => $rows->first()->ip_address,
            'device' => AuditLogService::device($rows->first()->user_agent),
            'devices' => $rows->count(),
        ])->filter(fn ($row) => $row['user'] !== null)->values();

        $history = DB::table('audit_logs')->join('users', 'users.id', '=', 'audit_logs.user_id')->where('audit_logs.action', 'auth.login')
            ->when($search !== '', $matches)
            ->orderByDesc('audit_logs.id')
            ->select('audit_logs.created_at', 'audit_logs.details', 'users.name', 'users.nim', 'users.username', 'users.email')
            ->paginate(30)->withQueryString();

        return view('manage.signed-in-users', [
            'search' => $search,
            'signedIn' => $signedIn,
            'online' => $signedIn->filter(fn ($row) => $row['last']->gt(now()->subMinutes(self::ONLINE_MINUTES)))->count(),
            'todayLogins' => DB::table('audit_logs')->where('action', 'auth.login')->where('created_at', '>=', now(config('brudel.timezone'))->startOfDay()->utc())->count(),
            'history' => $history,
        ]);
    }

    /**
     * Pengaturan › Audit Log: every recorded action, grouped by the actor's assignment at that moment.
     */
    public function audit(Request $request, AuditLogService $audit): View
    {
        abort_unless($request->user()->isStudentAffairs(), 403);
        $groups = $audit->groups();
        $group = array_key_exists((string) $request->query('kelompok'), $groups) ? (string) $request->query('kelompok') : null;
        $module = array_key_exists((string) $request->query('modul'), $audit->modules()) ? (string) $request->query('modul') : null;
        $search = mb_substr(trim((string) $request->query('cari')), 0, 80);

        return view('manage.audit-log', [
            'groups' => $groups,
            'group' => $group,
            'module' => $module,
            'modules' => $audit->modules(),
            'search' => $search,
            'counts' => $audit->counts(),
            'entries' => $audit->entries($group, $search, $module),
        ]);
    }
}
