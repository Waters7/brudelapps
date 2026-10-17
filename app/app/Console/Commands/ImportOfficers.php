<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Account\OfficerImportService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * Server-side twin of the "Impor ketua" upload on /kelola/penugasan, for loading the initial chairs during deployment.
 */
class ImportOfficers extends Command
{
    protected $signature = 'brudel:impor-pengurus
        {file : CSV dengan kolom organisasi, nama, nim, prodi, jabatan, email}
        {--commit : Simpan ke database (tanpa opsi ini hanya pratinjau)}
        {--admin= : Email kemahasiswaan direktorat yang tercatat di audit}';

    protected $description = 'Impor ketua dan pengurus organisasi dari CSV; jalankan setelah brudel:impor-siakad agar akun dicocokkan lewat NIM';

    public function handle(OfficerImportService $officers): int
    {
        $admin = $this->option('admin')
            ? User::where('email', $this->option('admin'))->first()
            : User::whereHas('currentAssignments', fn ($q) => $q->where('role', 'kemahasiswaan'))->orderBy('id')->first();
        if (! $admin?->isStudentAffairs()) {
            $this->error('Akun kemahasiswaan direktorat untuk audit tidak ditemukan. Gunakan --admin=email.');

            return self::FAILURE;
        }
        if (! is_file($this->argument('file'))) {
            $this->error('Berkas tidak ditemukan: '.$this->argument('file'));

            return self::FAILURE;
        }

        try {
            $report = $officers->import($admin, (string) file_get_contents($this->argument('file')), (bool) $this->option('commit'));
        } catch (ValidationException $e) {
            $this->error(collect($e->errors())->flatten()->implode(' '));

            return self::FAILURE;
        }
        $this->table(['Baris', 'Organisasi', 'Nama', 'Jabatan', 'Hasil', 'Catatan'], array_map(fn ($row) => [
            $row['line'], $row['organization'], $row['name'], $row['position'] ?? '-', $row['result'], implode(' ', $row['messages']),
        ], $report['rows']));
        $this->line(($report['committed'] ? 'IMPOR' : 'PRATINJAU (belum disimpan)').": {$report['assigned']} ditugaskan, "
            ."{$report['created_users']} akun baru, {$report['errors']} baris gagal.");
        if ($report['new_programs']) {
            $this->line('Prodi baru: '.implode(', ', $report['new_programs']));
        }

        return $report['errors'] ? self::FAILURE : self::SUCCESS;
    }
}
