<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dosen or tendik who accompany a UKM activity or a competition. They need no BRUDEL account.
     */
    public function up(): void
    {
        Schema::create('companions', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject');
            $table->string('name', 120);
            $table->string('kind', 10);
            $table->string('identifier', 40)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companions');
    }
};
