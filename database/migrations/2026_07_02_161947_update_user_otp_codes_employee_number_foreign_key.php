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
        Schema::table('user_otp_codes', function (Blueprint $table) {
            // Drop the existing foreign key
            $table->dropForeign(['employee_number']);

            // Recreate it with ON UPDATE CASCADE
            $table->foreign('employee_number')
                ->references('employee_number')
                ->on('employees')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_otp_codes', function (Blueprint $table) {
            // Drop the updated foreign key
            $table->dropForeign(['employee_number']);

            // Restore the original foreign key
            $table->foreign('employee_number')
                ->references('employee_number')
                ->on('employees')
                ->cascadeOnDelete();
        });
    }
};
