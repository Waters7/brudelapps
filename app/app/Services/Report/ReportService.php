<?php

namespace App\Services\Report;

use App\Models\AwardRecipient;
use App\Models\AwardResult;
use App\Models\Certificate;
use App\Models\Competition;
use App\Models\Delegation;
use App\Models\Department;
use App\Models\Event;
use App\Models\Membership;
use App\Models\Participant;
use App\Models\PaymentProof;
use App\Models\Registration;
use App\Models\User;
use App\Services\Wita;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Report module. A team award is one award_result; unique achievers are counted from recipients.
 *
 * @phpstan-type Filters array{year?: ?int, level?: ?int, field?: ?string, department_id?: ?int, club_id?: ?int, team?: ?string}
 */
class ReportService
{
    public const EXPORTS = [
        'pendaftaran' => 'Pendaftaran lomba',
        'pembayaran' => 'Pembayaran',
        'event' => 'Event',
        'delegasi' => 'Delegasi',
        'sertifikat' => 'Sertifikat',
        'prestasi' => 'Prestasi',
        'iku' => 'Prestasi IKU per jurusan',
        'keanggotaan' => 'Keanggotaan UKM',
    ];

    public function canViewDashboard(User $user): bool
    {
        return $user->isStudentAffairs() || $user->isLeadership();
    }

    /**
     * Restrict a query on users to one jurusan.
     */
    private function inDepartment(Builder $users, int $department): Builder
    {
        return $users->whereHas('program', fn ($p) => $p->where('department_id', $department));
    }

    /**
     * @param  Filters  $filters
     */
    public function awards(array $filters): Builder
    {
        return AwardResult::query()->where('status', 'verified')
            ->when($filters['year'] ?? null, fn ($q, $year) => $q->whereYear('awarded_on', $year))
            ->when($filters['level'] ?? null, fn ($q, $level) => $q->where('level', $level))
            ->when($filters['field'] ?? null, fn ($q, $field) => $q->where('field', $field))
            ->when($filters['club_id'] ?? null, fn ($q, $club) => $q->where('club_id', $club))
            ->when(($filters['team'] ?? null) === 'tim', fn ($q) => $q->where('is_team', true))
            ->when(($filters['team'] ?? null) === 'individu', fn ($q) => $q->where('is_team', false))
            ->when($filters['department_id'] ?? null, fn ($q, $department) => $q->whereHas('recipients.user', fn ($u) => $this->inDepartment($u, $department)));
    }

    /**
     * @param  Filters  $filters
     */
    public function registrations(array $filters): Builder
    {
        return Registration::query()->where('status', 'accepted')
            ->whereHas('competition', function ($q) use ($filters) {
                $q->when($filters['year'] ?? null, fn ($c, $year) => $c->whereYear('starts_at', $year))
                    ->when($filters['field'] ?? null, fn ($c, $field) => $c->where('field', $field))
                    ->when($filters['club_id'] ?? null, fn ($c, $club) => $c->whereHas('event', fn ($e) => $e->where('club_id', $club)))
                    ->when(($filters['team'] ?? null) === 'tim', fn ($c) => $c->where('format', 'team'))
                    ->when(($filters['team'] ?? null) === 'individu', fn ($c) => $c->where('format', 'individual'));
            })
            ->when($filters['department_id'] ?? null, fn ($q, $department) => $q->whereHas('participants.user', fn ($u) => $this->inDepartment($u, $department)));
    }

