<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fixed-date national holidays for the calendar's tanggal merah. Holidays that move every year (Idul Fitri,
     * Imlek, Nyepi, Waisak, …) and cuti bersama are added by kemahasiswaan direktorat from the yearly SKB 3 Menteri.
     */
    public function up(): void
    {
        $holidays = [
            ['tahun_baru_masehi', 'Tahun Baru Masehi', '2026-01-01'],
            ['hari_buruh', 'Hari Buruh Internasional', '2026-05-01'],
            ['hari_lahir_pancasila', 'Hari Lahir Pancasila', '2026-06-01'],
            ['hari_kemerdekaan', 'Hari Kemerdekaan Republik Indonesia', '2026-08-17'],
            ['natal', 'Hari Raya Natal', '2026-12-25'],
        ];
        $now = now();
        foreach ($holidays as $index => [$code, $label, $date]) {
            if (DB::table('master_options')->where('type', 'holiday')->where('code', $code)->exists()) {
                continue;
            }
            DB::table('master_options')->insert([
                'type' => 'holiday', 'code' => $code, 'label' => $label, 'position' => $index + 1, 'active' => true,
                'meta' => json_encode(['date' => $date, 'kind' => 'nasional', 'yearly' => true]), 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('master_options')->where('type', 'holiday')
            ->whereIn('code', ['tahun_baru_masehi', 'hari_buruh', 'hari_lahir_pancasila', 'hari_kemerdekaan', 'natal'])->delete();
    }
};
