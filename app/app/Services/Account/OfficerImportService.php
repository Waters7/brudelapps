<?php

namespace App\Services\Account;

use App\Models\Assignment;
use App\Models\Club;
use App\Models\Department;
use App\Models\Membership;
use App\Models\Program;
use App\Models\User;
use App\Notifications\BrudelNotice;
use App\Services\DomainService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Imports organization chairs (and other officers) from a CSV: organisasi, nama, nim, prodi, jabatan, email.
 * A student not yet in BRUDEL gets an account keyed by NIM; without an email it gets a placeholder that the
 * official student import (matched by NIM) replaces before the student activates the account.
 */
class OfficerImportService extends DomainService
{
    public const MAX_ROWS = 500;

    public const PLACEHOLDER_DOMAIN = 'impor-ketua.brudel.invalid';

    private const ALIASES = [
        'organisasi' => 'organization', 'ukm' => 'organization', 'nama_organisasi' => 'organization',
        'nama' => 'name', 'nama_lengkap' => 'name', 'nim' => 'nim', 'prodi' => 'program', 'program_studi' => 'program',
        'jabatan' => 'position', 'posisi' => 'position', 'email' => 'email', 'periode' => 'period',
    ];

    /** Program-name keywords for a jurusan, as in the jurusan migration. */
    private const DEPARTMENT_KEYWORDS = [
        'gigi' => 'KG', 'bidan' => 'KEB', 'rawat' => 'KEP', 'ners' => 'KEP', 'gizi' => 'GIZ', 'lingkungan' => 'KL',
        'sanitasi' => 'KL', 'farmasi' => 'FAR', 'laborator' => 'TLM', 'analis' => 'TLM',
    ];

    /** Common short names for a jurusan in "HMJ …". */
    private const DEPARTMENT_ALIASES = ['kesling' => 'KL', 'tlm' => 'TLM', 'kg' => 'KG', 'gizi' => 'GIZ', 'keperawatan' => 'KEP', 'kebidanan' => 'KEB', 'farmasi' => 'FAR'];

    /**
     * @return array{rows: list<array<string, mixed>>, errors: int, created_users: int, assigned: int, new_programs: list<string>, committed: bool}
     */
    public function import(User $actor, string $content, bool $commit): array
    {
        abort_unless($actor->isStudentAffairs(), 403);
        [$rows, $headerError] = $this->read($content);
        $this->ensure($headerError === null, $headerError ?? '');
        $this->ensure(count($rows) <= self::MAX_ROWS, 'Maksimal '.self::MAX_ROWS.' baris per impor.');
        $checked = $this->validate($rows);

        $summary = ['rows' => $checked, 'errors' => count(array_filter($checked, fn ($row) => $row['messages'])), 'created_users' => 0, 'assigned' => 0,
            'new_programs' => array_values(array_unique(array_filter(array_column($checked, 'new_program')))), 'committed' => $commit];
        if (! $commit) {
            $this->audit($actor, 'officers.previewed', $actor, ['rows' => count($checked)]);

            return $summary;
        }

        return DB::transaction(function () use ($actor, $checked, $summary) {
            $programs = [];
            foreach ($checked as $index => $row) {
                if ($row['messages'] || $row['skip']) {
                    continue;
                }
                $programId = $row['program_id'] ?? ($programs[$row['new_program']] ??= Program::create([
                    'name' => $row['new_program'], 'department_id' => $row['new_program_department'],
                ])->id);
                $user = $row['user_id'] ? User::findOrFail($row['user_id']) : null;
                if (! $user) {
                    $user = User::create([
                        'name' => $row['name'], 'nim' => $row['nim'], 'program_id' => $programId, 'active' => true,
                        'email' => $row['email'] ?: mb_strtolower($row['nim']).'@'.self::PLACEHOLDER_DOMAIN, 'password' => Hash::make(Str::random(48)),
                    ]);
                    $summary['created_users']++;
                }
                $club = Club::findOrFail($row['club_id']);
                if ($club->category !== 'himaju') {
                    $membership = Membership::firstOrNew(['club_id' => $club->id, 'user_id' => $user->id]);
                    if (! $membership->exists || $membership->status !== 'active') {
                        $from = $membership->exists ? $membership->status : null;
                        $membership->fill(['status' => 'active', 'joined_at' => $membership->joined_at ?? now(), 'left_at' => null, 'status_reason' => 'Pengurus organisasi (impor ketua)'])->save();
                        $this->history($membership, $from, 'active', $actor, 'Pengurus organisasi (impor ketua)');
                    }
                }
                $this->ensure(Membership::where('club_id', $club->id)->where('user_id', $user->id)->where('status', 'active')->exists(),
                    'Baris '.$row['line'].': '.$row['name'].' bukan anggota aktif '.$club->name.'.');
                $assignment = Assignment::create([
                    'user_id' => $user->id, 'role' => 'pengurus', 'club_id' => $club->id, 'position' => $row['position'], 'period' => $row['period'],
                    'starts_at' => now(), 'ends_at' => now()->addYear(), 'created_by' => $actor->id,
                ]);
                $this->audit($actor, 'role.granted', $assignment, ['user_id' => $user->id, 'role' => 'pengurus', 'scope' => $club->id, 'source' => 'officer-import']);
                $user->notify(new BrudelNotice('Penugasan baru', 'Anda ditugaskan sebagai '.$row['position'].' '.$club->name.' periode '.$row['period'].'.', '/kelola/ukm/'.$club->id));
                $summary['rows'][$index]['result'] = 'Ditugaskan';
                $summary['assigned']++;
            }
            $this->audit($actor, 'officers.imported', $actor, ['assigned' => $summary['assigned'], 'created_users' => $summary['created_users']]);

            return $summary;
        });
    }

