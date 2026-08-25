<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employee_movements', function (Blueprint $table) {

            // Safely drop FK (ignore if not exists)
            try {
                $table->dropForeign(['approved_by']);
            } catch (\Exception $e) {
            }

            try {
                $table->dropForeign(['requested_by']);
            } catch (\Exception $e) {
            }

            // Now drop columns
            $table->dropColumn([
                'employee_number',
                'approval_date',
                'implementation_date',
                'status',
                'justification',
                'remarks',
                'approved_by',
                'requested_by',
                'document_number',
                'document_type',
                'is_permanent',
                'is_temporary',
                'temporary_end_date',
                'conditions',
            ]);
        });

        // Convert ENUM → STRING
        Schema::table('employee_movements', function (Blueprint $table) {
            $table->string('movement_type')->nullable()->change();
        });

        // Add new column
        Schema::table('employee_movements', function (Blueprint $table) {
            $table->boolean('is_service_record')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Convert movement_type STRING → ENUM safely
        // (fallback invalid values before change)
        DB::statement("
        UPDATE employee_movements
        SET movement_type = 'transfer'
        WHERE movement_type NOT IN (
            'promotion',
            'transfer',
            'reassignment',
            'demotion',
            'reinstatement',
            'reemployment',
            'separation',
            'retirement',
            'resignation',
            'termination',
            'suspension',
            'salary_adjustment',
            'position_change',
            'department_change',
            'designation_change'
        )
        OR movement_type IS NULL
    ");

        Schema::table('employee_movements', function (Blueprint $table) {

            // 2. Change movement_type back to ENUM
            $table->enum('movement_type', [
                'promotion',
                'transfer',
                'reassignment',
                'demotion',
                'reinstatement',
                'reemployment',
                'separation',
                'retirement',
                'resignation',
                'termination',
                'suspension',
                'salary_adjustment',
                'position_change',
                'department_change',
                'designation_change'
            ])->default('transfer')->change();

            // 3. Re-add dropped columns
            $table->string('employee_number')->nullable();

            $table->date('approval_date')->nullable();
            $table->date('implementation_date')->nullable();

            $table->enum('status', [
                'pending',
                'approved',
                'rejected',
                'implemented',
                'cancelled'
            ])->default('pending');

            $table->text('justification')->nullable();
            $table->text('remarks')->nullable();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('requested_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('document_number')->nullable();
            $table->string('document_type')->nullable();

            $table->boolean('is_permanent')->default(false);
            $table->boolean('is_temporary')->default(false);
            $table->date('temporary_end_date')->nullable();
            $table->text('conditions')->nullable();

            // 4. Remove new column
            $table->dropColumn('is_service_record');
        });
    }
};
