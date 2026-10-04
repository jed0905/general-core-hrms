<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reusable onboarding definitions (People → Onboarding Templates). Applying a
 * template copies its tasks into onboarding_tasks, so later edits here never
 * change an existing onboarding.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            // Suggestion only (shown when starting onboarding); HR always chooses the template.
            $table->foreignId('employment_status_id')->nullable()->constrained('employment_statuses')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('onboarding_template_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('onboarding_template_id')->constrained('onboarding_templates')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category', 24); // before_start | first_day | first_week | first_month | other
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('assignee_type', 24); // employee | hr | supervisor | specific_employee
            $table->foreignId('assignee_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('due_relative_to', 16)->default('start_date'); // start_date | created
            $table->smallInteger('due_offset_days')->default(0); // negative = before
            $table->boolean('is_required')->default(true);
            $table->boolean('employee_visible')->default(true);
            $table->boolean('requires_verification')->default(false);
            $table->foreignId('required_document_type_id')->nullable()->constrained('employee_document_types')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['onboarding_template_id', 'sort_order'], 'onboarding_template_tasks_order_idx');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE onboarding_template_tasks ADD CONSTRAINT onboarding_template_tasks_category_check CHECK (category IN ('before_start', 'first_day', 'first_week', 'first_month', 'other'))");
            DB::statement("ALTER TABLE onboarding_template_tasks ADD CONSTRAINT onboarding_template_tasks_assignee_check CHECK (assignee_type IN ('employee', 'hr', 'supervisor', 'specific_employee'))");
            DB::statement("ALTER TABLE onboarding_template_tasks ADD CONSTRAINT onboarding_template_tasks_relative_check CHECK (due_relative_to IN ('start_date', 'created'))");
            DB::statement('ALTER TABLE onboarding_template_tasks ADD CONSTRAINT onboarding_template_tasks_offset_check CHECK (due_offset_days BETWEEN -365 AND 365)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_template_tasks');
        Schema::dropIfExists('onboarding_templates');
    }
};
