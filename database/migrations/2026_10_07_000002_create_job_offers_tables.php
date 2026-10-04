<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Job offers, their approval instances and an append-only event log.
 *
 * - Offer terms are the offer's own copy (position, employment type, location
 *   names are snapshotted) and freeze once the offer leaves draft.
 * - job_offer_approvals is a snapshot of the generic approval workflow
 *   (module "job_offer"), exactly like job_requisition_approvals.
 * - At most one offer per application is "active" (draft through accepted):
 *   enforced by a unique index on a generated column.
 * - Proposed compensation only; nothing here touches employee or payroll data.
 */
return new class extends Migration
{
    private const ACTIVE = "'draft', 'pending_approval', 'approved', 'issued', 'accepted'";

    public function up(): void
    {
        Schema::create('job_offers', function (Blueprint $table) {
            $table->id();
            $table->string('offer_number', 64)->unique();
            $table->foreignId('application_id')->constrained('applications')->restrictOnDelete();
            $table->foreignId('vacancy_id')->constrained('vacancies')->restrictOnDelete();
            $table->foreignId('applicant_id')->constrained('applicants')->restrictOnDelete();
            $table->foreignId('application_selection_id')->constrained('application_selections')->restrictOnDelete();
            $table->string('status', 24)->default('draft');

            // Terms (frozen once submitted for approval).
            $table->foreignId('job_title_id')->nullable()->constrained('job_titles')->restrictOnDelete();
            $table->string('position_title');
            $table->foreignId('department_id')->nullable()->constrained('departments')->restrictOnDelete();
            $table->string('department_name')->nullable();
            $table->foreignId('employment_status_id')->nullable()->constrained('employment_statuses')->restrictOnDelete();
            $table->string('employment_type')->nullable();
            $table->foreignId('location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->string('work_location')->nullable();
            $table->date('proposed_start_date');
            $table->date('expiry_date');
            $table->decimal('base_salary', 12, 2);
            $table->string('salary_frequency', 16);
            $table->char('currency', 3);
            $table->text('benefits')->nullable();
            $table->text('remarks')->nullable();

            // Lifecycle (nullable, written once each).
            $table->date('offer_date')->nullable(); // the date it was issued
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable(); // rejected in approval (not by the candidate)
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('response', 16)->nullable(); // candidate: accepted | declined
            $table->timestamp('responded_at')->nullable();
            $table->foreignId('response_recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('response_remarks')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->foreignId('withdrawn_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('withdrawal_reason')->nullable();
            $table->timestamps();

            $table->unsignedBigInteger('active_application_id')->nullable()
                ->storedAs('case when status in ('.self::ACTIVE.') then application_id end');
            $table->unique('active_application_id', 'job_offers_one_active_unique');
            $table->index(['vacancy_id', 'status'], 'job_offers_vacancy_status_idx');
            $table->index(['status', 'expiry_date'], 'job_offers_status_expiry_idx');
        });

        Schema::create('job_offer_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_offer_id')->constrained('job_offers')->restrictOnDelete();
            // References for traceability only; the snapshot columns below are what count.
            $table->foreignId('approval_workflow_id')->nullable()->constrained('approval_workflows')->nullOnDelete();
            $table->foreignId('approval_workflow_step_id')->nullable()->constrained('approval_workflow_steps')->nullOnDelete();
            $table->string('workflow_name');
            $table->unsignedSmallInteger('approval_order');
            $table->string('approver_type', 32);
            $table->boolean('is_required')->default(true);
            $table->foreignId('approver_id')->constrained('employees')->restrictOnDelete();
            $table->string('approver_name');
            $table->string('status', 16)->default('pending'); // pending | approved | rejected | skipped
            $table->timestamp('acted_at')->nullable();
            $table->foreignId('acted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['job_offer_id', 'approval_order'], 'job_offer_approvals_order_unique');
            $table->index(['approver_id', 'status'], 'job_offer_approvals_approver_idx');
        });

        Schema::create('job_offer_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_offer_id')->constrained('job_offers')->restrictOnDelete();
            $table->string('event', 32);
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index(['job_offer_id', 'id'], 'job_offer_events_offer_idx');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE job_offers ADD CONSTRAINT job_offers_status_check CHECK (status IN ('draft', 'pending_approval', 'approved', 'rejected', 'issued', 'accepted', 'declined', 'expired', 'withdrawn'))");
            DB::statement("ALTER TABLE job_offers ADD CONSTRAINT job_offers_frequency_check CHECK (salary_frequency IN ('hourly', 'daily', 'weekly', 'semi_monthly', 'monthly', 'annually'))");
            DB::statement('ALTER TABLE job_offers ADD CONSTRAINT job_offers_salary_check CHECK (base_salary >= 0)');
            DB::statement('ALTER TABLE job_offers ADD CONSTRAINT job_offers_dates_check CHECK (offer_date IS NULL OR expiry_date >= offer_date)');
            DB::statement("ALTER TABLE job_offers ADD CONSTRAINT job_offers_response_check CHECK ((response IS NULL AND responded_at IS NULL) OR (response IN ('accepted', 'declined') AND responded_at IS NOT NULL))");
            DB::statement("ALTER TABLE job_offers ADD CONSTRAINT job_offers_issued_check CHECK (status IN ('draft', 'pending_approval', 'approved', 'rejected') OR issued_at IS NOT NULL OR status = 'withdrawn')");
            DB::statement("ALTER TABLE job_offer_approvals ADD CONSTRAINT job_offer_approvals_status_check CHECK (status IN ('pending', 'approved', 'rejected', 'skipped'))");
        }

        $now = now();
        DB::table('number_sequences')->insertOrIgnore([
            'key' => 'job_offer_number',
            'name' => 'Job offer number',
            'prefix' => 'OFF-{YYYY}-',
            'suffix' => '',
            'padding' => 5,
            'next_number' => 1,
            'auto_generate' => true,
            'reset_period' => 'yearly',
            'current_period' => $now->format('Y'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if (! DB::table('approval_workflows')->where('module', 'job_offer')->exists()) {
            $workflowId = DB::table('approval_workflows')->insertGetId([
                'module' => 'job_offer',
                'name' => 'Standard Offer Approval',
                'description' => "Submitter's supervisor, then that supervisor's supervisor (skipped when there is none).",
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('approval_workflow_steps')->insert([
                ['approval_workflow_id' => $workflowId, 'step_order' => 1, 'approver_type' => 'immediate_supervisor', 'approver_employee_id' => null, 'is_required' => true, 'created_at' => $now, 'updated_at' => $now],
                ['approval_workflow_id' => $workflowId, 'step_order' => 2, 'approver_type' => 'higher_supervisor', 'approver_employee_id' => null, 'is_required' => false, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('job_offer_events');
        Schema::dropIfExists('job_offer_approvals');
        Schema::dropIfExists('job_offers');
        DB::table('number_sequences')->where('key', 'job_offer_number')->delete();
        // Workflow steps cascade; nothing else references an offer workflow once the offers are gone.
        DB::table('approval_workflows')->where('module', 'job_offer')->delete();
    }
};
