<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A front-page slide can come from a competition or a winner's award instead of an uploaded video or image.
     */
    public function up(): void
    {
        Schema::table('landing_slides', function (Blueprint $table) {
            $table->string('media_type', 10)->nullable()->change();
            $table->string('media_path')->nullable()->change();
            $table->string('media_disk', 20)->nullable()->change();
            $table->string('source', 12)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('landing_slides', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
