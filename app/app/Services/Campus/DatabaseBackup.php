<?php

namespace App\Services\Campus;

use App\Models\User;
use App\Services\DomainService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Backs up every table of the BRUDEL database to one compressed file and restores it. The file is signed with this
 * installation's APP_KEY and only restores into the same database engine and the same migration version.
 * Sessions, cache and the job queue are left out: they are rebuilt by themselves.
 */
class DatabaseBackup extends DomainService
{
    public const FOLDER = 'backups';

    public const FORMAT = 1;

    public const SKIPPED = ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'];

    /** Automatic backups older than the newest seven are deleted. */
    public const KEEP_AUTOMATIC = 7;

    public function create(?User $actor, string $kind = 'manual'): string
    {
        $this->ensureFolder();
        $moment = now(config('brudel.timezone'));
        while (is_file($this->path($name = 'brudel-'.$moment->format('Ymd-His').'-'.$kind.'.json.gz'))) {
            $moment->addSecond();
        }
        $path = $this->path($name);
        $gz = gzopen($path, 'wb6');
        $hmac = hash_init('sha256', HASH_HMAC, $this->key());
        $write = function (array $line) use ($gz, $hmac) {
            $json = json_encode($line, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
            hash_update($hmac, $json);
            gzwrite($gz, $json);
        };

        try {
            $tables = $this->tables();
            $write(['brudel' => self::FORMAT, 'created_at' => now()->toIso8601String(), 'driver' => DB::getDriverName(), 'kind' => $kind,
                'migrations' => $this->migrations(), 'tables' => $tables]);
            $rows = 0;
            foreach ($tables as $table) {
                $query = DB::table($table);
                if (Schema::hasColumn($table, 'id')) {
                    $query->orderBy('id');
                }
                foreach ($query->cursor() as $row) {
                    $write(['t' => $table, 'r' => (array) $row]);
                    $rows++;
                }
            }
            $write(['end' => true, 'rows' => $rows]);
            gzwrite($gz, json_encode(['hmac' => hash_final($hmac)])."\n");
            gzclose($gz);
        } catch (\Throwable $exception) {
            gzclose($gz);
            @unlink($path);
            throw $exception;
        }

        if ($actor) {
            $this->audit($actor, 'backup.created', $actor, ['file' => $name, 'rows' => $rows]);
        }
        Cache::forget('brudel.storage.application');

        return $name;
    }

    /**
     * Backups on the server, newest first, with what their first line says about them.
     *
     * @return Collection<int, array{name: string, size: int, created_at: ?string, kind: string, tables: int}>
     */
    public function all(): Collection
    {
        $this->ensureFolder();

        return collect(glob($this->path('*.json.gz')) ?: [])->map(function (string $path) {
            $header = $this->header($path);

            return ['name' => basename($path), 'size' => (int) filesize($path), 'created_at' => $header['created_at'] ?? null,
                'kind' => $header['kind'] ?? 'tidak dikenal', 'tables' => count($header['tables'] ?? [])];
        })->sortByDesc(fn ($backup) => $backup['created_at'] ?? '')->values();
    }

    /**
     * Keeps an uploaded backup next to the others once its signature checks out.
     */
    public function upload(User $actor, UploadedFile $file): string
    {
        $this->ensureFolder();
        $moment = now(config('brudel.timezone'));
        while (is_file($this->path($name = 'brudel-'.$moment->format('Ymd-His').'-unggahan.json.gz'))) {
            $moment->addSecond();
        }
        $file->move(dirname($this->path($name)), $name);
        try {
            $this->verify($name);
        } catch (\Throwable $exception) {
            @unlink($this->path($name));
            throw $exception;
        }
        $this->audit($actor, 'backup.uploaded', $actor, ['file' => $name]);

        return $name;
    }

    /**
     * Replaces every backed-up table with the content of the backup. A safety backup is taken first.
     *
     * @return array{rows: int, safety: string}
     */
    public function restore(User $actor, string $name): array
    {
        $header = $this->verify($name);
        $this->ensure($header['driver'] === DB::getDriverName(), 'Cadangan dibuat dari mesin database lain ('.$header['driver'].').');
        $this->ensure($header['migrations'] === $this->migrations(), 'Versi database cadangan berbeda dengan aplikasi saat ini. Pulihkan hanya cadangan dari versi yang sama.');
        $this->ensure(array_diff($header['tables'], $this->tables()) === [], 'Cadangan memuat tabel yang tidak ada di database ini.');
        $safety = $this->create($actor, 'sebelum-pemulihan');

        @set_time_limit(0);
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $tables = $header['tables'];
        $replica = $this->suspendForeignKeys($driver);
        $rows = 0;

        try {
            $connection->transaction(function () use ($connection, $driver, $tables, $name, &$rows) {
                if ($driver === 'pgsql') {
                    $connection->statement('TRUNCATE TABLE '.collect($tables)->map(fn ($table) => '"'.$table.'"')->join(', ').' RESTART IDENTITY CASCADE');
                } else {
                    foreach (array_reverse($tables) as $table) {
                        DB::table($table)->delete();
                    }
                }
                $batch = [];
                $flush = function () use (&$batch) {
                    foreach ($batch as $table => $records) {
                        DB::table($table)->insert($records);
                    }
                    $batch = [];
                };
                foreach ($this->lines($name) as $line) {
                    if (! isset($line['t'])) {
                        continue;
                    }
                    $batch[$line['t']][] = $line['r'];
                    $rows++;
                    if (count($batch[$line['t']]) >= 200) {
                        $flush();
                    }
                }
                $flush();
                if ($driver === 'pgsql') {
                    foreach ($tables as $table) {
                        if (Schema::hasColumn($table, 'id') && $connection->selectOne("select pg_get_serial_sequence('\"{$table}\"', 'id') as seq")->seq) {
                            $connection->statement("select setval(pg_get_serial_sequence('\"{$table}\"', 'id'), coalesce(max(id), 1), max(id) is not null) from \"{$table}\"");
                        }
                    }
                }
            });
        } finally {
            $this->resumeForeignKeys($driver, $replica);
        }

        Cache::flush();
        $this->audit($actor, 'backup.restored', $actor, ['file' => $name, 'rows' => $rows, 'safety' => $safety]);

        return ['rows' => $rows, 'safety' => $safety];
    }

    public function delete(User $actor, string $name): void
    {
        $path = $this->path($this->safeName($name));
        $this->ensure(is_file($path), 'Cadangan tidak ditemukan.');
        unlink($path);
        $this->audit($actor, 'backup.deleted', $actor, ['file' => $name]);
        Cache::forget('brudel.storage.application');
    }

    /**
     * Deletes automatic backups beyond the newest few. Manual and uploaded ones stay until someone removes them.
     */
    public function prune(): int
    {
        $old = $this->all()->where('kind', 'otomatis')->slice(self::KEEP_AUTOMATIC);
        foreach ($old as $backup) {
            @unlink($this->path($backup['name']));
        }

        return $old->count();
    }

    public function path(string $name): string
    {
        return storage_path('app/private/'.self::FOLDER.'/'.$name);
    }

    public function safeName(string $name): string
    {
        $this->ensure(preg_match('/^brudel-[0-9]{8}-[0-9]{6}-[a-z-]+\.json\.gz$/', $name) === 1, 'Nama berkas cadangan tidak valid.');

        return $name;
    }

    /**
     * Checks the signature and the end marker, so a file from another installation, edited, or cut short is refused.
     *
     * @return array{brudel: int, created_at: string, driver: string, kind: string, migrations: list<string>, tables: list<string>}
     */
    public function verify(string $name): array
    {
        $path = $this->path($this->safeName($name));
        $this->ensure(is_file($path), 'Cadangan tidak ditemukan.');
        $hmac = hash_init('sha256', HASH_HMAC, $this->key());
        $gz = gzopen($path, 'rb');
        $this->ensure($gz !== false, 'Berkas cadangan tidak dapat dibaca.');
        $header = null;
        $ended = false;
        $signature = null;
        while (($raw = gzgets($gz)) !== false) {
            $line = json_decode($raw, true);
            if (! is_array($line)) {
                break;
            }
            if (array_key_exists('hmac', $line) && count($line) === 1) {
                $signature = $line['hmac'];
                break;
            }
            hash_update($hmac, $raw);
            $header ??= $line;
            $ended = $ended || isset($line['end']);
        }
        gzclose($gz);
        $this->ensure(is_array($header) && ($header['brudel'] ?? null) === self::FORMAT && $ended, 'Berkas bukan cadangan BRUDEL yang utuh.');
        $this->ensure(is_string($signature) && hash_equals(hash_final($hmac), $signature), 'Tanda tangan cadangan tidak cocok. Cadangan dibuat oleh instalasi lain atau telah diubah.');

        return $header;
    }

    /**
     * @return list<string>
     */
    public function tables(): array
    {
        return collect(Schema::getTables())
            ->filter(fn ($table) => in_array($table['schema'] ?? null, [null, 'public', 'main'], true) || DB::getDriverName() === 'mysql')
            ->pluck('name')->reject(fn ($table) => in_array($table, self::SKIPPED, true) || str_starts_with($table, 'sqlite_'))
            ->sort()->pipe(fn ($names) => $this->ordered($names->values()->all()));
    }

    /**
     * Parents before the tables that point at them, so rows insert cleanly even where foreign keys stay enforced.
     *
     * @param  list<string>  $tables
     * @return list<string>
     */
    private function ordered(array $tables): array
    {
        $parents = [];
        foreach ($tables as $table) {
            $parents[$table] = collect(Schema::getForeignKeys($table))->pluck('foreign_table')
                ->filter(fn ($parent) => $parent !== $table && in_array($parent, $tables, true))->unique()->values()->all();
        }
        $ordered = [];
        $visiting = [];
        $visit = function (string $table) use (&$visit, &$ordered, &$visiting, $parents) {
            if (in_array($table, $ordered, true) || isset($visiting[$table])) {
                return;
            }
            $visiting[$table] = true;
            foreach ($parents[$table] as $parent) {
                $visit($parent);
            }
            $ordered[] = $table;
        };
        foreach ($tables as $table) {
            $visit($table);
        }

        return $ordered;
    }

    /**
     * @return \Generator<int, array<string, mixed>>
     */
    private function lines(string $name): \Generator
    {
        $gz = gzopen($this->path($name), 'rb');
        try {
            while (($raw = gzgets($gz)) !== false) {
                $line = json_decode($raw, true);
                if (is_array($line)) {
                    yield $line;
                }
            }
        } finally {
            gzclose($gz);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function header(string $path): ?array
    {
        $gz = @gzopen($path, 'rb');
        if ($gz === false) {
            return null;
        }
        $line = gzgets($gz, 1048576);
        gzclose($gz);

        return $line ? json_decode($line, true) : null;
    }

    /**
     * @return list<string>
     */
    private function migrations(): array
    {
        return DB::table('migrations')->orderBy('migration')->pluck('migration')->all();
    }

    private function suspendForeignKeys(string $driver): bool
    {
        try {
            match ($driver) {
                'pgsql' => DB::selectOne('select rolsuper from pg_roles where rolname = current_user')?->rolsuper ? DB::statement('SET session_replication_role = replica') : throw new \RuntimeException('bukan superuser'),
                'sqlite' => DB::statement('PRAGMA foreign_keys = OFF'),
                'mysql', 'mariadb' => DB::statement('SET FOREIGN_KEY_CHECKS = 0'),
                default => null,
            };

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function resumeForeignKeys(string $driver, bool $suspended): void
    {
        if (! $suspended) {
            return;
        }
        match ($driver) {
            'pgsql' => DB::statement('SET session_replication_role = DEFAULT'),
            'sqlite' => DB::statement('PRAGMA foreign_keys = ON'),
            'mysql', 'mariadb' => DB::statement('SET FOREIGN_KEY_CHECKS = 1'),
            default => null,
        };
    }

    private function key(): string
    {
        $key = (string) config('app.key');

        return str_starts_with($key, 'base64:') ? base64_decode(Str::after($key, 'base64:')) : $key;
    }

    private function ensureFolder(): void
    {
        if (! is_dir($folder = storage_path('app/private/'.self::FOLDER))) {
            mkdir($folder, 0750, true);
        }
    }
}
