<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An LPJ names what it accounts for: one activity of the organization, or one management period.
     */
    public function up(): void
    {
        Schema::table('club_submissions', function (Blueprint $table) {
            $table->foreignId('club_activity_id')->nullable()->constrained();
            $table->string('period', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('club_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('club_activity_id');
            $table->dropColumn('period');
        });
    }
};
