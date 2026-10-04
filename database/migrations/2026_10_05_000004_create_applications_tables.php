<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Applications (one applicant to one vacancy), the documents submitted with
 * them (links to applicant documents; files are never copied), the immutable
 * stage history, screening results and internal notes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->string('application_number', 64)->unique();
            $table->foreignId('applicant_id')->constrained('applicants')->restrictOnDelete();
            $table->foreignId('vacancy_id')->constrained('vacancies')->restrictOnDelete();
            // Changed only by ApplicationPipelineService.
            $table->foreignId('current_vacancy_stage_id')->constrained('vacancy_stages')->restrictOnDelete();
            $table->enum('status', ['active', 'shortlisted', 'rejected', 'withdrawn'])->default('active');
            $table->foreignId('recruitment_source_id')->nullable()->constrained('recruitment_sources')->nullOnDelete();
            // Explicit defaults: never ON UPDATE CURRENT_TIMESTAMP (see applicant_documents.uploaded_at).
            $table->timestamp('applied_at')->useCurrent();
            $table->timestamp('last_activity_at')->useCurrent();
            $table->timestamp('shortlisted_at')->nullable();
            $table->foreignId('shortlisted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rejection_reason_id')->nullable()->constrained('rejection_reasons')->restrictOnDelete();
            $table->text('rejection_remarks')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('withdrawal_reason', 1000)->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->foreignId('withdrawn_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // An applicant applies to a vacancy at most once (enforced by the database).
            $table->unique(['applicant_id', 'vacancy_id'], 'applications_applicant_vacancy_unique');
            $table->index(['vacancy_id', 'status'], 'applications_vacancy_status_idx');
            $table->index(['vacancy_id', 'current_vacancy_stage_id'], 'applications_vacancy_stage_idx');
        });

        Schema::create('application_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->foreignId('applicant_document_id')->constrained('applicant_documents')->restrictOnDelete();
            $table->foreignId('attached_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['application_id', 'applicant_document_id'], 'application_documents_unique');
        });

        Schema::create('application_stage_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->restrictOnDelete();
            // applied | moved | shortlisted | rejected | withdrawn
            $table->string('action', 32);
            $table->foreignId('from_vacancy_stage_id')->nullable()->constrained('vacancy_stages')->nullOnDelete();
            $table->foreignId('to_vacancy_stage_id')->nullable()->constrained('vacancy_stages')->nullOnDelete();
            $table->string('from_stage_name')->nullable();
            $table->string('to_stage_name')->nullable();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->foreignId('rejection_reason_id')->nullable()->constrained('rejection_reasons')->restrictOnDelete();
            $table->text('remarks')->nullable();
            $table->foreignId('acted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acted_at')->useCurrent();
            $table->timestamps();

            $table->index(['application_id', 'acted_at'], 'application_history_app_idx');
        });

        Schema::create('application_screenings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->unique()->constrained('applications')->cascadeOnDelete();
            $table->enum('result', ['passed', 'failed']);
            $table->text('remarks')->nullable();
            $table->date('screened_on');
            $table->foreignId('screened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rejection_reason_id')->nullable()->constrained('rejection_reasons')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('application_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('author_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('author_name');
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_notes');
        Schema::dropIfExists('application_screenings');
        Schema::dropIfExists('application_stage_histories');
        Schema::dropIfExists('application_documents');
        Schema::dropIfExists('applications');
    }
};
