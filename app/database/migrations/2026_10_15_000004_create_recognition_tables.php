<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->nullable()->constrained();
            $table->string('title');
            $table->string('destination_event');
            $table->string('organizer');
            $table->unsignedTinyInteger('level');
            $table->string('field', 20);
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at')->nullable();
            $table->string('location');
            $table->text('reason');
            $table->string('status', 20)->default('draft')->index();
            $table->foreignId('proposed_by')->constrained('users');
            $table->timestampTz('submitted_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users');
            $table->timestampTz('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->string('letter_number', 80)->nullable();
            $table->string('letter_path')->nullable();
            $table->string('letter_disk', 20)->nullable();
            $table->foreignId('letter_uploaded_by')->nullable()->constrained('users');
            $table->timestampTz('letter_uploaded_at')->nullable();
            $table->text('outcome')->nullable();
            $table->timestampTz('outcome_recorded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('delegation_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delegation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->string('role', 20);
            $table->foreignId('registration_id')->nullable()->constrained();
            $table->timestamps();
            $table->unique(['delegation_id', 'user_id']);
        });

        Schema::create('award_results', function (Blueprint $table) {
            $table->id();
            $table->string('source', 10);
            $table->foreignId('competition_id')->nullable()->constrained();
            $table->foreignId('registration_id')->nullable()->constrained();
            $table->foreignId('delegation_id')->nullable()->constrained();
            $table->foreignId('club_id')->nullable()->constrained();
            $table->string('title');
            $table->string('organizer');
            $table->string('field', 20);
            $table->unsignedTinyInteger('level');
            $table->string('award', 80);
            $table->date('awarded_on');
            $table->boolean('is_team')->default(false);
            $table->string('team_name', 80)->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('reason')->nullable();
            $table->string('evidence_path')->nullable();
            $table->string('evidence_disk', 20)->nullable();
            $table->string('documentation_url')->nullable();
            $table->foreignId('submitted_by')->constrained('users');
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestampTz('verified_at')->nullable();
            $table->timestamps();
            $table->unique(['registration_id', 'award']);
            $table->index(['status', 'awarded_on']);
            $table->index(['level', 'field']);
        });

        Schema::create('award_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('award_result_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->string('team_role', 20)->default('individu');
            $table->timestamps();
            $table->unique(['award_result_id', 'user_id']);
        });

        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->string('number', 80)->unique();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('competition_id')->nullable()->constrained();
            $table->foreignId('registration_id')->nullable()->constrained();
            $table->foreignId('award_recipient_id')->nullable()->constrained();
            $table->string('title');
            $table->string('award_type', 80);
            $table->date('issued_on');
            $table->string('status', 20)->default('valid');
            $table->unsignedInteger('current_version')->default(1);
            $table->string('verification_token', 64)->unique();
            $table->text('revoked_reason')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users');
            $table->foreignId('issued_by')->constrained('users');
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('certificate_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certificate_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('path');
            $table->string('disk', 20);
            $table->text('reason')->nullable();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestampTz('created_at');
            $table->unique(['certificate_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_versions');
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('award_recipients');
        Schema::dropIfExists('award_results');
        Schema::dropIfExists('delegation_members');
        Schema::dropIfExists('delegations');
    }
};
