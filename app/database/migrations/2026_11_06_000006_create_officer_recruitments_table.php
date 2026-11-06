<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * New officers of BEM, MPM and HIMAJU: students stand for a post against criteria the outgoing officers wrote.
     */
    public function up(): void
    {
        Schema::create('officer_recruitments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained();
            $table->string('title', 150);
            $table->string('period', 30);
            $table->text('description')->nullable();
            $table->json('positions');
            $table->json('criteria');
            $table->timestampTz('opens_at');
            $table->timestampTz('closes_at');
            $table->timestampTz('term_starts_at');
            $table->timestampTz('term_ends_at');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->index(['club_id', 'closes_at']);
        });

        Schema::create('officer_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('officer_recruitment_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('position', 60);
            $table->text('statement');
            $table->string('status', 12)->default('submitted')->index();
            $table->json('met_criteria')->nullable();
            $table->text('review_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['officer_recruitment_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('officer_candidates');
        Schema::dropIfExists('officer_recruitments');
    }
};
