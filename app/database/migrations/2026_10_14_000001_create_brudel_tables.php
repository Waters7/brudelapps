<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->string('department');
        });
        Schema::table('users', function (Blueprint $t) {
            $t->string('nim')->nullable()->unique();
            $t->foreignId('program_id')->nullable()->constrained();
            $t->boolean('active')->default(true);
            $t->boolean('publication_consent')->default(false);
        });
        Schema::create('clubs', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('field');
            $t->text('description');
            $t->string('mentor');
            $t->string('contact');
            $t->string('symbol');
            $t->timestamps();
        });
        Schema::create('recruitments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('club_id')->constrained();
            $t->string('name');
            $t->timestampTz('opens_at');
            $t->timestampTz('closes_at');
            $t->unsignedInteger('quota');
            $t->text('requirements');
            $t->timestamps();
        });
        Schema::create('club_applications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('recruitment_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->text('motivation');
            $t->string('status')->default('submitted')->index();
            $t->text('reason')->nullable();
            $t->timestamps();
            $t->unique(['recruitment_id', 'user_id']);
        });
        Schema::create('memberships', function (Blueprint $t) {
            $t->id();
            $t->foreignId('club_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->foreignId('club_application_id')->nullable()->constrained();
            $t->string('status')->default('active');
            $t->timestampTz('joined_at');
            $t->timestamps();
            $t->unique(['club_id', 'user_id']);
        });
        Schema::create('events', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('organizer');
            $t->string('pathway')->default('internal');
            $t->string('status')->default('approved');
            $t->timestamps();
        });
        Schema::create('competitions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_id')->constrained();
            $t->string('name');
            $t->string('field');
            $t->string('level')->default('Internal Poltekkes');
            $t->text('description');
            $t->text('requirements');
            $t->string('format')->default('individual');
            $t->unsignedSmallInteger('min_members')->default(1);
            $t->unsignedSmallInteger('max_members')->default(1);
            $t->unsignedInteger('quota');
            $t->unsignedBigInteger('fee')->default(0);
            $t->string('fee_basis')->default('team');
            $t->boolean('requires_document')->default(false);
            $t->boolean('requires_program_approval')->default(false);
            $t->timestampTz('opens_at');
            $t->timestampTz('closes_at');
            $t->timestampTz('starts_at');
            $t->string('location');
            $t->timestamps();
        });
        Schema::create('assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->string('role');
            $t->foreignId('club_id')->nullable()->constrained();
            $t->foreignId('event_id')->nullable()->constrained();
            $t->foreignId('program_id')->nullable()->constrained();
            $t->timestampTz('starts_at');
            $t->timestampTz('ends_at');
            $t->timestamps();
            $t->index(['user_id', 'role']);
        });
        Schema::create('registrations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('competition_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->string('team_name')->nullable();
            $t->text('motivation');
            $t->string('status')->default('draft')->index();
            $t->text('reason')->nullable();
            $t->unsignedBigInteger('fee_snapshot')->default(0);
            $t->string('document_path')->nullable();
            $t->string('document_disk')->nullable();
            $t->timestampTz('submitted_at')->nullable();
            $t->timestamps();
            $t->unique(['competition_id', 'user_id']);
        });
        Schema::create('participants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $t->foreignId('competition_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->string('status')->default('invited');
            $t->timestamps();
            $t->unique(['competition_id', 'user_id']);
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained();
            $t->string('action');
            $t->string('subject_type');
            $t->unsignedBigInteger('subject_id');
            $t->json('details');
            $t->timestampTz('created_at');
            $t->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'participants', 'registrations', 'assignments', 'competitions', 'events', 'memberships', 'club_applications', 'recruitments', 'clubs'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('program_id');
            $t->dropColumn(['nim', 'active', 'publication_consent']);
        });
        Schema::dropIfExists('programs');
    }
};
