<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('profile_frame', 40)->nullable();
        });
        Schema::create('game_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('game', 40);
            $table->unsignedInteger('seed');
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->unsignedInteger('ticks')->nullable();
            $table->index(['user_id', 'game']);
        });
        Schema::create('game_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('game', 40);
            $table->unsignedInteger('points');
            $table->string('badge');
            $table->unsignedInteger('best_ticks');
            $table->timestampTz('earned_at');
            $table->timestampTz('best_at');
            $table->unique(['user_id', 'game']);
            $table->index(['game', 'best_ticks', 'best_at']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('profile_frame');
        });
        Schema::dropIfExists('game_rewards');
        Schema::dropIfExists('game_runs');
    }
};