    /**
     * One row per organization with empty officer columns, so kemahasiswaan fills names and NIMs.
     *
     * @return Collection<int, array<int, string>>
     */
    public function template(): Collection
    {
        return Club::where('active', true)->ordered()->get()->map(fn (Club $club) => [$club->name, '', '', '', 'Ketua', '']);
    }

    public static function currentPeriod(): string
    {
        $now = now(config('brudel.timezone'));
        $start = $now->month >= 8 ? $now->year : $now->year - 1;

        return $start.'/'.($start + 1);
    }

    /**
     * @return array{0: list<array<string, string|int>>, 1: ?string}
     */
    private function read(string $content): array
    {
        $content = (string) preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $lines = preg_split('/\r\n|\n|\r/', trim($content));
        if (! $lines || trim($lines[0]) === '') {
            return [[], 'Berkas CSV kosong.'];
        }
        $delimiter = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : ',';
        $header = array_map(fn ($cell) => self::ALIASES[Str::snake(mb_strtolower(trim((string) $cell)))] ?? null, str_getcsv($lines[0], $delimiter, '"', ''));
        foreach (['organization', 'name', 'nim', 'program'] as $required) {
            if (! in_array($required, $header, true)) {
                return [[], 'Kolom wajib: organisasi, nama, nim, prodi (jabatan, email, periode opsional).'];
            }
        }
        $rows = [];
        foreach (array_slice($lines, 1) as $index => $line) {
            if (trim($line) === '') {
                continue;
            }
            $cells = str_getcsv($line, $delimiter, '"', '');
            $row = ['line' => $index + 2, 'organization' => '', 'name' => '', 'nim' => '', 'program' => '', 'position' => '', 'email' => '', 'period' => ''];
            foreach ($header as $position => $key) {
                if ($key !== null) {
                    $row[$key] = trim(preg_replace('/\s+/', ' ', (string) ($cells[$position] ?? '')));
                }
            }
            if ($row['name'] === '' && $row['nim'] === '') {
                continue;
            }
            $rows[] = $row;
        }

        return [$rows, null];
    }

