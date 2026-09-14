<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('module');
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->unique(['role_id', 'permission_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('employee_no')->nullable()->after('name');
            $table->string('office')->nullable()->after('employee_no');
            $table->boolean('is_active')->default(true)->after('password');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
        });

        Schema::create('applicants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('applicant_no')->unique();
            $table->string('full_name');
            $table->date('date_of_birth');
            $table->string('sex', 20);
            $table->string('contact_number', 30);
            $table->string('email');
            $table->timestamps();

            $table->index('full_name');
            $table->index('email');
        });

        Schema::create('applicant_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('beneficiary_type', 30)->default('non_student');
            $table->string('school_name')->nullable();
            $table->string('course_or_program')->nullable();
            $table->string('year_level')->nullable();
            $table->timestamps();
        });

        Schema::create('applicant_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->string('street');
            $table->string('barangay');
            $table->string('municipality');
            $table->string('province');
            $table->boolean('is_primary')->default(true);
            $table->timestamps();

            $table->index(['barangay', 'municipality']);
        });

        Schema::create('program_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('group', 30);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('assistance_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_category_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('slug')->unique();
            $table->text('description');
            $table->text('eligibility');
            $table->string('beneficiary_type', 30)->default('both');
            $table->string('amount_type', 20)->default('fixed');
            $table->decimal('amount', 12, 2)->nullable();
            $table->decimal('amount_max', 12, 2)->nullable();
            $table->boolean('is_open')->default(true);
            $table->date('open_from')->nullable();
            $table->date('open_until')->nullable();
            $table->unsignedInteger('slot_limit')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_open', 'beneficiary_type']);
        });

        Schema::create('program_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assistance_program_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('program_eligibility_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assistance_program_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('field')->nullable();
            $table->string('operator', 30)->nullable();
            $table->string('value')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('program_form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assistance_program_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('label');
            $table->string('type', 30)->default('text');
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(true);
            $table->text('help_text')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->string('application_no')->unique();
            $table->foreignId('applicant_id')->constrained()->restrictOnDelete();
            $table->foreignId('assistance_program_id')->constrained()->restrictOnDelete();
            $table->string('status', 40)->default('draft');
            $table->unsignedTinyInteger('current_step')->default(1);
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('assigned_staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('approved_amount', 12, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['status', 'submitted_at']);
            $table->index(['applicant_id', 'status']);
            $table->index('assistance_program_id');
        });

        Schema::create('application_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_form_field_id')->nullable()->constrained()->nullOnDelete();
            $table->string('field_name');
            $table->string('field_label');
            $table->text('value')->nullable();
            $table->timestamps();

            $table->index(['application_id', 'field_name']);
        });

        Schema::create('document_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_requirement_id')->nullable()->constrained()->nullOnDelete();
            $table->string('requirement_name');
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('document_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_submission_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 40)->default('pending');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('application_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['application_id', 'created_at']);
        });

        Schema::create('application_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evaluator_id')->constrained('users')->restrictOnDelete();
            $table->boolean('eligibility_passed')->default(false);
            $table->boolean('documents_complete')->default(false);
            $table->text('assessment')->nullable();
            $table->string('recommendation', 30);
            $table->decimal('recommended_amount', 12, 2)->nullable();
            $table->text('remarks');
            $table->timestamp('evaluated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('application_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('officer_id')->constrained('users')->restrictOnDelete();
            $table->string('decision', 30);
            $table->decimal('approved_amount', 12, 2)->nullable();
            $table->text('remarks');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('release_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->restrictOnDelete();
            $table->date('release_date');
            $table->string('release_location');
            $table->string('release_method', 40);
            $table->string('status', 30)->default('scheduled');
            $table->foreignId('scheduled_by')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['release_date', 'status']);
        });

        Schema::create('assistance_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->restrictOnDelete();
            $table->foreignId('release_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by')->constrained('users')->restrictOnDelete();
            $table->string('reference_no')->unique();
            $table->string('verification_code')->unique();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('release_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assistance_release_id')->constrained()->cascadeOnDelete();
            $table->foreignId('verified_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('result', 40);
            $table->string('lookup_method', 40)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('type', 40);
            $table->text('body');
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['is_published', 'published_at']);
        });

        Schema::create('system_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->string('type', 40)->default('info');
            $table->foreignId('application_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 40);
            $table->foreignId('application_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('subject');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->text('description');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['action', 'created_at']);
            $table->index('created_at');
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('group')->default('general');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('system_notifications');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('release_verifications');
        Schema::dropIfExists('assistance_releases');
        Schema::dropIfExists('release_schedules');
        Schema::dropIfExists('application_approvals');
        Schema::dropIfExists('application_evaluations');
        Schema::dropIfExists('application_status_history');
        Schema::dropIfExists('document_verifications');
        Schema::dropIfExists('document_submissions');
        Schema::dropIfExists('application_answers');
        Schema::dropIfExists('applications');
        Schema::dropIfExists('program_form_fields');
        Schema::dropIfExists('program_eligibility_rules');
        Schema::dropIfExists('program_requirements');
        Schema::dropIfExists('assistance_programs');
        Schema::dropIfExists('program_categories');
        Schema::dropIfExists('applicant_addresses');
        Schema::dropIfExists('applicant_profiles');
        Schema::dropIfExists('applicants');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
            $table->dropColumn(['employee_no', 'office', 'is_active', 'last_login_at']);
        });

        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
