<?php

namespace App\Services\Account;

use App\Http\Controllers\Account\AssignmentController;
use App\Models\Assignment;
use App\Models\Department;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Audit log grouped by the actor's assignment at the moment of the action (kemahasiswaan direktorat,
 * kemahasiswaan jurusan per jurusan, pimpinan, pengurus, …), plus students without an assignment and the system.
 */
class AuditLogService
{
    private const MODULES = [
        'auth' => 'Masuk', 'account' => 'Akun', 'role' => 'Peran', 'students' => 'Data mahasiswa', 'officers' => 'Impor pengurus', 'program' => 'Prodi',
        'master' => 'Master data', 'setting' => 'Pengaturan kampus', 'system' => 'Pengaturan sistem', 'landing' => 'Banner halaman depan', 'club' => 'UKM',
        'membership' => 'Keanggotaan', 'recruitment' => 'Rekrutmen', 'space' => 'Ruang UKM', 'activity' => 'Kegiatan', 'event' => 'Event', 'competition' => 'Lomba',
        'competition_accounts' => 'Akun lomba', 'registration' => 'Pendaftaran lomba', 'team' => 'Tim', 'payment' => 'Pembayaran', 'payment_account' => 'Rekening',
        'department' => 'Persetujuan jurusan', 'rubric' => 'Rubrik', 'score' => 'Penilaian', 'bracket' => 'Bagan', 'match' => 'Pertandingan', 'results' => 'Hasil lomba',
        'performance' => 'Penampilan', 'certificate' => 'Sertifikat', 'award' => 'Prestasi', 'delegation' => 'Delegasi', 'report' => 'Laporan', 'support' => 'Bantuan',
        'profile' => 'Profil',
    ];

    private const VERBS = [
        'login' => 'masuk', 'created' => 'dibuat', 'saved' => 'disimpan', 'updated' => 'diperbarui', 'deleted' => 'dihapus', 'submitted' => 'diajukan',
        'approved' => 'disetujui', 'accepted' => 'diterima', 'rejected' => 'ditolak', 'verified' => 'diverifikasi', 'revision' => 'diminta revisi',
        'waitlisted' => 'masuk daftar tunggu', 'cancelled' => 'dibatalkan', 'imported' => 'diimpor', 'siakad_imported' => 'diimpor dari SIAKAD',
        'previewed' => 'dipratinjau', 'published' => 'dipublikasikan', 'reopened' => 'dibuka kembali', 'issued' => 'diterbitkan', 'revoked' => 'dicabut',
        'replaced' => 'diganti', 'granted' => 'diberikan', 'ended' => 'diakhiri', 'exported' => 'diekspor', 'uploaded' => 'diunggah', 'proposed' => 'diusulkan',
        'completed' => 'selesai', 'letter' => 'surat tugas diunggah', 'activated' => 'diaktifkan', 'deactivated' => 'dinonaktifkan',
        'setup_completed' => 'dilengkapi', 'contact_updated' => 'email/HP diubah', 'password_changed' => 'kata sandi diganti', 'password_reset' => 'kata sandi diatur ulang',
        'staff_created' => 'staf dibuat', 'system_admin_set' => 'superadmin diatur', 'google_login' => 'masuk dengan Google', 'photo_updated' => 'foto diperbarui',
        'photo_removed' => 'foto dihapus', 'consent' => 'persetujuan publikasi diubah', 'provisioned' => 'dibuat', 'rotated' => 'kata sandi diganti', 'viewed' => 'dilihat',
        'appointed' => 'ditetapkan', 'opened' => 'dibuka', 'closed' => 'ditutup', 'announced' => 'diumumkan', 'announcement_deleted' => 'pengumuman dihapus',
        'message_removed' => 'pesan dihapus', 'quest_created' => 'quest dibuat', 'quest_closed' => 'quest ditutup', 'code_opened' => 'kode hadir dibuka',
        'code_closed' => 'kode hadir ditutup', 'marked_present' => 'kehadiran dicatat', 'reported' => 'dilaporkan', 'banner_updated' => 'banner diperbarui',
        'banner_removed' => 'banner dihapus', 'slide_added' => 'ditambahkan', 'slide_updated' => 'diperbarui', 'slide_deleted' => 'dihapus', 'settings_updated' => 'diperbarui',
        'generated' => 'dibuat', 'seeded' => 'diunggulkan', 'scheduled' => 'dijadwalkan', 'winner' => 'pemenang dicatat', 'corrected' => 'dikoreksi', 'invited' => 'diundang',
        'removed' => 'dikeluarkan', 'invitation_expired' => 'undangan kedaluwarsa', 'draft' => 'disimpan sebagai draf',
    ];

    /**
     * Group choices: every role (kemahasiswaan jurusan once per jurusan), all jurusan together, students, and the system.
     *
     * @return array<string, string>
     */
    public function groups(): array
    {
        $groups = [];
        foreach (AssignmentController::roleOptions() as $key => $label) {
            if (str_starts_with($key, 'jurusan:') && ! isset($groups['jurusan'])) {
                $groups['jurusan'] = 'Kemahasiswaan Jurusan (semua jurusan)';
            }
            $groups[$key] = $label;
        }

        return $groups + ['mahasiswa' => 'Mahasiswa (tanpa penugasan)', 'sistem' => 'Sistem'];
    }

