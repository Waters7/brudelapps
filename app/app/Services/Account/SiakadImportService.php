<?php

namespace App\Services\Account;

use App\Models\Department;
use App\Models\Program;
use App\Models\User;
use App\Services\DomainService;
use Carbon\Carbon;
use DOMDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Imports the SIAKAD student list export ("laporan_daftarmhs.xls", an HTML table). Only NIM, name, program,
 * jurusan, email and birth date are read; NIK, addresses and family data in the export are ignored and never
 * stored. New (or never activated) students get their birth date as an 8-digit password (DDMMYYYY) that must
 * be replaced on first login.
 */
class SiakadImportService extends DomainService
{
    public const PLACEHOLDER_DOMAIN = 'siakad.brudel.invalid';

    private const MONTHS = [
        'januari' => 1, 'january' => 1, 'jan' => 1, 'februari' => 2, 'february' => 2, 'feb' => 2, 'pebruari' => 2, 'maret' => 3, 'march' => 3, 'mar' => 3,
        'april' => 4, 'apr' => 4, 'mei' => 5, 'may' => 5, 'juni' => 6, 'june' => 6, 'jun' => 6, 'juli' => 7, 'july' => 7, 'jul' => 7,
        'agustus' => 8, 'august' => 8, 'agu' => 8, 'agt' => 8, 'ags' => 8, 'aug' => 8, 'september' => 9, 'sep' => 9, 'sept' => 9,
        'oktober' => 10, 'october' => 10, 'okt' => 10, 'oct' => 10, 'november' => 11, 'nov' => 11, 'nopember' => 11, 'desember' => 12, 'december' => 12, 'des' => 12, 'dec' => 12,
    ];

    /**
     * @param  list<string>  $paths
     * @return array{files: list<array<string, mixed>>, created: int, updated: int, passwords: int, kept_active: int, no_birth_date: list<string>, placeholder_email: int, errors: list<string>, new_programs: list<string>, committed: bool}
     */
    public function import(User $actor, array $paths, bool $commit): array
    {
        abort_unless($actor->isStudentAffairs(), 403);
        $summary = ['files' => [], 'created' => 0, 'updated' => 0, 'passwords' => 0, 'kept_active' => 0, 'no_birth_date' => [], 'placeholder_email' => 0, 'errors' => [], 'new_programs' => [], 'committed' => $commit];
        $seenNim = [];
        $seenEmail = [];
        foreach ($paths as $path) {
            $sheet = $this->read($path);
            $department = $this->department($sheet['department']);
            $file = ['name' => basename($path), 'department' => $department?->name ?? ($sheet['department'] ?: 'tidak dikenali'), 'rows' => count($sheet['rows'])];
            if (! $department) {
                $summary['errors'][] = $file['name'].': jurusan "'.$sheet['department'].'" tidak dikenali; berkas dilewati.';
                $summary['files'][] = $file;

                continue;
            }
            $work = function () use ($actor, $sheet, $department, $commit, &$summary, &$seenNim, &$seenEmail) {
                foreach ($sheet['rows'] as $row) {
                    $this->row($row, $department, $commit, $summary, $seenNim, $seenEmail);
                }
                if ($commit) {
                    $this->audit($actor, 'students.siakad_imported', $actor, ['jurusan' => $department->code, 'rows' => count($sheet['rows'])]);
                }
            };
            $commit ? DB::transaction($work) : $work();
            $summary['files'][] = $file;
        }
        $summary['new_programs'] = array_values(array_unique($summary['new_programs']));

        return $summary;
    }

    /**
     * Birth date to the initial password: 8 digits DDMMYYYY.
     */
    public static function birthPassword(Carbon $date): string
    {
        return $date->format('dmY');
    }

