<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Account\SiakadImportService;
use Illuminate\Console\Command;

/**
 * Server-side import of SIAKAD "laporan_daftarmhs.xls" exports (thousands of students, too large for one web upload).
 */
class ImportSiakadStudents extends Command
{
    protected $signature = 'brudel:impor-siakad
        {files* : Berkas laporan_daftarmhs.xls dari SIAKAD}
        {--commit : Simpan ke database (tanpa opsi ini hanya pratinjau)}
        {--admin= : Email kemahasiswaan direktorat yang tercatat di audit}';

    protected $description = 'Impor mahasiswa dari ekspor SIAKAD; kata sandi awal = tanggal lahir DDMMYYYY dan wajib diganti saat masuk pertama';

    public function handle(SiakadImportService $siakad): int
    {
        $admin = $this->option('admin')
            ? User::where('email', $this->option('admin'))->first()
            : User::whereHas('currentAssignments', fn ($q) => $q->where('role', 'kemahasiswaan'))->orderBy('id')->first();
        if (! $admin?->isStudentAffairs()) {
            $this->error('Akun kemahasiswaan direktorat untuk audit tidak ditemukan. Gunakan --admin=email.');

            return self::FAILURE;
        }
        foreach ($this->argument('files') as $file) {
            if (! is_file($file)) {
                $this->error('Berkas tidak ditemukan: '.$file);

                return self::FAILURE;
            }
        }

        $report = $siakad->import($admin, $this->argument('files'), (bool) $this->option('commit'));
        $this->table(['Berkas', 'Jurusan', 'Baris'], array_map(fn ($file) => [$file['name'], $file['department'], $file['rows']], $report['files']));
        $this->line(($report['committed'] ? 'IMPOR' : 'PRATINJAU (belum disimpan)').": {$report['created']} akun baru, {$report['updated']} diperbarui, "
            ."{$report['passwords']} kata sandi tanggal lahir, {$report['kept_active']} akun aktif tidak diubah kata sandinya, "
            ."{$report['placeholder_email']} tanpa email valid (email sementara).");
        if ($report['new_programs']) {
            $this->line('Prodi baru: '.implode(', ', $report['new_programs']));
        }
        if ($report['no_birth_date']) {
            $this->warn(count($report['no_birth_date']).' mahasiswa tanpa tanggal lahir valid (aktivasi lewat email): '.implode(', ', array_slice($report['no_birth_date'], 0, 20)).(count($report['no_birth_date']) > 20 ? ', …' : ''));
        }
        foreach ($report['errors'] as $error) {
            $this->warn($error);
        }

        return $report['errors'] && ! $report['created'] && ! $report['updated'] ? self::FAILURE : self::SUCCESS;
    }
}
