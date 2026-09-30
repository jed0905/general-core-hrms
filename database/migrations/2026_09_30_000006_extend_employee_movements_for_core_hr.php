<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Completes the existing (empty, unwired) employee movement tables.
 *
 * Additive only: no existing column, index or constraint is changed.
 *
 * employee_movement_types: configuration for each movement type.
 * employee_movements: the before/after employment state of each movement, so
 * a movement stays understandable after the employee changes again. IDs are
 * kept for reporting (nullOnDelete, so a removed department never deletes
 * history) and display names are frozen in `snapshot`.
 */
return new class extends Migration
{
    /**
     * Employment fields a movement can change: employees column => referenced table.
     */
    private array $stateColumns = [
        'department_id' => 'departments',
        'job_title_id' => 'job_titles',
        'employment_status_id' => 'employment_statuses',
        'location_id' => 'locations',
        'supervisor_id' => 'employees',
    ];

    public function up(): void
    {
        Schema::table('employee_movement_types', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            // Employment fields this type normally changes (drives the form).
            $table->json('affected_fields')->nullable()->after('description');
            // Record status (employees.status) this type sets, e.g. 'terminated'; null = unchanged.
            $table->string('employee_status', 20)->nullable()->after('affected_fields');
            $table->boolean('is_active')->default(true)->after('employee_status');

            $table->unique('code', 'employee_movement_types_code_unique');
            $table->index('is_active');
        });

        Schema::table('employee_movements', function (Blueprint $table) {
            foreach ($this->stateColumns as $column => $referenced) {
                foreach (['from', 'to'] as $side) {
                    $table->foreignId("{$side}_{$column}")
                        ->nullable()
                        ->constrained($referenced)
                        ->nullOnDelete();
                }
            }

            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();

            // Which employment fields this movement changes.
            $table->json('changed_fields')->nullable();
            // Display names at the time of the movement: {"from": {...}, "to": {...}}.
            $table->json('snapshot')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->index('effective_date');
            $table->index(['status', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::table('employee_movements', function (Blueprint $table) {
            $table->dropIndex(['effective_date']);
            $table->dropIndex(['status', 'effective_date']);

            $foreignKeys = ['created_by', 'cancelled_by'];
            foreach (array_keys($this->stateColumns) as $column) {
                $foreignKeys[] = "from_{$column}";
                $foreignKeys[] = "to_{$column}";
            }

            foreach ($foreignKeys as $column) {
                $table->dropForeign([$column]);
            }

            $table->dropColumn(array_merge($foreignKeys, [
                'from_status',
                'to_status',
                'changed_fields',
                'snapshot',
                'cancelled_at',
                'cancellation_reason',
            ]));
        });

        Schema::table('employee_movement_types', function (Blueprint $table) {
            $table->dropUnique('employee_movement_types_code_unique');
            $table->dropIndex(['is_active']);
            $table->dropColumn(['description', 'affected_fields', 'employee_status', 'is_active']);
        });
    }
};