    public function birthDate(string $value): ?Carbon
    {
        $value = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value)));
        if (preg_match('/^(\d{1,2})[ \-\/.]([a-z]+)\.?[ \-\/.](\d{4})$/u', $value, $m) && isset(self::MONTHS[$m[2]])) {
            [$day, $month, $year] = [(int) $m[1], self::MONTHS[$m[2]], (int) $m[3]];
        } elseif (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $value, $m)) {
            [$year, $month, $day] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('/^(\d{1,2})[\-\/.](\d{1,2})[\-\/.](\d{4})$/', $value, $m)) {
            [$day, $month, $year] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else {
            return null;
        }

        return checkdate($month, $day, $year) && $year >= 1950 && $year <= (int) now()->year ? Carbon::create($year, $month, $day) : null;
    }

    /**
     * "D3 - Keperawatan" becomes "D-III Keperawatan", the naming already used in BRUDEL.
     */
    public function programName(string $raw): string
    {
        $raw = trim(preg_replace('/\s+/u', ' ', $raw));
        if (! preg_match('/^(D[1-4]|prof|S[1-3])\s*-\s*(.+)$/i', $raw, $m)) {
            return $raw;
        }

        return match (strtoupper($m[1])) {
            'D1' => 'D-I ', 'D2' => 'D-II ', 'D3' => 'D-III ', 'D4' => 'D-IV ', 'PROF' => '', default => strtoupper($m[1]).' ',
        }.trim($m[2]);
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, mixed>  $summary
     * @param  array<string, true>  $seenNim
     * @param  array<string, string>  $seenEmail
     */
    private function row(array $row, Department $department, bool $commit, array &$summary, array &$seenNim, array &$seenEmail): void
    {
        $nim = ltrim(trim($row['nim'] ?? ''), "'");
        $name = trim($row['nama'] ?? '');
        if (! preg_match('/^[A-Za-z0-9.\-]{5,20}$/', $nim) || $name === '') {
            $summary['errors'][] = 'Baris dengan NIM "'.$nim.'" dilewati: NIM atau nama tidak valid.';

            return;
        }
        if (isset($seenNim[$nim])) {
            return;
        }
        $seenNim[$nim] = true;
        $existing = User::where('nim', $nim)->first();
        $email = $this->email($row, $nim, $existing, $seenEmail);
        if (str_ends_with($email, '@'.self::PLACEHOLDER_DOMAIN)) {
            $summary['placeholder_email']++;
        }
        $birth = $this->birthDate($row['tanggal lahir'] ?? '');
        if (! $birth && (! $existing || ! $existing->email_verified_at)) {
            $summary['no_birth_date'][] = $nim;
        }
        $programName = $this->programName($row['program studi'] ?? '');
        $program = Program::whereRaw('LOWER(name) = ?', [mb_strtolower($programName)])->first();
        if (! $program) {
            $summary['new_programs'][] = $programName;
        }
        $existing ? $summary['updated']++ : $summary['created']++;
        $setPassword = $birth && (! $existing || ! $existing->email_verified_at);
        $setPassword ? $summary['passwords']++ : ($existing?->email_verified_at ? $summary['kept_active']++ : null);
        if (! $commit) {
            return;
        }

        $program ??= Program::create(['name' => $programName, 'department_id' => $department->id]);
        if (! $program->department_id) {
            $program->update(['department_id' => $department->id]);
        }
        $user = $existing ?? new User(['nim' => $nim]);
        $attributes = ['name' => Str::limit($name, 120, ''), 'email' => $email, 'program_id' => $program->id, 'active' => true];
        if ($setPassword) {
            // A temporary password replaced at first login, so a lighter bcrypt cost keeps a large import fast.
            $attributes += ['password' => Hash::make(self::birthPassword($birth), ['rounds' => min(10, (int) config('hashing.bcrypt.rounds', 12))]), 'must_change_password' => true];
        } elseif (! $user->exists) {
            $attributes += ['password' => Hash::make(Str::random(40)), 'must_change_password' => false];
        }
        $user->forceFill($attributes)->save();
    }

    /**
     * Campus email first, then personal email; activated accounts keep their email. Duplicates and invalid
     * addresses fall back to a placeholder that a later import or the student can correct.
     *
     * @param  array<string, string>  $row
     * @param  array<string, string>  $seenEmail
     */
    private function email(array $row, string $nim, ?User $existing, array &$seenEmail): string
    {
        if ($existing?->email_verified_at) {
            return $existing->email;
        }
        foreach (['email kampus', 'email pribadi'] as $column) {
            $email = mb_strtolower(trim($row[$column] ?? ''));
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190 || (isset($seenEmail[$email]) && $seenEmail[$email] !== $nim)) {
                continue;
            }
            $holder = User::where('email', $email)->first();
            if ($holder && $holder->nim !== $nim) {
                continue;
            }
            $seenEmail[$email] = $nim;

            return $email;
        }

        return $existing && ! str_ends_with($existing->email, '.invalid') ? $existing->email : mb_strtolower($nim).'@'.self::PLACEHOLDER_DOMAIN;
    }

    private function department(string $name): ?Department
    {
        $key = mb_strtolower(trim($name));

        return $key === '' ? null : Department::all()->first(fn (Department $d) => mb_strtolower($d->name) === $key || str_starts_with(mb_strtolower($d->name), $key));
    }

    /**
     * @return array{department: string, rows: list<array<string, string>>}
     */
    private function read(string $path): array
    {
        $html = (string) file_get_contents($path);
        $this->ensure(str_contains(substr($html, 0, 2000), '<'), basename($path).' bukan ekspor laporan_daftarmhs (HTML).');
        libxml_use_internal_errors(true);
        $dom = new DOMDocument;
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        $department = '';
        $header = null;
        $rows = [];
        foreach ($dom->getElementsByTagName('tr') as $tr) {
            $cells = [];
            foreach ($tr->childNodes as $cell) {
                if (in_array($cell->nodeName, ['td', 'th'], true)) {
                    $cells[] = trim(preg_replace('/\s+/u', ' ', $cell->textContent));
                }
            }
            if (! $cells) {
                continue;
            }
            if ($header === null) {
                if (mb_strtolower($cells[0]) === 'jurusan' && isset($cells[2])) {
                    $department = $cells[2];
                }
                if (in_array('NIM', $cells, true)) {
                    $header = array_map(fn ($cell) => mb_strtolower($cell), $cells);
                }

                continue;
            }
            if (in_array('NIM', $cells, true)) {
                continue;
            }
            $rows[] = array_combine($header, array_pad(array_slice($cells, 0, count($header)), count($header), ''));
        }
        $this->ensure($header !== null, basename($path).': kolom NIM tidak ditemukan.');

        return ['department' => $department, 'rows' => $rows];
    }
}
