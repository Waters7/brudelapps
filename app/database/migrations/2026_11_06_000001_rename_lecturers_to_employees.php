<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tendik join the dosen in one master list of employees, told apart by kind. Existing rows are all dosen.
     */
    public function up(): void
    {
        Schema::rename('lecturers', 'employees');
        Schema::table('employees', function (Blueprint $table) {
            $table->string('kind', 10)->default('dosen')->index();
        });
    }

    public function down(): void
    {
        DB::table('employees')->where('kind', 'tendik')->delete();
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex(['kind']);
            $table->dropColumn('kind');
        });
        Schema::rename('employees', 'lecturers');
    }
};
