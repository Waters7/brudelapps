<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The seven official jurusan of Poltekkes Kemenkes Manado. */
    private const DEPARTMENTS = [
        'KEP' => 'Keperawatan',
        'KEB' => 'Kebidanan',
        'KG' => 'Kesehatan Gigi',
        'GIZ' => 'Gizi dan Dietetika',
        'KL' => 'Kesehatan Lingkungan',
        'FAR' => 'Farmasi',
        'TLM' => 'Teknologi Laboratorium Medis',
    ];

    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('code', 20)->unique();
        });
        foreach (self::DEPARTMENTS as $code => $name) {
            DB::table('departments')->insert(['code' => $code, 'name' => $name]);
        }
        $ids = DB::table('departments')->pluck('id', 'code');

        Schema::table('programs', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->constrained();
        });
        foreach (DB::table('programs')->get() as $program) {
            DB::table('programs')->where('id', $program->id)->update(['department_id' => $this->departmentFor($program->department.' '.$program->name, $ids)]);
        }
        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn('department');
        });
        $programDepartments = DB::table('programs')->pluck('department_id', 'id');

        Schema::table('assignments', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->constrained();
        });
        foreach (DB::table('assignments')->where('role', 'prodi')->get() as $assignment) {
            DB::table('assignments')->where('id', $assignment->id)->update(['role' => 'jurusan', 'department_id' => $programDepartments[$assignment->program_id] ?? null, 'program_id' => null]);
        }

        Schema::create('department_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained();
            $table->string('status', 20)->default('pending')->index();
            $table->text('reason')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users');
            $table->timestampTz('decided_at')->nullable();
            $table->timestamps();
            $table->unique(['registration_id', 'department_id']);
        });
        $grouped = DB::table('program_approvals')->orderBy('id')->get()
            ->filter(fn ($approval) => isset($programDepartments[$approval->program_id]) && $programDepartments[$approval->program_id] !== null)
            ->groupBy(fn ($approval) => $approval->registration_id.'-'.$programDepartments[$approval->program_id]);
        foreach ($grouped as $approvals) {
            $first = $approvals->first();
            $status = $approvals->contains('status', 'rejected') ? 'rejected' : ($approvals->contains('status', 'pending') ? 'pending' : $first->status);
            $decided = $approvals->firstWhere('status', $status);
            DB::table('department_approvals')->insert([
                'registration_id' => $first->registration_id,
                'department_id' => $programDepartments[$first->program_id],
                'status' => $status,
                'reason' => $decided->reason,
                'decided_by' => $decided->decided_by,
                'decided_at' => $decided->decided_at,
                'created_at' => $first->created_at,
                'updated_at' => $decided->updated_at,
            ]);
        }
        Schema::dropIfExists('program_approvals');

        Schema::table('users', function (Blueprint $table) {
            $table->string('photo_path')->nullable();
            $table->string('photo_disk', 20)->nullable();
            $table->string('google_sub', 64)->nullable()->unique();
        });

        Schema::table('competitions', function (Blueprint $table) {
            $table->unsignedTinyInteger('judge_count')->default(0);
        });

        Schema::create('competition_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->unique()->constrained();
            $table->string('role', 30);
            $table->unsignedTinyInteger('slot')->default(1);
            $table->text('secret');
            $table->timestampTz('rotated_at')->nullable();
            $table->timestamps();
            $table->unique(['competition_id', 'role', 'slot']);
        });

        Schema::create('score_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained();
            $table->foreignId('registration_id')->constrained();
            $table->foreignId('judge_id')->constrained('users');
            $table->foreignId('rubric_criterion_id')->constrained('rubric_criteria');
            $table->decimal('old_score', 5, 2)->nullable();
            $table->decimal('new_score', 5, 2);
            $table->text('note')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestampTz('created_at');
            $table->index(['competition_id', 'created_at']);
        });

        Schema::create('club_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained();
            $table->string('type', 20);
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->string('location', 190);
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->boolean('is_public')->default(true);
            $table->string('status', 20)->default('scheduled')->index();
            $table->string('attendance_code', 12)->nullable()->unique();
            $table->timestampTz('code_expires_at')->nullable();
            $table->string('opponent', 150)->nullable();
            $table->integer('score_home')->nullable();
            $table->integer('score_away')->nullable();
            $table->text('notes')->nullable();
            $table->string('minutes_path')->nullable();
            $table->string('minutes_disk', 20)->nullable();
            $table->string('minutes_name', 190)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('reported_by')->nullable()->constrained('users');
            $table->timestampTz('reported_at')->nullable();
            $table->timestamps();
            $table->index(['club_id', 'starts_at']);
        });

        Schema::create('activity_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->string('method', 10)->default('code');
            $table->foreignId('recorded_by')->nullable()->constrained('users');
            $table->timestampTz('checked_in_at');
            $table->unique(['club_activity_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_attendances');
        Schema::dropIfExists('club_activities');
        Schema::dropIfExists('score_logs');
        Schema::dropIfExists('competition_accounts');
        Schema::table('competitions', function (Blueprint $table) {
            $table->dropColumn('judge_count');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['google_sub']);
            $table->dropColumn(['photo_path', 'photo_disk', 'google_sub']);
        });

        Schema::create('program_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained();
            $table->string('status', 20)->default('pending')->index();
            $table->text('reason')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users');
            $table->timestampTz('decided_at')->nullable();
            $table->timestamps();
            $table->unique(['registration_id', 'program_id']);
        });
        Schema::dropIfExists('department_approvals');

        DB::table('assignments')->where('role', 'jurusan')->update(['role' => 'prodi']);
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
        });

        Schema::table('programs', function (Blueprint $table) {
            $table->string('department')->default('');
        });
        foreach (DB::table('programs')->get() as $program) {
            DB::table('programs')->where('id', $program->id)->update(['department' => (string) DB::table('departments')->where('id', $program->department_id)->value('name')]);
        }
        Schema::table('programs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
        });
        Schema::dropIfExists('departments');
    }

    /**
     * @param  Collection<string, int>  $ids
     */
    private function departmentFor(string $text, $ids): ?int
    {
        $text = mb_strtolower($text);
        foreach ([['gigi', 'KG'], ['bidan', 'KEB'], ['rawat', 'KEP'], ['ners', 'KEP'], ['gizi', 'GIZ'], ['lingkungan', 'KL'], ['sanitasi', 'KL'], ['farmasi', 'FAR'], ['laborator', 'TLM'], ['analis', 'TLM']] as [$needle, $code]) {
            if (str_contains($text, $needle)) {
                return $ids[$code];
            }
        }

        return null;
    }
};