    /**
     * @param  Filters  $filters
     * @return array<string, mixed>
     */
    public function summary(array $filters): array
    {
        $department = $filters['department_id'] ?? null;
        $awards = $this->awards($filters);
        $awardIds = (clone $awards)->pluck('id');
        $recipients = AwardRecipient::whereIn('award_result_id', $awardIds)
            ->when($department, fn ($q) => $q->whereHas('user', fn ($u) => $this->inDepartment($u, $department)));
        $registrations = $this->registrations($filters);
        $registrationIds = (clone $registrations)->pluck('id');
        $participants = Participant::whereIn('registration_id', $registrationIds)
            ->when($department, fn ($q) => $q->whereHas('user', fn ($u) => $this->inDepartment($u, $department)));
        $memberships = Membership::where('status', 'active')
            ->when($filters['club_id'] ?? null, fn ($q, $club) => $q->where('club_id', $club))
            ->when($department, fn ($q) => $q->whereHas('user', fn ($u) => $this->inDepartment($u, $department)));

        return [
            'award_count' => $awardIds->count(),
            'unique_achievers' => (clone $recipients)->distinct()->count('user_id'),
            'recipient_rows' => (clone $recipients)->count(),
            'team_awards' => (clone $awards)->where('is_team', true)->count(),
            'participant_entries' => (clone $participants)->count(),
            'unique_participants' => (clone $participants)->distinct()->count('user_id'),
            'team_count' => (clone $registrations)->whereHas('competition', fn ($q) => $q->where('format', 'team'))->count(),
            'individual_count' => (clone $registrations)->whereHas('competition', fn ($q) => $q->where('format', 'individual'))->count(),
            'membership_count' => (clone $memberships)->count(),
            'unique_members' => (clone $memberships)->distinct()->count('user_id'),
            'by_level' => collect(config('brudel.levels'))->map(fn ($label, $level) => (clone $awards)->where('level', $level)->count()),
            'by_field' => collect(config('brudel.fields'))->mapWithKeys(fn ($field) => [$field => (clone $awards)->where('field', $field)->count()]),
        ];
    }

    /**
     * IKU table: verified awards per jurusan and level. A cross-jurusan team award appears in each jurusan
     * row involved but only once in the campus total.
     *
     * @param  Filters  $filters
     * @return array{rows: list<array{department: Department, levels: array<int, int>, total: int, students: int}>, totals: array{levels: array<int, int>, total: int, students: int}}
     */
    public function ikuByDepartment(array $filters): array
    {
        unset($filters['department_id']);
        $awards = $this->awards($filters)->with('recipients.user.program')->get();
        $levels = array_keys(config('brudel.levels'));
        $rows = [];
        foreach (Department::orderBy('name')->get() as $department) {
            $mine = $awards->filter(fn ($award) => $award->recipients->contains(fn ($r) => $r->user->program?->department_id === $department->id));
            $rows[] = [
                'department' => $department,
                'levels' => collect($levels)->mapWithKeys(fn ($level) => [$level => $mine->where('level', $level)->count()])->all(),
                'total' => $mine->count(),
                'students' => $mine->flatMap->recipients->filter(fn ($r) => $r->user->program?->department_id === $department->id)->pluck('user_id')->unique()->count(),
            ];
        }

        return ['rows' => $rows, 'totals' => [
            'levels' => collect($levels)->mapWithKeys(fn ($level) => [$level => $awards->where('level', $level)->count()])->all(),
            'total' => $awards->count(),
            'students' => $awards->flatMap->recipients->pluck('user_id')->unique()->count(),
        ]];
    }

