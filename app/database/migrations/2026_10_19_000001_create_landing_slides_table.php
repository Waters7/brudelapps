<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Scrolling banner of the public front page: short videos or images managed by kemahasiswaan direktorat.
     */
    public function up(): void
    {
        Schema::create('landing_slides', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->string('caption', 300)->nullable();
            $table->string('link_url', 300)->nullable();
            $table->string('link_label', 40)->nullable();
            $table->string('media_type', 10);
            $table->string('media_path');
            $table->string('media_disk', 20);
            $table->unsignedSmallInteger('position')->default(1);
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_slides');
    }
};
