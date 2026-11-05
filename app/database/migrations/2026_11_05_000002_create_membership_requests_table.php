<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The two ways into a UKM or HIMAJU outside a recruitment period: a student asks to join, or the ketua,
     * pembina, or admin jurusan invites the student, who then accepts.
     */
    public function up(): void
    {
        Schema::create('membership_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('direction', 12);
            $table->string('status', 12)->default('pending');
            $table->string('message', 500)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('decided_by')->nullable()->constrained('users');
            $table->timestampTz('decided_at')->nullable();
            $table->timestamps();
            $table->index(['club_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_requests');
    }
};
