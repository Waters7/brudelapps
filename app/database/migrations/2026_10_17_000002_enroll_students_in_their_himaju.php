<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const REASON = 'Anggota otomatis HIMAJU jurusan';

    /**
     * HIMAJU membership is mandatory: enroll every active student already in the database in their jurusan's
     * HIMAJU. New and changed students are kept in sync by ClubService::syncHimaju. HIMAJU and BEM/MPM no longer
     * use open recruitment, so recruitment periods for them are closed.
     */
    public function up(): void
    {
        $himaju = DB::table('clubs')->where('category', 'himaju')->pluck('id', 'department_id');
        $students = DB::table('users')->join('programs', 'programs.id', '=', 'users.program_id')
            ->whereNotNull('users.nim')->where('users.active', true)->whereNotNull('programs.department_id')
            ->select('users.id', 'programs.department_id')->get();

        $now = now();
        foreach ($students as $student) {
            $club = $himaju[$student->department_id] ?? null;
            if (! $club || DB::table('memberships')->where('club_id', $club)->where('user_id', $student->id)->exists()) {
                continue;
            }
            DB::table('memberships')->insert([
                'club_id' => $club, 'user_id' => $student->id, 'status' => 'active', 'joined_at' => $now,
                'status_reason' => self::REASON, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        DB::table('recruitments')->whereIn('club_id', DB::table('clubs')->where('category', '!=', 'ukm')->select('id'))
            ->where('closes_at', '>', $now)->update(['closes_at' => $now, 'updated_at' => $now]);
    }

    public function down(): void
    {
        DB::table('memberships')->whereNull('club_application_id')->where('status_reason', self::REASON)
            ->whereIn('club_id', DB::table('clubs')->where('category', 'himaju')->select('id'))->delete();
    }
};
