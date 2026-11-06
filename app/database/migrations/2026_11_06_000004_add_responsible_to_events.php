<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every event answers to one person. Existing events start with their proposer, who already had to be a Ketua UKM
     * or kemahasiswaan to propose them.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('responsible_id')->nullable()->constrained('users');
        });

        DB::table('events')->update(['responsible_id' => DB::raw('proposed_by')]);
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('responsible_id');
        });
    }
};
