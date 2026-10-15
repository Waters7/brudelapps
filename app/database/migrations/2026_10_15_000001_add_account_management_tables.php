<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->unique();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('public_slug', 80)->nullable()->unique();
            $table->timestampTz('status_changed_at')->nullable();
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->foreignId('competition_id')->nullable()->constrained();
            $table->string('position', 60)->nullable();
            $table->string('period', 30)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->text('ended_reason')->nullable();
        });

        Schema::create('student_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('filename');
            $table->string('mode', 10);
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->json('row_errors');
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 80)->primary();
            $table->text('value')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestampTz('updated_at')->nullable();
        });

        Schema::create('payment_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('bank_name', 80);
            $table->string('account_number', 40);
            $table->string('account_holder', 120);
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->unique(['bank_name', 'account_number']);
        });

        Schema::create('status_histories', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 60);
            $table->unsignedBigInteger('subject_id');
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->text('reason')->nullable();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->timestampTz('created_at');
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_histories');
        Schema::dropIfExists('payment_accounts');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('student_imports');
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('competition_id');
            $table->dropColumn(['position', 'period', 'ended_reason']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['public_slug']);
            $table->dropColumn(['public_slug', 'status_changed_at']);
        });
        Schema::table('programs', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