    /**
     * Entries of one group, newest first, with the actor's roles at that moment.
     */
    public function entries(?string $group, string $search, ?string $module): LengthAwarePaginator
    {
        $page = DB::table('audit_logs')->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')
            ->when($group, fn ($q) => $this->inGroup($q, $group))
            ->when($module, fn ($q) => $q->where(fn ($w) => $w->where('audit_logs.action', 'like', $module.'.%')))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->whereLike('users.name', '%'.$search.'%')->orWhereLike('users.nim', '%'.$search.'%')
                ->orWhereLike('users.username', '%'.$search.'%')->orWhereLike('users.email', '%'.$search.'%')))
            ->orderByDesc('audit_logs.id')
            ->select('audit_logs.*', 'users.name', 'users.nim', 'users.username', 'users.email')
            ->paginate(40)->withQueryString();

        $assignments = Assignment::with('department')->whereIn('user_id', collect($page->items())->pluck('user_id')->filter()->unique())->get()->groupBy('user_id');
        $page->through(function ($entry) use ($assignments) {
            $at = Carbon::parse($entry->created_at);
            $entry->roles = ($assignments->get($entry->user_id) ?? collect())
                ->filter(fn (Assignment $a) => $a->starts_at->lte($at) && $a->ends_at->gt($at))->map->roleLabel()->unique()->values();
            $entry->label = $this->label($entry->action);
            $entry->summary = $this->summary(json_decode((string) $entry->details, true) ?: []);

            return $entry;
        });

        return $page;
    }

    /**
     * How many entries each group has within the last days, for the group overview.
     *
     * @return Collection<string, int>
     */
    public function counts(int $days = 30): Collection
    {
        $since = now()->subDays($days);
        $byRole = DB::table('audit_logs')->join('assignments', fn ($join) => $this->duringAssignment($join))
            ->where('audit_logs.created_at', '>=', $since)
            ->groupBy('assignments.role', 'assignments.department_id')
            ->selectRaw('assignments.role, assignments.department_id, count(distinct audit_logs.id) as total')->get();
        $counts = collect();
        foreach ($byRole as $row) {
            $key = $row->role === 'jurusan' ? 'jurusan:'.$row->department_id : $row->role;
            $counts[$key] = ($counts[$key] ?? 0) + (int) $row->total;
        }
        $counts['jurusan'] = DB::table('audit_logs')->where('created_at', '>=', $since)->whereExists(fn ($q) => $this->assignmentAtTheTime($q, 'jurusan'))->count();
        $counts['mahasiswa'] = DB::table('audit_logs')->join('users', 'users.id', '=', 'audit_logs.user_id')->where('audit_logs.created_at', '>=', $since)
            ->whereNotNull('users.nim')->whereNotExists(fn ($q) => $this->assignmentAtTheTime($q))->count();
        $counts['sistem'] = DB::table('audit_logs')->where('created_at', '>=', $since)->whereNull('user_id')->count();

        return $counts;
    }

    /**
     * Modules present in the log, for the filter.
     *
     * @return array<string, string>
     */
    public function modules(): array
    {
        return self::MODULES;
    }

    public function label(string $action): string
    {
        [$module, $verb] = array_pad(explode('.', $action, 2), 2, '');

        return (self::MODULES[$module] ?? ucfirst(str_replace('_', ' ', $module))).' '.(self::VERBS[$verb] ?? str_replace('_', ' ', $verb));
    }

    private function inGroup(Builder $query, string $group): void
    {
        match (true) {
            $group === 'sistem' => $query->whereNull('audit_logs.user_id'),
            $group === 'mahasiswa' => $query->whereNotNull('users.nim')->whereNotExists(fn ($q) => $this->assignmentAtTheTime($q)),
            (bool) preg_match('/^jurusan:(\d+)$/', $group, $match) => $query->whereExists(fn ($q) => $this->assignmentAtTheTime($q, 'jurusan', (int) $match[1])),
            default => $query->whereExists(fn ($q) => $this->assignmentAtTheTime($q, $group)),
        };
    }

    /**
     * Correlated subquery: the actor held the role (in that jurusan) when the entry was written.
     */
    private function assignmentAtTheTime(Builder $query, ?string $role = null, ?int $department = null): void
    {
        $query->select(DB::raw(1))->from('assignments')->where(fn ($join) => $this->duringAssignment($join))
            ->when($role, fn ($q) => $q->where('assignments.role', $role))
            ->when($department, fn ($q) => $q->where('assignments.department_id', $department));
    }

    private function duringAssignment($join): void
    {
        $join->whereColumn('assignments.user_id', 'audit_logs.user_id')
            ->whereColumn('assignments.starts_at', '<=', 'audit_logs.created_at')
            ->whereColumn('assignments.ends_at', '>', 'audit_logs.created_at');
    }

    /**
     * Short "key: value" text of the scalar details; the browser identification is shortened.
     *
     * @param  array<string, mixed>  $details
     */
    private function summary(array $details): string
    {
        return collect($details)->map(fn ($value, $key) => match (true) {
            $key === 'agent' => 'perangkat: '.self::device((string) $value),
            is_scalar($value) || $value === null => $key.': '.($value === null ? '—' : (is_bool($value) ? ($value ? 'ya' : 'tidak') : $value)),
            default => $key.': '.mb_strimwidth((string) json_encode($value, JSON_UNESCAPED_UNICODE), 0, 60, '…'),
        })->implode(' · ');
    }

    /**
     * Short browser and system name from a user agent, e.g. "Chrome · Android".
     */
    public static function device(?string $agent): string
    {
        $agent = (string) $agent;
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') => 'Opera',
            str_contains($agent, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'Peramban lain',
        };
        $system = match (true) {
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone'), str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'perangkat lain',
        };

        return $browser.' · '.$system;
    }

    /**
     * Name of a jurusan group, for headings.
     */
    public function groupLabel(?string $group): ?string
    {
        if ($group === null || $group === '') {
            return null;
        }
        if (preg_match('/^jurusan:(\d+)$/', $group, $match)) {
            return 'Kemahasiswaan Jurusan '.Department::whereKey((int) $match[1])->value('name');
        }

        return $this->groups()[$group] ?? null;
    }
}
