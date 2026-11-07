<?php

namespace App\Services\Campus;

use App\Models\Setting;
use App\Models\User;
use App\Notifications\BrudelNotice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * How full the server disk is and how much of it BRUDEL takes, with an early warning before the disk runs out.
 */
class StorageMonitor
{
    public const LEVELS = ['ok' => 'Aman', 'warning' => 'Peringatan', 'critical' => 'Kritis'];

    /**
     * The disk that holds the application and its uploads. In Docker this is the host volume mounted into the container.
     *
     * @return array{total: int, free: int, used: int, percent: float, level: string}
     */
    public function disk(): array
    {
        $total = (int) (@disk_total_space(base_path()) ?: 0);
        $free = (int) (@disk_free_space(base_path()) ?: 0);
        $used = max(0, $total - $free);
        $percent = $total > 0 ? round($used * 100 / $total, 1) : 0.0;

        return ['total' => $total, 'free' => $free, 'used' => $used, 'percent' => $percent, 'level' => $this->level($percent)];
    }

    /**
     * BRUDEL's own share: database, uploaded files, backups and logs. Folder sizes are cached for ten minutes.
     *
     * @return array<string, int>
     */
    public function application(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget('brudel.storage.application');
        }

        return Cache::remember('brudel.storage.application', 600, fn () => [
            'Database' => $this->databaseSize(),
            'Berkas unggahan' => $this->folderSize(storage_path('app'), [storage_path('app/private/'.DatabaseBackup::FOLDER)]),
            'Cadangan database' => $this->folderSize(storage_path('app/private/'.DatabaseBackup::FOLDER)),
            'Log aplikasi' => $this->folderSize(storage_path('logs')),
        ]);
    }

    /**
     * @return array{warning: int, critical: int}
     */
    public function thresholds(): array
    {
        $warning = (int) (Setting::value('storage_warning_percent') ?? 80);
        $critical = (int) (Setting::value('storage_critical_percent') ?? 90);

        return ['warning' => $warning, 'critical' => max($critical, $warning + 1)];
    }

    public function level(float $percent): string
    {
        $limits = $this->thresholds();

        return match (true) {
            $percent >= $limits['critical'] => 'critical',
            $percent >= $limits['warning'] => 'warning',
            default => 'ok',
        };
    }

    /**
     * Tells kemahasiswaan once a day while the disk stays above a threshold, and again at once when it gets worse.
     */
    public function alert(): ?string
    {
        $disk = $this->disk();
        if ($disk['level'] === 'ok') {
            Cache::forget('brudel.storage.alerted');

            return null;
        }
        $previous = Cache::get('brudel.storage.alerted');
        if ($previous === $disk['level'] || ($previous === 'critical' && $disk['level'] === 'warning')) {
            return null;
        }
        $admins = User::where('active', true)->whereHas('currentAssignments', fn ($q) => $q->where('role', 'kemahasiswaan'))->get();
        Notification::send($admins, new BrudelNotice('Penyimpanan server '.strtolower(self::LEVELS[$disk['level']]),
            'Disk terpakai '.$disk['percent'].'%, sisa '.self::human($disk['free']).'. Hapus cadangan lama atau tambah kapasitas.', '/kelola/sistem/penyimpanan'));
        Cache::put('brudel.storage.alerted', $disk['level'], now()->addDay());

        return $disk['level'];
    }

    public static function human(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = (float) $bytes;
        $unit = 0;
        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return number_format($size, $unit === 0 ? 0 : 1, ',', '.').' '.$units[$unit];
    }

    private function databaseSize(): int
    {
        $connection = DB::connection();

        return match ($connection->getDriverName()) {
            'pgsql' => (int) $connection->selectOne('select pg_database_size(current_database()) as size')->size,
            'sqlite' => is_file($connection->getDatabaseName()) ? (int) filesize($connection->getDatabaseName()) : 0,
            'mysql', 'mariadb' => (int) $connection->selectOne('select coalesce(sum(data_length + index_length), 0) as size from information_schema.tables where table_schema = database()')->size,
            default => 0,
        };
    }

    /**
     * @param  list<string>  $skip
     */
    private function folderSize(string $path, array $skip = []): int
    {
        if (! is_dir($path)) {
            return 0;
        }
        $total = 0;
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $name = $file->getPathname();
            if ($file->isFile() && ! collect($skip)->contains(fn ($folder) => str_starts_with($name, $folder))) {
                $total += $file->getSize();
            }
        }

        return $total;
    }
}
