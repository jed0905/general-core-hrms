<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Applicants: people who may apply to vacancies. Not employees: no employee
 * columns are required. Data is kept to what recruitment needs (no date of
 * birth, civil status, etc.).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applicants', function (Blueprint $table) {
            $table->id();
            $table->string('applicant_number', 64)->unique();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('suffix', 50)->nullable();
            $table->string('preferred_name')->nullable();
            $table->string('email', 191)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('alternate_phone', 50)->nullable();
            // Duplicate-detection keys: lower-cased email; last 10 digits of each phone.
            $table->string('normalized_email', 191)->nullable();
            $table->string('phone_key', 20)->nullable();
            $table->string('alternate_phone_key', 20)->nullable();
            $table->text('address')->nullable();
            $table->foreignId('recruitment_source_id')->nullable()->constrained('recruitment_sources')->nullOnDelete();
            $table->string('source_details')->nullable();
            $table->boolean('privacy_consent')->default(false);
            $table->timestamp('privacy_consented_at')->nullable();
            $table->boolean('is_internal')->default(false);
            $table->foreignId('employee_id')->nullable()->unique()->constrained('employees')->nullOnDelete();
            // Set when the applicant is hired (later phase).
            $table->foreignId('converted_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->enum('status', ['active', 'archived'])->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('normalized_email', 'applicants_email_idx');
            $table->index('phone_key', 'applicants_phone_idx');
            $table->index('alternate_phone_key', 'applicants_alt_phone_idx');
            $table->index(['last_name', 'first_name'], 'applicants_name_idx');
        });

        Schema::create('applicant_educations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained('applicants')->cascadeOnDelete();
            $table->string('level')->nullable();
            $table->string('institute');
            $table->string('degree')->nullable();
            $table->string('major_specialization')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->string('notes', 1000)->nullable();
            $table->timestamps();
        });

        Schema::create('applicant_work_experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained('applicants')->cascadeOnDelete();
            $table->string('company');
            $table->string('job_title');
            $table->date('from');
            $table->date('to')->nullable(); // null = current role
            $table->string('notes', 1000)->nullable();
            $table->timestamps();
        });

        Schema::create('applicant_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained('applicants')->restrictOnDelete();
            $table->foreignId('applicant_document_type_id')->constrained('applicant_document_types')->restrictOnDelete();
            $table->string('original_name');
            $table->string('disk', 32)->default('local');
            $table->string('file_path')->unique();
            $table->string('mime_type', 191)->nullable();
            $table->unsignedBigInteger('file_size')->comment('bytes');
            $table->string('description', 1000)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            // Explicit default: on MySQL/MariaDB without explicit_defaults_for_timestamp a bare
            // NOT NULL timestamp would get ON UPDATE CURRENT_TIMESTAMP and change on every edit.
            $table->timestamp('uploaded_at')->useCurrent();
            $table->timestamps();

            $table->index(['applicant_id', 'applicant_document_type_id'], 'applicant_docs_applicant_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicant_documents');
        Schema::dropIfExists('applicant_work_experiences');
        Schema::dropIfExists('applicant_educations');
        Schema::dropIfExists('applicants');
    }
};
