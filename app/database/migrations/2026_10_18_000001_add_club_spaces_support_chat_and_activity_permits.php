<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * UKM card banners, activity permits (room borrowing or outside campus hours), the support chat to
     * kemahasiswaan direktorat, and each organization's member space: announcements, chat, and quests.
     */
    public function up(): void
    {
        // Settings have a text key; their audit rows keep the key in details instead (PostgreSQL rejected them).
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('subject_id')->nullable()->change();
        });

        Schema::table('clubs', function (Blueprint $table) {
            $table->string('banner_path')->nullable();
            $table->string('banner_disk', 20)->nullable();
        });

        Schema::table('club_activities', function (Blueprint $table) {
            $table->boolean('needs_room')->default(false);
            $table->string('room', 150)->nullable();
            // null: no permit needed; otherwise pending, approved, or rejected.
            $table->string('permit_status', 20)->nullable()->index();
            $table->string('permit_level', 20)->nullable();
            $table->string('permit_reasons', 190)->nullable();
            $table->foreignId('permit_decided_by')->nullable()->constrained('users');
            $table->timestampTz('permit_decided_at')->nullable();
            $table->text('permit_note')->nullable();
        });

        Schema::create('support_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('category', 20);
            $table->string('subject', 150);
            $table->string('status', 20)->default('open')->index();
            $table->timestampTz('last_message_at')->index();
            $table->unsignedBigInteger('last_message_id')->nullable();
            $table->unsignedBigInteger('user_read_id')->nullable();
            $table->unsignedBigInteger('admin_read_id')->nullable();
            $table->timestamps();
        });

        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_thread_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->boolean('from_admin')->default(false);
            $table->text('body');
            $table->timestampTz('created_at')->useCurrent();
        });

        Schema::create('club_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('title', 150);
            $table->text('body');
            $table->boolean('pinned')->default(false);
            $table->timestamps();
        });

        Schema::create('club_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->text('body');
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['club_id', 'id']);
        });

        Schema::create('club_quests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained();
            $table->foreignId('created_by')->constrained('users');
            $table->string('title', 150);
            $table->text('description');
            $table->unsignedInteger('points');
            $table->timestampTz('due_at')->nullable();
            $table->string('status', 20)->default('open')->index();
            $table->timestamps();
        });

        Schema::create('quest_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_quest_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->text('note');
            $table->string('status', 20)->default('submitted')->index();
            $table->unsignedInteger('points_awarded')->default(0);
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->unique(['club_quest_id', 'user_id']);
        });
    }

    public function down(): void
    {
        foreach (['quest_submissions', 'club_quests', 'club_messages', 'club_posts', 'support_messages', 'support_threads'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('club_activities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('permit_decided_by');
            $table->dropIndex(['permit_status']);
            $table->dropColumn(['needs_room', 'room', 'permit_status', 'permit_level', 'permit_reasons', 'permit_decided_at', 'permit_note']);
        });
        Schema::table('clubs', function (Blueprint $table) {
            $table->dropColumn(['banner_path', 'banner_disk']);
        });
        DB::table('audit_logs')->whereNull('subject_id')->update(['subject_id' => 0]);
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('subject_id')->nullable(false)->change();
        });
    }
};
