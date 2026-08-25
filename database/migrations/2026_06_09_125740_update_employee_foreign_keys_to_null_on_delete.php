<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $foreignKeys = [
                'department_id' => 'departments',
                'job_status_id' => 'job_statuses',
                'position_id' => 'positions',
                'salary_step_id' => 'salary_steps',
                'immediate_supervisor_id' => 'employees',
                'higher_supervisor_id' => 'employees',
                'immediate_supervisor_designation' => 'designations',
                'higher_supervisor_designation' => 'designations',
            ];

            foreach ($foreignKeys as $column => $tableName) {
                $table->dropForeign([$column]);

                $table->foreign($column)
                    ->references('id')
                    ->on($tableName)
                    ->nullOnDelete();
            }
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);

            $table->foreign('parent_id')
                ->references('id')
                ->on('departments')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $foreignKeys = [
                'department_id' => 'departments',
                'job_status_id' => 'job_statuses',
                'position_id' => 'positions',
                'salary_step_id' => 'salary_steps',
                'immediate_supervisor_id' => 'employees',
                'higher_supervisor_id' => 'employees',
                'immediate_supervisor_designation' => 'designations',
                'higher_supervisor_designation' => 'designations',
            ];

            foreach ($foreignKeys as $column => $tableName) {
                $table->dropForeign([$column]);

                $table->foreign($column)
                    ->references('id')
                    ->on($tableName);
            }
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);

            $table->foreign('parent_id')
                ->references('id')
                ->on('departments')
                ->cascadeOnDelete();
        });
    }
};
