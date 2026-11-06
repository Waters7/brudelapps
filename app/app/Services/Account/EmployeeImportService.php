<?php

namespace App\Services\Account;

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Services\DomainService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SimpleXMLElement;
use ZipArchive;

/**
 * Imports dosen and tendik from the "Laporan DUK" workbook. Accounts stay inactive until kemahasiswaan direktorat
 * activates them; the NIP is both the username and the first password, replaced at first sign-in. Only name, NIP,
 * email, rank, position and placement are read: NIK, birth dates and phone numbers in the workbook are never stored.
 */
class EmployeeImportService extends DomainService
{
    /** @var Collection<int, Department> */
    private Collection $departments;

    /**
     * @return array{dosen: int, tendik: int, created: int, updated: int, linked: int, placeholder_email: int, retired: int, errors: list<string>, committed: bool}
     */
    public function import(User $actor, string $path, bool $commit): array
    {
        abort_unless($actor->isStudentAffairs(), 403);
        $this->departments = Department::all();
        $summary = ['dosen' => 0, 'tendik' => 0, 'created' => 0, 'updated' => 0, 'linked' => 0, 'placeholder_email' => 0, 'retired' => 0, 'errors' => [], 'committed' => $commit];
        $rows = $this->read($path);
        $work = function () use ($actor, $rows, $commit, &$summary) {
            $seenEmail = [];
            foreach ($rows as $row) {
                $this->row($row, $commit, $summary, $seenEmail);
            }
            if ($commit) {
                $this->audit($actor, 'employees.imported', $actor, ['dosen' => $summary['dosen'], 'tendik' => $summary['tendik']]);
            }
        };
        $commit ? DB::transaction($work) : $work();

        return $summary;
    }

