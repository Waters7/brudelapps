<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\Campus\SystemSettings;
use Illuminate\Console\Command;

/**
 * Server-side recovery for Pengaturan Sistem, e.g. when a wrong Turnstile key blocks every login.
 */
class ResetSystemSettings extends Command
{
    protected $signature = 'brudel:pengaturan-sistem {--reset=* : Nama pengaturan yang dikembalikan ke .env, atau "all"}';

    protected $description = 'Tampilkan atau kembalikan Pengaturan Sistem ke nilai .env';

    public function handle(): int
    {
        $stored = Setting::where('key', 'like', SystemSettings::PREFIX.'%')->pluck('key')->map(fn ($key) => substr($key, strlen(SystemSettings::PREFIX)));
        $reset = $this->option('reset');
        if ($reset === []) {
            $this->info($stored->isEmpty() ? 'Tidak ada pengaturan yang diubah dari menu.' : 'Diatur dari menu: '.$stored->implode(', '));

            return self::SUCCESS;
        }
        $keys = in_array('all', $reset, true) ? $stored->all() : $reset;
        $unknown = array_diff($keys, array_keys(SystemSettings::FIELDS));
        if ($unknown !== []) {
            $this->error('Pengaturan tidak dikenal: '.implode(', ', $unknown));

            return self::FAILURE;
        }
        Setting::whereIn('key', array_map(fn ($key) => SystemSettings::PREFIX.$key, $keys))->delete();
        $this->info('Dikembalikan ke .env: '.(implode(', ', $keys) ?: '-'));

        return self::SUCCESS;
    }
}
