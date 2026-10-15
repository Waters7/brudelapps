<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Times are stored in UTC and entered/displayed in Asia/Makassar (WITA).
 */
class Wita
{
    public static function toUtc(?string $local): ?Carbon
    {
        if ($local === null || trim($local) === '') {
            return null;
        }

        return Carbon::parse($local, config('brudel.timezone'))->utc();
    }

    public static function show(?CarbonInterface $time, string $format = 'd M Y, H:i'): string
    {
        return $time ? $time->copy()->timezone(config('brudel.timezone'))->locale('id')->translatedFormat($format) : '—';
    }

    public static function input(?CarbonInterface $time): string
    {
        return $time ? $time->copy()->timezone(config('brudel.timezone'))->format('Y-m-d\TH:i') : '';
    }
}
