<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SOP and guideline documents for non-academic activities, published by kemahasiswaan direktorat.
     */
    public function up(): void
    {
        Schema::create('guidelines', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->string('description', 500)->nullable();
            $table->string('file_path');
            $table->string('file_disk', 20);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guidelines');
    }
};
