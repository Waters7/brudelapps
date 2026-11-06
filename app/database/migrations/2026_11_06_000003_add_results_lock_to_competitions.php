<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A lock set by kemahasiswaan keeps a competition's results from everyone below admin jurusan.
     */
    public function up(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->timestampTz('results_locked_at')->nullable();
            $table->foreignId('results_locked_by')->nullable()->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('results_locked_by');
            $table->dropColumn('results_locked_at');
        });
    }
};
