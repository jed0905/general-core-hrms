<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance rules, roster overrides, computed days, adjustments, payroll period
 * summaries and the attendance audit log.
 *
 * - attendance_policies: company rules (grace, pairing, overtime, night window,
 *   day boundary). No statutory value lives here.
 * - employee_shift_assignments: a date-specific shift or rest day that
 *   overrides the employee's weekly Time-module schedule (rotating rosters).
 * - attendance_days: one computed row per employee per work date; recomputed
 *   from raw events + approved adjustments until locked.
 * - attendance_adjustments: corrections with original and corrected values and
 *   an approval trail. Raw events are never modified.
 * - attendance_period_summaries: quantities for a payroll period; only approved
 *   summaries feed payroll, and a summary consumed by a finalized run is locked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('legal_entity_id')->nullable()->constrained('legal_entities')->nullOnDelete();
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('late_grace_minutes')->default(0);
            $table->unsignedSmallInteger('undertime_grace_minutes')->default(0);
            $table->string('pairing_mode', 16)->default('in_out'); // in_out | first_last
            $table->unsignedSmallInteger('debounce_minutes')->default(1);
            $table->boolean('deduct_scheduled_break')->default(true);
            $table->unsignedSmallInteger('overtime_threshold_minutes')->default(0);
            $table->boolean('overtime_requires_approval')->default(true);
            $table->boolean('count_early_arrival_as_overtime')->default(false);
            $table->time('night_start')->nullable();
            $table->time('night_end')->nullable();
            $table->unsignedSmallInteger('window_before_minutes')->default(240);
            $table->time('day_start_time')->default('00:00:00');
            $table->string('timezone', 64)->nullable(); // fallback when no legal entity timezone applies
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('legal_entity_id', 'attendance_policies_entity_unique');
        });

        Schema::create('employee_shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('work_date');
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->restrictOnDelete();
            $table->boolean('is_rest_day')->default(false);
            $table->string('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'work_date'], 'employee_shift_assignments_unique');
        });

        Schema::create('attendance_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->date('work_date');
            $table->string('timezone', 64);
            $table->string('day_type', 16); // workday | rest_day | holiday | no_schedule
            $table->string('holiday_type', 32)->nullable();
            $table->foreignId('holiday_id')->nullable()->constrained('holidays')->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->dateTime('scheduled_start')->nullable(); // UTC
            $table->dateTime('scheduled_end')->nullable(); // UTC
            $table->unsignedInteger('scheduled_minutes')->default(0);
            $table->dateTime('first_in')->nullable();
            $table->dateTime('last_out')->nullable();
            $table->unsignedInteger('worked_minutes')->default(0);
            $table->unsignedInteger('regular_minutes')->default(0);
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('undertime_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->unsignedInteger('approved_overtime_minutes')->default(0);
            $table->unsignedInteger('night_minutes')->default(0);
            $table->decimal('leave_days', 6, 4)->default(0);
            $table->decimal('absence_days', 6, 4)->default(0);
            $table->string('status', 16);
            $table->json('buckets')->nullable(); // minutes by (day type, holiday type, overtime, night)
            $table->json('exceptions')->nullable();
            $table->json('event_ids')->nullable();
            $table->json('pairs')->nullable();
            $table->foreignId('attendance_policy_id')->nullable()->constrained('attendance_policies')->nullOnDelete();
            $table->timestamp('computed_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'work_date'], 'attendance_days_employee_date_unique');
            $table->index(['work_date', 'status'], 'attendance_days_date_status_idx');
        });

        Schema::create('attendance_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->date('work_date');
            $table->string('type', 24); // add_punch | void_punch | approve_overtime | mark_excused
            $table->foreignId('attendance_event_id')->nullable()->constrained('attendance_events')->restrictOnDelete();
            $table->json('original')->nullable();
            $table->json('corrected')->nullable();
            $table->string('reason');
            $table->text('remarks')->nullable();
            $table->string('status', 16)->default('pending'); // pending | approved | rejected
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_remarks')->nullable();
            $table->foreignId('created_event_id')->nullable()->constrained('attendance_events')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'work_date', 'status'], 'attendance_adjustments_day_idx');
        });

        Schema::create('attendance_period_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->restrictOnDelete();
            $table->json('quantities');
            $table->json('details')->nullable();
            $table->string('status', 16)->default('draft'); // draft | approved
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('consumed_run_id')->nullable()->constrained('payroll_runs')->nullOnDelete();
            $table->boolean('changed_after_payroll')->default(false);
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'payroll_period_id'], 'attendance_period_summaries_unique');
        });

        Schema::create('attendance_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('auditable_type', 100);
            $table->unsignedBigInteger('auditable_id');
            $table->string('event', 40);
            $table->json('changes')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_label', 100)->nullable(); // device / connector identity for machine actions
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index(['auditable_type', 'auditable_id'], 'attendance_audit_logs_subject_idx');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE attendance_policies ADD CONSTRAINT attendance_policies_pairing_check CHECK (pairing_mode IN ('in_out', 'first_last'))");
            DB::statement("ALTER TABLE attendance_adjustments ADD CONSTRAINT attendance_adjustments_status_check CHECK (status IN ('pending', 'approved', 'rejected'))");
            DB::statement("ALTER TABLE attendance_adjustments ADD CONSTRAINT attendance_adjustments_type_check CHECK (type IN ('add_punch', 'void_punch', 'approve_overtime', 'mark_excused'))");
            DB::statement("ALTER TABLE attendance_period_summaries ADD CONSTRAINT attendance_period_summaries_status_check CHECK (status IN ('draft', 'approved'))");
        }

        // One global default policy with neutral settings (no grace, typed pairing, overtime needs approval).
        $now = now();
        DB::table('attendance_policies')->insertOrIgnore([
            'name' => 'Default attendance policy',
            'legal_entity_id' => null,
            'is_default' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_audit_logs');
        Schema::dropIfExists('attendance_period_summaries');
        Schema::dropIfExists('attendance_adjustments');
        Schema::dropIfExists('attendance_days');
        Schema::dropIfExists('employee_shift_assignments');
        Schema::dropIfExists('attendance_policies');
    }
};
