<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A help conversation goes to kemahasiswaan direktorat (null) or to the kemahasiswaan of one jurusan.
     */
    public function up(): void
    {
        Schema::table('support_threads', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->constrained();
        });
    }

    public function down(): void
    {
        Schema::table('support_threads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
        });
    }
};
