<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matchups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained();
            $table->unsignedTinyInteger('round');
            $table->unsignedSmallInteger('position');
            $table->foreignId('registration_a_id')->nullable()->constrained('registrations');
            $table->foreignId('registration_b_id')->nullable()->constrained('registrations');
            $table->unsignedSmallInteger('seed_a')->nullable();
            $table->unsignedSmallInteger('seed_b')->nullable();
            $table->boolean('is_bye')->default(false);
            $table->timestampTz('scheduled_at')->nullable();
            $table->string('location')->nullable();
            $table->string('status', 20)->default('pending');
            $table->integer('score_a')->nullable();
            $table->integer('score_b')->nullable();
            $table->string('score_detail')->nullable();
            $table->string('period_label', 40)->nullable();
            $table->foreignId('winner_registration_id')->nullable()->constrained('registrations');
            $table->foreignId('next_matchup_id')->nullable()->constrained('matchups');
            $table->char('next_slot', 1)->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->unique(['competition_id', 'round', 'position']);
        });

        Schema::create('rubric_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->decimal('weight', 5, 2);
            $table->unsignedSmallInteger('position');
            $table->timestamps();
            $table->unique(['competition_id', 'position']);
        });

        Schema::create('scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained();
            $table->foreignId('registration_id')->constrained();
            $table->foreignId('judge_id')->constrained('users');
            $table->foreignId('rubric_criterion_id')->constrained('rubric_criteria');
            $table->decimal('score', 5, 2);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['registration_id', 'judge_id', 'rubric_criterion_id']);
            $table->index(['competition_id', 'judge_id']);
        });

        Schema::create('competition_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained();
            $table->foreignId('registration_id')->constrained();
            $table->unsignedSmallInteger('rank');
            $table->decimal('total', 7, 3)->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique(['competition_id', 'registration_id']);
        });

        Schema::create('score_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained();
            $table->foreignId('matchup_id')->nullable()->constrained();
            $table->foreignId('registration_id')->nullable()->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('action', 30);
            $table->json('payload');
            $table->unsignedInteger('version')->default(0);
            $table->text('reason')->nullable();
            $table->timestampTz('created_at');
            $table->index(['competition_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('score_updates');
        Schema::dropIfExists('competition_results');
        Schema::dropIfExists('scores');
        Schema::dropIfExists('rubric_criteria');
        Schema::dropIfExists('matchups');
    }
};
