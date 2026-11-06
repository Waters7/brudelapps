<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Activity proposals and accountability reports that officers send to kemahasiswaan for validation.
     */
    public function up(): void
    {
        Schema::create('club_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained();
            $table->string('type', 20);
            $table->string('title', 190);
            $table->text('summary')->nullable();
            $table->string('target', 12);
            $table->foreignId('department_id')->nullable()->constrained();
            $table->string('status', 12)->default('submitted')->index();
            $table->unsignedInteger('version')->default(1);
            $table->string('file_path');
            $table->string('file_disk', 20);
            $table->text('review_note')->nullable();
            $table->foreignId('submitted_by')->constrained('users');
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestampTz('submitted_at');
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_submissions');
    }
};