    /**
     * "Asisten Ahli" is a lecturer's functional rank, even where the DUK leaves out the word Dosen.
     */
    public function kind(string $position): string
    {
        return preg_match('/\bdosen\b|^\s*asisten ahli\b/i', $position) === 1 ? 'dosen' : 'tendik';
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, mixed>  $summary
     * @param  array<string, string>  $seenEmail
     */
    private function row(array $row, bool $commit, array &$summary, array &$seenEmail): void
    {
        if (mb_strtolower($row['placement']) === 'pensiun') {
            $summary['retired']++;

            return;
        }
        $nip = preg_replace('/\D/', '', $row['nip']);
        $row['name'] = ltrim($row['name'], '-. ');
        if (strlen($nip) !== 18 || $row['name'] === '') {
            $summary['errors'][] = 'Baris dengan NIP "'.$nip.'" dilewati: NIP atau nama tidak valid.';

            return;
        }
        $kind = $this->kind($row['position']);
        $summary[$kind]++;

        $emails = collect([$row['official'], $row['personal']])->map(fn (string $email) => mb_strtolower(trim($email)))
            ->filter(fn (string $email) => filter_var($email, FILTER_VALIDATE_EMAIL) && mb_strlen($email) <= 190 && ($seenEmail[$email] ?? $nip) === $nip)->values();
        $byNip = User::where('username', $nip)->first();
        $staff = $byNip ? null : User::whereNull('nim')->whereIn('email', $emails)->whereDoesntHave('employee')->first();
        $account = $byNip ?? $staff;
        $email = $account?->email_verified_at ? $account->email : $this->availableEmail($emails, $account);
        if ($email === null) {
            $summary['placeholder_email']++;
            $email = $nip.'@'.$kind.'.brudel.invalid';
        }
        $seenEmail[$email] = $nip;
        $summary[$staff ? 'linked' : ($byNip ? 'updated' : 'created')]++;
        if (! $commit) {
            return;
        }

        $user = $this->account($account, $staff !== null, $nip, $row['name'], $email);
        Employee::updateOrCreate(['user_id' => $user->id], [
            'kind' => $kind,
            'department_id' => $this->department($row['placement'])?->id,
            'unit' => Str::limit($row['placement'], 120, '') ?: null,
            'rank' => Str::limit($row['rank'], 20, '') ?: null,
            'position' => Str::limit($row['position'], 190, '') ?: null,
            'structural_position' => Str::limit($row['structural'], 190, '') ?: null,
        ]);
    }

    /**
     * A new employee starts inactive with the NIP as password. Someone already here keeps their password and status:
     * a staff account with the same email just gains the NIP, and a repeat import only refreshes what they have not set.
     */
    private function account(?User $account, bool $isStaff, string $nip, string $name, string $email): User
    {
        if (! $account) {
            $user = new User;
            $user->forceFill(['name' => Str::limit($name, 120, ''), 'username' => $nip, 'email' => $email, 'password' => $nip, 'active' => false, 'must_change_password' => true])->save();

            return $user;
        }
        if ($isStaff) {
            $account->forceFill(['username' => $account->username ?? $nip])->save();
        } elseif (! $account->email_verified_at) {
            $account->forceFill(['name' => Str::limit($name, 120, ''), 'email' => $email])->save();
        }

        return $account;
    }

    /**
     * @param  Collection<int, string>  $emails
     */
    private function availableEmail(Collection $emails, ?User $account): ?string
    {
        return $emails->first(fn (string $email) => ! User::where('email', $email)->when($account, fn ($q) => $q->whereKeyNot($account->id))->exists());
    }

    private function department(string $placement): ?Department
    {
        $key = mb_strtolower(trim($placement));

        return $key === '' ? null : $this->departments->first(fn (Department $d) => mb_strtolower($d->name) === $key || str_starts_with(mb_strtolower($d->name), $key));
    }

    /**
     * The first sheet of the workbook, with columns found by their header text so moved columns still import.
     *
     * @return list<array{name: string, nip: string, rank: string, position: string, personal: string, official: string, structural: string, placement: string}>
     */
    private function read(string $path): array
    {
        $zip = new ZipArchive;
        $this->ensure($zip->open($path) === true, basename($path).' bukan berkas Excel (.xlsx).');
        $cells = $this->firstSheet($zip);
        $zip->close();

        $headerRow = collect($cells)->search(fn (array $row) => in_array('nip baru', array_map('mb_strtolower', $row), true));
        $this->ensure($headerRow !== false, basename($path).': kolom "NIP Baru" tidak ditemukan.');
        $find = fn (int $line, string $label) => array_search($label, array_map('mb_strtolower', $cells[$line] ?? []), true);
        $columns = [
            'name' => $find($headerRow, 'nama'), 'nip' => $find($headerRow, 'nip baru'), 'position' => $find($headerRow, 'jabatan'),
            'rank' => $find($headerRow + 1, 'golongan'), 'personal' => $find($headerRow + 1, 'e-mail pribadi'), 'official' => $find($headerRow + 1, 'e-mail kemenkes'),
            'structural' => $find($headerRow, 'jabatan struktural / penugasan'), 'placement' => $find($headerRow, 'penempatan prodi / unit'),
        ];
        $missing = array_keys(array_filter($columns, fn ($column) => $column === false));
        $this->ensure($missing === [], basename($path).': kolom tidak ditemukan: '.implode(', ', $missing).'.');

        return collect($cells)->filter(fn (array $row, int $line) => $line > $headerRow + 1 && preg_match('/\d{10,}/', $row[$columns['nip']] ?? ''))
            ->map(fn (array $row) => array_map(fn (string $column) => $row[$column] ?? '', $columns))->values()->all();
    }

    /**
     * @return array<int, array<string, string>> Rows by line number, cells by column letter.
     */
    private function firstSheet(ZipArchive $zip): array
    {
        $strings = [];
        $shared = $zip->getFromName('xl/sharedStrings.xml');
        foreach ($shared ? $this->xml($shared)->si : [] as $item) {
            $strings[] = implode('', array_map('strval', $item->xpath('.//*[local-name()="t"]')));
        }
        $workbook = $this->xml((string) $zip->getFromName('xl/workbook.xml'));
        $id = (string) $workbook->sheets->sheet[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
        $target = '';
        foreach ($this->xml((string) $zip->getFromName('xl/_rels/workbook.xml.rels'))->Relationship as $relation) {
            $target = (string) $relation['Id'] === $id ? ltrim((string) $relation['Target'], '/') : $target;
        }
        $sheet = $this->xml((string) $zip->getFromName(str_starts_with($target, 'xl/') ? $target : 'xl/'.$target));

        $rows = [];
        foreach ($sheet->sheetData->row as $row) {
            foreach ($row->c as $cell) {
                $value = match ((string) $cell['t']) {
                    's' => $strings[(int) $cell->v] ?? '',
                    'inlineStr' => (string) $cell->is->t,
                    default => (string) $cell->v,
                };
                $rows[(int) $row['r']][preg_replace('/\d+/', '', (string) $cell['r'])] = trim((string) preg_replace('/\s+/u', ' ', $value));
            }
        }

        return $rows;
    }

    private function xml(string $content): SimpleXMLElement
    {
        $document = simplexml_load_string($content, options: LIBXML_NONET);
        $this->ensure($document !== false, 'Isi berkas Excel tidak dapat dibaca.');

        return $document;
    }
}