    /**
     * @param  list<array<string, string|int>>  $rows
     * @return list<array<string, mixed>>
     */
    private function validate(array $rows): array
    {
        $clubs = Club::all();
        $departments = Department::all();
        $programs = Program::all();
        $users = User::whereIn('nim', array_column($rows, 'nim'))->with('program')->get()->keyBy('nim');
        $seen = [];

        return array_map(function (array $row) use ($clubs, $departments, $programs, $users, &$seen) {
            $messages = [];
            $club = $this->club($row['organization'], $clubs, $departments);
            if (! $club) {
                $messages[] = 'Organisasi "'.$row['organization'].'" tidak dikenal.';
            }
            if (! preg_match('/^[A-Za-z0-9.\-]{5,20}$/', $row['nim'])) {
                $messages[] = 'NIM wajib 5–20 karakter huruf/angka.';
            }
            if ($row['name'] === '' || mb_strlen($row['name']) > 120) {
                $messages[] = 'Nama wajib diisi (maksimal 120 karakter).';
            }
            $email = mb_strtolower($row['email']);
            if ($email !== '' && (! filter_var($email, FILTER_VALIDATE_EMAIL) || User::where('email', $email)->where(fn ($q) => $q->whereNull('nim')->orWhere('nim', '!=', $row['nim']))->exists())) {
                $messages[] = 'Email tidak valid atau sudah dipakai akun lain.';
            }
            $position = $row['position'] === '' ? 'Ketua' : collect(config('brudel.officer_positions'))->first(fn ($known) => mb_strtolower($known) === mb_strtolower($row['position']));
            if (! $position) {
                $messages[] = 'Jabatan "'.$row['position'].'" tidak dikenal.';
            }

            $programName = $this->programName($row['program']);
            $program = $programs->first(fn ($p) => mb_strtolower($p->name) === mb_strtolower($programName) || ($p->code && mb_strtolower($p->code) === mb_strtolower($programName)));
            $existing = $users->get($row['nim']);
            $departmentId = $existing?->program?->department_id ?? $program?->department_id ?? $this->departmentFor($programName, $departments);
            if ($programName === '' && ! $existing) {
                $messages[] = 'Prodi wajib diisi untuk mahasiswa baru.';
            }
            if ($existing && ! $existing->active) {
                $messages[] = 'Akun mahasiswa dengan NIM ini nonaktif.';
            }
            if ($club && $club->category === 'himaju' && $departmentId !== $club->department_id) {
                $messages[] = 'Ketua '.$club->name.' harus mahasiswa jurusan tersebut.';
            }
            $key = ($club?->id ?? '?').'|'.$row['nim'].'|'.$position;
            if (isset($seen[$key])) {
                $messages[] = 'Duplikat dengan baris '.$seen[$key].'.';
            }
            $seen[$key] ??= $row['line'];
            $already = $existing && $club && $position && Assignment::where('user_id', $existing->id)->where('role', 'pengurus')->where('club_id', $club->id)
                ->where('position', $position)->where('ends_at', '>', now())->exists();

            return [
                'line' => $row['line'], 'organization' => $club?->name ?? $row['organization'], 'club_id' => $club?->id, 'name' => $existing?->name ?? $row['name'],
                'nim' => $row['nim'], 'program' => $existing?->program?->name ?? ($program?->name ?? $programName), 'program_id' => $existing?->program_id ?? $program?->id,
                'new_program' => ! $existing && ! $program && $programName !== '' ? $programName : null, 'new_program_department' => $departmentId,
                'department' => $departments->firstWhere('id', $departmentId)?->name, 'position' => $position, 'email' => $email,
                'period' => $row['period'] ?: self::currentPeriod(), 'user_id' => $existing?->id, 'skip' => $already,
                'result' => $messages ? 'Gagal' : ($already ? 'Sudah menjabat' : ($existing ? 'Akun ada' : 'Akun baru')), 'messages' => $messages,
            ];
        }, $rows);
    }

    /**
     * Exact name, the name without the "UKM " prefix, an abbreviation such as BEM or MPM, or "HMJ/HIMAJU <jurusan>".
     *
     * @param  Collection<int, Club>  $clubs
     * @param  Collection<int, Department>  $departments
     */
    private function club(string $name, Collection $clubs, Collection $departments): ?Club
    {
        $key = mb_strtolower(trim($name));
        $match = $clubs->first(fn (Club $club) => in_array($key, [mb_strtolower($club->name), 'ukm '.mb_strtolower(preg_replace('/^UKM\s+/i', '', $club->name))], true)
            || mb_strtolower(preg_replace('/^UKM\s+/i', '', $club->name)) === preg_replace('/^ukm\s+/', '', $key)
            || str_ends_with(mb_strtolower($club->name), '('.$key.')'));
        if ($match || ! preg_match('/^(hmj|himaju)\s+(.+)$/', $key, $parts)) {
            return $match;
        }
        $code = self::DEPARTMENT_ALIASES[$parts[2]] ?? null;
        $department = $departments->first(fn (Department $d) => mb_strtolower($d->code) === $parts[2] || $d->code === $code
            || mb_strtolower($d->name) === $parts[2] || str_starts_with(mb_strtolower($d->name), $parts[2]));

        return $department ? $clubs->first(fn (Club $club) => $club->category === 'himaju' && $club->department_id === $department->id) : null;
    }

    /**
     * "D-lll Keperawatan" (lowercase L typed for the roman numeral) becomes "D-III Keperawatan".
     */
    private function programName(string $name): string
    {
        return (string) preg_replace_callback('/^d\s*-?\s*([il]{2,3}|[il]v)\b/i', fn ($m) => 'D-'.strtoupper(str_replace(['l', 'L'], 'I', $m[1])), trim($name));
    }

    /**
     * @param  Collection<int, Department>  $departments
     */
    private function departmentFor(string $programName, Collection $departments): ?int
    {
        $name = mb_strtolower($programName);
        foreach (self::DEPARTMENT_KEYWORDS as $keyword => $code) {
            if (str_contains($name, $keyword)) {
                return $departments->firstWhere('code', $code)?->id;
            }
        }

        return null;
    }
}
