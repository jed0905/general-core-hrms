<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recruitment → Core HR handoff (recruitment phase 5).
 *
 * application_conversions: one immutable row per converted application,
 * created in the same transaction as the employee, its number, education,
 * work experience, documents, hiring movement and optional user account. There
 * is no pending/failed row: a failed conversion rolls back entirely. Unique on
 * the application and on the accepted offer, so neither can be converted twice
 * even under concurrency.
 *
 * application_conversion_documents: provenance of each employee document copied
 * from an applicant document (the applicant's file is left untouched).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->unique('application_conversions_application_unique')->constrained('applications')->restrictOnDelete();
            $table->foreignId('applicant_id')->constrained('applicants')->restrictOnDelete();
            $table->foreignId('job_offer_id')->unique('application_conversions_offer_unique')->constrained('job_offers')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            // new_employee: created by this conversion. existing_employee: an internal candidate (or a person
            // already converted through another application) linked without creating anything in Core HR.
            $table->string('conversion_type', 24);
            $table->string('employee_number', 64); // as issued/linked at conversion time
            $table->foreignId('employee_movement_id')->nullable()->constrained('employee_movements')->restrictOnDelete();
            $table->date('start_date');
            $table->string('user_account', 16); // created | existing | none
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('education_copied')->default(0);
            $table->unsignedSmallInteger('work_experience_copied')->default(0);
            $table->unsignedSmallInteger('documents_copied')->default(0);
            // What could not be copied as-is, and why (never silently truncated).
            $table->json('notices')->nullable();
            $table->foreignId('converted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('converted_at')->useCurrent();
            $table->timestamps();

            $table->index('employee_id', 'application_conversions_employee_idx');
        });

        Schema::create('application_conversion_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_conversion_id')->constrained('application_conversions', 'id', 'conversion_documents_conversion_fk')->restrictOnDelete();
            $table->foreignId('applicant_document_id')->constrained('applicant_documents')->restrictOnDelete();
            $table->foreignId('employee_document_id')->nullable()->constrained('employee_documents')->nullOnDelete();
            $table->timestamps();

            $table->unique(['application_conversion_id', 'applicant_document_id'], 'conversion_documents_unique');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE application_conversions ADD CONSTRAINT application_conversions_type_check CHECK (conversion_type IN ('new_employee', 'existing_employee'))");
            DB::statement("ALTER TABLE application_conversions ADD CONSTRAINT application_conversions_account_check CHECK (user_account IN ('created', 'existing', 'none'))");
            DB::statement("ALTER TABLE application_conversions ADD CONSTRAINT application_conversions_movement_check CHECK (conversion_type = 'existing_employee' OR employee_movement_id IS NOT NULL)");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('application_conversion_documents');
        Schema::dropIfExists('application_conversions');
    }
};
