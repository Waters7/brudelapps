<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_runs', fn (Blueprint $table) => $table->unsignedSmallInteger('rules_version')->default(1));
        Schema::table('game_rewards', fn (Blueprint $table) => $table->unsignedSmallInteger('best_rules_version')->default(1));
    }

    public function down(): void
    {
        Schema::table('game_runs', fn (Blueprint $table) => $table->dropColumn('rules_version'));
        Schema::table('game_rewards', fn (Blueprint $table) => $table->dropColumn('best_rules_version'));
    }
};
