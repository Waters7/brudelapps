<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->boolean('active')->default(true);
            $table->text('activities')->nullable();
        });

        Schema::table('recruitments', function (Blueprint $table) {
            $table->boolean('requires_document')->default(false);
            $table->text('selection_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
        });

        Schema::table('club_applications', function (Blueprint $table) {
            $table->string('document_path')->nullable();
            $table->string('document_disk', 20)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestampTz('reviewed_at')->nullable();
        });

        Schema::table('memberships', function (Blueprint $table) {
            $table->string('division', 80)->nullable();
            $table->timestampTz('left_at')->nullable();
            $table->text('status_reason')->nullable();
        });

        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('club_id')->nullable()->constrained();
            $table->string('field', 20)->nullable();
            $table->text('description')->nullable();
            $table->string('external_organizer')->nullable();
            $table->foreignId('proposed_by')->nullable()->constrained('users');
            $table->timestampTz('submitted_at')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users');
            $table->timestampTz('decided_at')->nullable();
        });

        Schema::table('competitions', function (Blueprint $table) {
            $table->text('fee_notes')->nullable();
            $table->text('external_info')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->string('assessment_type', 10)->default('none');
            $table->string('tie_break', 20)->default('criteria');
            $table->boolean('live_score')->default(false);
            $table->string('live_visibility', 10)->default('auth');
            $table->boolean('live_show_provisional')->default(false);
            $table->timestampTz('results_published_at')->nullable();
            $table->foreignId('results_published_by')->nullable()->constrained('users');
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->string('payment_status', 20)->default('not_required')->index();
            $table->unsignedSmallInteger('seed')->nullable();
            $table->string('performance_status', 20)->nullable();
            $table->unsignedSmallInteger('performance_order')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestampTz('reviewed_at')->nullable();
        });

        Schema::create('program_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained();
            $table->string('status', 20)->default('pending')->index();
            $table->text('reason')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users');
            $table->timestampTz('decided_at')->nullable();
            $table->timestamps();
            $table->unique(['registration_id', 'program_id']);
        });

        Schema::create('payment_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->foreignId('payment_account_id')->constrained();
            $table->unsignedBigInteger('amount');
            $table->date('transferred_on');
            $table->string('path');
            $table->string('disk', 20);
            $table->string('status', 20)->default('pending');
            $table->text('reason')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestampTz('verified_at')->nullable();
            $table->timestamps();
            $table->index(['registration_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_proofs');
        Schema::dropIfExists('program_approvals');
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropIndex(['payment_status']);
            $table->dropColumn(['payment_status', 'seed', 'performance_status', 'performance_order', 'reviewed_at']);
        });
        Schema::table('competitions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('results_published_by');
            $table->dropColumn(['fee_notes', 'external_info', 'ends_at', 'assessment_type', 'tie_break', 'live_score', 'live_visibility', 'live_show_provisional', 'results_published_at']);
        });
        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('club_id');
            $table->dropConstrainedForeignId('proposed_by');
            $table->dropConstrainedForeignId('decided_by');
            $table->dropColumn(['field', 'description', 'external_organizer', 'submitted_at', 'reason', 'decided_at']);
        });
        Schema::table('memberships', function (Blueprint $table) {
            $table->dropColumn(['division', 'left_at', 'status_reason']);
        });
        Schema::table('club_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['document_path', 'document_disk', 'reviewed_at']);
        });
        Schema::table('recruitments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['requires_document', 'selection_notes']);
        });
        Schema::table('clubs', function (Blueprint $table) {
            $table->dropColumn(['active', 'activities']);
        });
    }
};
