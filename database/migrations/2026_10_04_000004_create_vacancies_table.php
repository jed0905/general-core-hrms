<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vacancies: actual hiring opportunities. Position data is the vacancy's own
 * copy (taken from the requisition when it originates from one), so later
 * requisition changes never rewrite a vacancy. Recruitment stages will be
 * snapshotted per vacancy in a later phase (vacancy_stages).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vacancies', function (Blueprint $table) {
            $table->id();
            $table->string('vacancy_number', 64)->unique();
            $table->foreignId('job_requisition_id')->nullable()->constrained('job_requisitions')->restrictOnDelete();
            $table->string('title');
            $table->foreignId('job_title_id')->constrained('job_titles')->restrictOnDelete();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->foreignId('employment_status_id')->nullable()->constrained('employment_statuses')->restrictOnDelete();
            $table->unsignedSmallInteger('openings');
            // Updated transactionally by hiring (later phase); never above openings.
            $table->unsignedSmallInteger('filled_count')->default(0);
            $table->text('description')->nullable();
            $table->text('responsibilities')->nullable();
            $table->text('qualifications')->nullable();
            $table->decimal('salary_min', 12, 2)->nullable();
            $table->decimal('salary_max', 12, 2)->nullable();
            $table->string('salary_currency', 3)->nullable();
            $table->date('opening_date')->nullable();
            $table->date('closing_date')->nullable();
            $table->enum('visibility', ['internal', 'external', 'both'])->default('internal');
            $table->foreignId('hiring_manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->enum('status', ['draft', 'open', 'on_hold', 'closed', 'filled', 'cancelled'])->default('draft');
            $table->string('status_reason', 1000)->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status', 'vacancies_status_idx');
            $table->index(['department_id', 'status'], 'vacancies_dept_status_idx');
            $table->index('hiring_manager_id', 'vacancies_hiring_manager_idx');
        });

        // SQLite (tests) can't add constraints after creation; the service enforces the same rules.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE vacancies ADD CONSTRAINT vacancies_filled_within_openings CHECK (filled_count <= openings)');
            DB::statement('ALTER TABLE vacancies ADD CONSTRAINT vacancies_openings_positive CHECK (openings >= 1)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vacancies');
    }
};
