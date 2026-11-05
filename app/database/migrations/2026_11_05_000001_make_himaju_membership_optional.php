<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const REASON = 'Anggota otomatis HIMAJU jurusan';

    /**
     * Being a student of a jurusan no longer makes someone a HIMAJU member. Memberships that only exist because of
     * the old automatic rule are removed; HIMAJU officers stay members, now without the automatic label.
     */
    public function up(): void
    {
        $automatic = fn () => DB::table('memberships')->where('status_reason', self::REASON)->whereNull('club_application_id')
            ->whereIn('club_id', DB::table('clubs')->where('category', 'himaju')->select('id'));
        $officer = fn ($q) => $q->select(DB::raw(1))->from('assignments')->where('assignments.role', 'pengurus')->where('assignments.ends_at', '>', now())
            ->whereColumn('assignments.user_id', 'memberships.user_id')->whereColumn('assignments.club_id', 'memberships.club_id');

        $automatic()->whereExists($officer)->update(['status_reason' => 'Pengurus organisasi', 'updated_at' => now()]);
        $automatic()->pluck('id')->chunk(500)->each(function ($ids) {
            DB::table('status_histories')->where('subject_type', 'memberships')->whereIn('subject_id', $ids)->delete();
            DB::table('memberships')->whereIn('id', $ids)->delete();
        });
    }

    /**
     * The removed rows can be derived again: every active student belonged to the HIMAJU of their jurusan.
     */
    public function down(): void
    {
        $himaju = DB::table('clubs')->where('category', 'himaju')->pluck('id', 'department_id');
        $students = DB::table('users')->join('programs', 'programs.id', '=', 'users.program_id')
            ->whereNotNull('users.nim')->where('users.active', true)->whereNotNull('programs.department_id')
            ->select('users.id', 'programs.department_id')->get();
        $now = now();
        foreach ($students as $student) {
            $club = $himaju[$student->department_id] ?? null;
            if ($club && ! DB::table('memberships')->where('club_id', $club)->where('user_id', $student->id)->exists()) {
                DB::table('memberships')->insert(['club_id' => $club, 'user_id' => $student->id, 'status' => 'active', 'joined_at' => $now,
                    'status_reason' => self::REASON, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }
};
