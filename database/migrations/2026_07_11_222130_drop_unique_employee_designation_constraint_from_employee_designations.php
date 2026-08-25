<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create a separate index for employee_id
        Schema::table('employee_designations', function (Blueprint $table) {
            $table->index('employee_id', 'employee_designations_employee_id_index');
        });

        // Then drop the composite unique constraint
        Schema::table('employee_designations', function (Blueprint $table) {
            $table->dropUnique('employee_designations_employee_id_designation_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_designations', function (Blueprint $table) {
            $table->unique(['employee_id', 'designation_id']);
            $table->dropIndex('employee_designations_employee_id_index');
        });
    }
};