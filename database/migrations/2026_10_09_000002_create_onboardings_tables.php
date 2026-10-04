<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Employee onboarding cases, their task snapshots, an immutable event trail
 * and HR notes. Onboarding belongs to the employee (Core HR), never to an
 * applicant. At most one active (pending / in progress) case per employee is
 * enforced by a unique index on a generated column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboardings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('onboarding_template_id')->nullable()->constrained('onboarding_templates')->nullOnDelete();
            $table->string('template_name'); // snapshot
            // Provenance when started from a recruitment conversion (optional).
            $table->foreignId('application_conversion_id')->nullable()->constrained('application_conversions')->nullOnDelete();
            $table->string('status', 16)->default('pending'); // pending | in_progress | completed | cancelled
            $table->date('start_date');
            $table->date('target_completion_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->unsignedBigInteger('active_employee_id')->nullable()
                ->storedAs("case when status in ('pending', 'in_progress') then employee_id end");
            $table->unique('active_employee_id', 'onboardings_one_active_unique');
            $table->index(['status', 'start_date'], 'onboardings_status_start_idx');
            $table->index('employee_id', 'onboardings_employee_idx');
        });

        Schema::create('onboarding_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('onboarding_id')->constrained('onboardings')->restrictOnDelete();
            // Traceability only; the copied columns below are what the onboarding uses.
            $table->foreignId('onboarding_template_task_id')->nullable()->constrained('onboarding_template_tasks')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category', 24);
            $table->string('assignee_type', 24);
            $table->foreignId('assignee_employee_id')->nullable()->constrained('employees')->restrictOnDelete();
            $table->boolean('is_required')->default(true);
            $table->boolean('employee_visible')->default(true);
            $table->boolean('requires_verification')->default(false);
            $table->foreignId('required_document_type_id')->nullable()->constrained('employee_document_types')->restrictOnDelete();
            $table->date('due_date');
            $table->string('status', 16)->default('pending'); // pending | in_progress | completed | skipped | cancelled
            $table->timestamp('started_at')->nullable();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('completion_remarks')->nullable();
            $table->foreignId('employee_document_id')->nullable()->constrained('employee_documents')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('skipped_at')->nullable();
            $table->foreignId('skipped_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('skip_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // The same template task can't be applied twice to one onboarding.
            $table->unique(['onboarding_id', 'onboarding_template_task_id'], 'onboarding_tasks_template_task_unique');
            $table->index(['onboarding_id', 'status'], 'onboarding_tasks_case_status_idx');
            $table->index(['assignee_employee_id', 'status'], 'onboarding_tasks_assignee_idx');
            $table->index(['status', 'due_date'], 'onboarding_tasks_due_idx');
        });

        Schema::create('onboarding_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('onboarding_id')->constrained('onboardings')->restrictOnDelete();
            $table->foreignId('onboarding_task_id')->nullable()->constrained('onboarding_tasks')->restrictOnDelete();
            $table->string('event', 32);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index(['onboarding_id', 'id'], 'onboarding_events_case_idx');
        });

        Schema::create('onboarding_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('onboarding_id')->constrained('onboardings')->restrictOnDelete();
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('author_name');
            $table->text('body');
            $table->timestamps();
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE onboardings ADD CONSTRAINT onboardings_status_check CHECK (status IN ('pending', 'in_progress', 'completed', 'cancelled'))");
            DB::statement("ALTER TABLE onboardings ADD CONSTRAINT onboardings_completed_check CHECK (status <> 'completed' OR completed_at IS NOT NULL)");
            DB::statement("ALTER TABLE onboarding_tasks ADD CONSTRAINT onboarding_tasks_status_check CHECK (status IN ('pending', 'in_progress', 'completed', 'skipped', 'cancelled'))");
            DB::statement("ALTER TABLE onboarding_tasks ADD CONSTRAINT onboarding_tasks_assignee_check CHECK (assignee_type IN ('employee', 'hr', 'supervisor', 'specific_employee'))");
            DB::statement("ALTER TABLE onboarding_tasks ADD CONSTRAINT onboarding_tasks_completed_check CHECK (status <> 'completed' OR completed_at IS NOT NULL)");
            DB::statement("ALTER TABLE onboarding_tasks ADD CONSTRAINT onboarding_tasks_required_skip_check CHECK (NOT (is_required AND status = 'skipped'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_notes');
        Schema::dropIfExists('onboarding_events');
        Schema::dropIfExists('onboarding_tasks');
        Schema::dropIfExists('onboardings');
    }
};
