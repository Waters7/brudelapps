<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Announcements of kemahasiswaan direktorat to many organizations at once (all, every UKM, every HIMAJU,
     * BEM & MPM, or chosen ones), apart from the announcements officers post in their own organization's space.
     */
    public function up(): void
    {
        Schema::create('broadcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('title', 150);
            $table->text('body');
            $table->string('audience', 20);
            $table->boolean('is_public')->default(false);
            $table->timestampTz('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('broadcast_club', function (Blueprint $table) {
            $table->foreignId('broadcast_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->primary(['broadcast_id', 'club_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_club');
        Schema::dropIfExists('broadcasts');
    }
};
