<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Account\EmployeeImportService;
use Illuminate\Console\Command;

/**
 * Server-side import of dosen and tendik from the Laporan DUK workbook.
 */
class ImportEmployees extends Command
{
    protected $signature = 'brudel:impor-pegawai
        {file : Berkas Laporan DUK (.xlsx)}
        {--commit : Simpan ke database (tanpa opsi ini hanya pratinjau)}
        {--admin= : Email kemahasiswaan direktorat yang tercatat di audit}';

    protected $description = 'Impor dosen dan tendik dari Laporan DUK; akun nonaktif sampai diaktifkan super admin, sandi pertama = NIP';

    /** @var list<string> */
    protected $aliases = ['brudel:impor-dosen'];

    public function handle(EmployeeImportService $employees): int
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

        $report = $employees->import($admin, $this->argument('file'), (bool) $this->option('commit'));
        $this->line(($report['committed'] ? 'IMPOR' : 'PRATINJAU (belum disimpan)').": {$report['dosen']} dosen dan {$report['tendik']} tendik: {$report['created']} akun baru (nonaktif), "
            ."{$report['updated']} diperbarui, {$report['linked']} ditautkan ke akun staf yang ada, {$report['placeholder_email']} tanpa email unik (email sementara).");
        $this->line("Dilewati: {$report['retired']} pensiun.");
        foreach ($report['errors'] as $error) {
            $this->warn($error);
        }

        return self::SUCCESS;
    }
}
