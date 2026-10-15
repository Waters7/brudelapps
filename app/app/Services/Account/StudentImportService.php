<?php

namespace App\Services\Account;

use App\Models\Program;
use App\Models\StudentImport;
use App\Models\User;
use App\Services\DomainService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Imports official campus student data. Preview validates without writing accounts; commit imports valid rows only.
 */
class StudentImportService extends DomainService
{
    public const MAX_ROWS = 5000;

    private const ALIASES = [
        'nim' => 'nim',
        'nama' => 'name',
        'name' => 'name',
        'nama_lengkap' => 'name',
        'email' => 'email',
        'prodi' => 'program',
        'program_studi' => 'program',
        'kode_prodi' => 'program',
        'program' => 'program',
        'status' => 'status',
    ];

    public function import(User $actor, UploadedFile $file, bool $commit): StudentImport
    {
        abort_unless($actor->isStudentAffairs(), 403);
        [$rows, $headerError] = $this->read($file->getRealPath());
        $this->ensure($headerError === null, $headerError ?? '');
        $this->ensure(count($rows) <= self::MAX_ROWS, 'Maksimal '.self::MAX_ROWS.' baris per impor.');

        [$valid, $errors] = $this->validate($rows);

        return DB::transaction(function () use ($actor, $file, $commit, $rows, $valid, $errors) {
            $created = 0;
            $updated = 0;
            if ($commit) {
                foreach ($valid as $row) {
                    $user = $row['existing'];
                    if ($user) {
                        $user->forceFill(['name' => $row['name'], 'email' => $row['email'], 'program_id' => $row['program_id'], 'active' => $row['active']])->save();
                        $updated++;
                    } else {
                        User::create([
                            'name' => $row['name'],
                            'email' => $row['email'],
                            'nim' => $row['nim'],
                            'program_id' => $row['program_id'],
                            'active' => $row['active'],
                            'password' => Hash::make(Str::random(48)),
                        ]);
                        $created++;
                    }
                }
            }
            $import = StudentImport::create([
                'user_id' => $actor->id,
                'filename' => mb_substr($file->getClientOriginalName(), 0, 200),
                'mode' => $commit ? 'commit' : 'preview',
                'total_rows' => count($rows),
                'created_count' => $created,
                'updated_count' => $updated,
                'failed_count' => count($errors),
                'row_errors' => $errors,
            ]);
            $import->setAttribute('valid_count', count($valid));
            $this->audit($actor, $commit ? 'students.imported' : 'students.previewed', $import, ['created' => $created, 'updated' => $updated, 'failed' => count($errors)]);

            return $import;
        });
    }

    /**
     * @return array{0: list<array{line: int, nim: string, name: string, email: string, program: string, status: string}>, 1: ?string}
     */
    private function read(string $path): array
    {
        $content = (string) file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $lines = preg_split('/\r\n|\n|\r/', trim($content));
        if (! $lines || trim($lines[0]) === '') {
            return [[], 'Berkas CSV kosong.'];
        }
        $delimiter = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : ',';
        $header = array_map(fn ($cell) => self::ALIASES[Str::snake(mb_strtolower(trim((string) $cell)))] ?? null, str_getcsv($lines[0], $delimiter, '"', ''));
        foreach (['nim', 'name', 'email', 'program'] as $required) {
            if (! in_array($required, $header, true)) {
                return [[], 'Kolom wajib: nim, nama, email, prodi (status opsional).'];
            }
        }

        $rows = [];
        foreach (array_slice($lines, 1) as $index => $line) {
            if (trim($line) === '') {
                continue;
            }
            $cells = str_getcsv($line, $delimiter, '"', '');
            $row = ['line' => $index + 2, 'nim' => '', 'name' => '', 'email' => '', 'program' => '', 'status' => ''];
            foreach ($header as $position => $key) {
                if ($key !== null) {
                    $row[$key] = trim((string) ($cells[$position] ?? ''));
                }
            }
            $rows[] = $row;
        }

        return [$rows, null];
    }

    /**
     * @param  list<array{line: int, nim: string, name: string, email: string, program: string, status: string}>  $rows
     * @return array{0: list<array<string, mixed>>, 1: list<array{line: int, nim: string, messages: list<string>}>}
     */
    private function validate(array $rows): array
    {
        $programs = [];
        foreach (Program::all() as $program) {
            $programs[mb_strtolower($program->name)] = $program->id;
            if ($program->code) {
                $programs[mb_strtolower($program->code)] = $program->id;
            }
        }
        $byNim = User::whereIn('nim', array_column($rows, 'nim'))->get()->keyBy('nim');
        $byEmail = User::whereIn('email', array_map('mb_strtolower', array_column($rows, 'email')))->get()->keyBy('email');

        $valid = [];
        $errors = [];
        $seenNim = [];
        $seenEmail = [];
        foreach ($rows as $row) {
            $messages = [];
            $email = mb_strtolower($row['email']);
            if (! preg_match('/^[A-Za-z0-9.\-]{5,20}$/', $row['nim'])) {
                $messages[] = 'NIM wajib 5–20 karakter huruf/angka.';
            }
            if ($row['name'] === '' || mb_strlen($row['name']) > 120) {
                $messages[] = 'Nama wajib diisi (maksimal 120 karakter).';
            }
            if (! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
                $messages[] = 'Format email tidak valid.';
            }
            $programId = $programs[mb_strtolower($row['program'])] ?? null;
            if ($programId === null) {
                $messages[] = 'Prodi "'.$row['program'].'" tidak dikenal. Gunakan kode atau nama prodi terdaftar.';
            }
            $status = mb_strtolower($row['status'] ?: 'aktif');
            if (! in_array($status, ['aktif', 'nonaktif', 'active', 'inactive'], true)) {
                $messages[] = 'Status harus aktif atau nonaktif.';
            }
            if (isset($seenNim[$row['nim']])) {
                $messages[] = 'NIM duplikat dengan baris '.$seenNim[$row['nim']].'.';
            }
            if ($email !== '' && isset($seenEmail[$email])) {
                $messages[] = 'Email duplikat dengan baris '.$seenEmail[$email].'.';
            }
            $seenNim[$row['nim']] ??= $row['line'];
            $seenEmail[$email] ??= $row['line'];

            $existing = $byNim->get($row['nim']);
            $emailOwner = $byEmail->get($email);
            if ($emailOwner && (! $existing || $emailOwner->id !== $existing->id)) {
                $messages[] = 'Email sudah dipakai akun lain.';
            } elseif ($existing && $existing->email !== $email && $existing->email_verified_at !== null) {
                $messages[] = 'Email berbeda dengan akun yang sudah teraktivasi. Perubahan email dilakukan manual oleh kemahasiswaan.';
            }

            if ($messages) {
                $errors[] = ['line' => $row['line'], 'nim' => $row['nim'], 'messages' => $messages];

                continue;
            }
            $valid[] = [
                'nim' => $row['nim'],
                'name' => $row['name'],
                'email' => $email,
                'program_id' => $programId,
                'active' => in_array($status, ['aktif', 'active'], true),
                'existing' => $existing,
            ];
        }

        return [$valid, $errors];
    }
}
