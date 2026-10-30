<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Salary payment tracking and the payroll configuration audit log.
 *
 * - employee_bank_accounts: account identifiers are encrypted at rest; only the
 *   last four characters are stored in clear for display.
 * - payroll_disbursements: one per payslip of a finalized run, with a snapshot of
 *   the destination account. No bank or payment provider is connected; exports
 *   are files (CSV, ISO 20022 pain.001) that staff upload themselves.
 * - payroll_audit_logs: who changed compensation, configuration or statutory rules.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->string('bank_name');
            $table->string('bank_identifier', 32)->nullable(); // BIC/SWIFT or a local bank code
            $table->string('account_name');
            $table->text('account_number'); // encrypted
            $table->string('account_last4', 4);
            $table->text('iban')->nullable(); // encrypted
            $table->char('currency', 3);
            $table->boolean('is_primary')->default(true);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'is_primary'], 'employee_bank_accounts_employee_idx');
        });

        Schema::create('payroll_disbursements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->restrictOnDelete();
            $table->foreignId('payroll_run_employee_id')->constrained('payroll_run_employees')->restrictOnDelete();
            $table->foreignId('employee_bank_account_id')->nullable()->constrained('employee_bank_accounts')->nullOnDelete();
            $table->decimal('amount', 19, 4);
            $table->char('currency', 3);
            $table->string('method', 24);
            $table->string('status', 16)->default('pending');
            $table->json('destination')->nullable(); // encrypted snapshot of the account used
            $table->string('reference', 100)->nullable();
            $table->timestamp('exported_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('payroll_run_employee_id', 'payroll_disbursements_payslip_unique');
            $table->index(['payroll_run_id', 'status'], 'payroll_disbursements_run_idx');
        });

        Schema::create('payroll_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('auditable_type', 100);
            $table->unsignedBigInteger('auditable_id');
            $table->string('event', 32);
            $table->json('changes')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index(['auditable_type', 'auditable_id'], 'payroll_audit_logs_subject_idx');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE payroll_disbursements ADD CONSTRAINT payroll_disbursements_status_check CHECK (status IN ('pending', 'exported', 'paid', 'failed'))");
            DB::statement("ALTER TABLE payroll_disbursements ADD CONSTRAINT payroll_disbursements_method_check CHECK (method IN ('bank_transfer', 'cash', 'check', 'other'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_audit_logs');
        Schema::dropIfExists('payroll_disbursements');
        Schema::dropIfExists('employee_bank_accounts');
    }
};