    /**
     * Rows for a CSV export, limited to what the user may see.
     *
     * @param  Filters  $filters
     * @return array{0: list<string>, 1: iterable<int, list<mixed>>}
     */
    public function export(User $user, string $type, array $filters): array
    {
        $all = $user->isStudentAffairs() || $user->isLeadership();
        $competitions = $user->managedCompetitionIds() ?? collect();
        $clubs = $user->scopeIds('pengurus', 'club_id');
        abort_unless($all || ($competitions->isNotEmpty() && in_array($type, ['pendaftaran', 'pembayaran', 'event', 'sertifikat'], true))
            || ($clubs->isNotEmpty() && $type === 'keanggotaan'), 403);
        $events = Competition::whereIn('id', $competitions)->pluck('event_id')->unique();

        return match ($type) {
            'pendaftaran' => [
                ['ID', 'Nomor lomba', 'Event', 'Nama/tim', 'Format', 'Jumlah anggota', 'Status', 'Pembayaran', 'Dikirim WITA'],
                Registration::with('competition.event', 'user')->withCount('participants')->whereNotIn('status', ['draft', 'cancelled'])
                    ->when(! $all, fn ($q) => $q->whereIn('competition_id', $competitions))
                    ->cursor()->map(fn ($r) => [$r->id, $r->competition->name, $r->competition->event->name, $r->displayName(), $r->competition->format === 'team' ? 'Tim' : 'Individu', $r->participants_count, $r->status, $r->payment_status, Wita::show($r->submitted_at, 'd/m/Y H:i')]),
            ],
            'pembayaran' => [
                ['ID', 'Pendaftaran', 'Nomor lomba', 'Nominal', 'Tanggal transfer', 'Status', 'Alasan', 'Diverifikasi WITA'],
                PaymentProof::with('registration.competition')
                    ->when(! $all, fn ($q) => $q->whereHas('registration', fn ($r) => $r->whereIn('competition_id', $competitions)))
                    ->cursor()->map(fn ($p) => [$p->id, $p->registration_id, $p->registration->competition->name, $p->amount, $p->transferred_on->format('d/m/Y'), $p->status, $p->reason, Wita::show($p->verified_at, 'd/m/Y H:i')]),
            ],
            'event' => [
                ['ID', 'Event', 'Penyelenggara', 'Jalur', 'UKM pengusul', 'Status', 'Jumlah nomor lomba'],
                Event::with('club')->withCount('competitions')->when(! $all, fn ($q) => $q->whereIn('id', $events))
                    ->cursor()->map(fn ($e) => [$e->id, $e->name, $e->organizer, $e->pathway === 'delegation' ? 'Seleksi delegasi' : 'Internal', $e->club?->name, $e->status, $e->competitions_count]),
            ],
            'delegasi' => [
                ['ID', 'Judul', 'Tujuan', 'Tingkat', 'Bidang', 'Mulai WITA', 'Status', 'Nomor surat tugas', 'Hasil'],
                Delegation::query()->cursor()->map(fn ($d) => [$d->id, $d->title, $d->destination_event, config('brudel.levels')[$d->level] ?? $d->level, $d->field, Wita::show($d->starts_at, 'd/m/Y'), $d->status, $d->letter_number, $d->outcome]),
            ],
            'sertifikat' => [
                ['Nomor', 'Penerima', 'Lomba', 'Jenis penghargaan', 'Terbit', 'Status', 'Versi'],
                Certificate::with('user')->when(! $all, fn ($q) => $q->whereIn('competition_id', $competitions))
                    ->cursor()->map(fn ($c) => [$c->number, $c->user->name, $c->title, $c->award_type, $c->issued_on->format('d/m/Y'), $c->status, $c->current_version]),
            ],
            'prestasi' => [
                ['ID', 'Kegiatan', 'Penghargaan', 'Tingkat', 'Bidang', 'Tanggal', 'Tim/individu', 'Jumlah penerima', 'Jurusan penerima', 'Sumber'],
                $this->awards($filters)->with('recipients.user.program.department')->withCount('recipients')->cursor()
                    ->map(fn ($a) => [$a->id, $a->title, $a->award, $a->levelLabel(), $a->field, $a->awarded_on->format('d/m/Y'), $a->is_team ? 'Tim' : 'Individu', $a->recipients_count,
                        $a->recipients->map(fn ($r) => $r->user->program?->department?->name)->filter()->unique()->join('; '), $a->source === 'brudel' ? 'BRUDEL' : 'Eksternal']),
            ],
            'iku' => $this->ikuRows($filters),
            'keanggotaan' => [
                ['UKM', 'Nama', 'Jurusan', 'Prodi', 'Divisi', 'Status', 'Bergabung', 'Keluar'],
                Membership::with('club', 'user.program.department')->when(! $all, fn ($q) => $q->whereIn('club_id', $clubs))
                    ->cursor()->map(fn ($m) => [$m->club->name, $m->user->name, $m->user->program?->department?->name, $m->user->program?->name, $m->division, $m->status, Wita::show($m->joined_at, 'd/m/Y'), Wita::show($m->left_at, 'd/m/Y')]),
            ],
            default => abort(404),
        };
    }

    /**
     * @param  Filters  $filters
     * @return array{0: list<string>, 1: list<list<mixed>>}
     */
    private function ikuRows(array $filters): array
    {
        $iku = $this->ikuByDepartment($filters);
        $header = array_merge(['Jurusan'], array_values(config('brudel.levels')), ['Total penghargaan', 'Mahasiswa berprestasi unik']);
        $rows = array_map(fn ($row) => array_merge([$row['department']->name], array_values($row['levels']), [$row['total'], $row['students']]), $iku['rows']);
        $rows[] = array_merge(['Total kampus (penghargaan tim dihitung sekali)'], array_values($iku['totals']['levels']), [$iku['totals']['total'], $iku['totals']['students']]);

        return [$header, $rows];
    }

    /**
     * Stream rows as UTF-8 CSV with formula-injection protection.
     *
     * @param  list<string>  $header
     * @param  iterable<int, list<mixed>>|Collection<int, list<mixed>>  $rows
     */
    public static function csv(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header, ',', '"', '');
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($cell) => preg_match('/^[=+@\-\t\r\n]/', (string) $cell) ? "'".$cell : $cell, $row), ',', '"', '');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
