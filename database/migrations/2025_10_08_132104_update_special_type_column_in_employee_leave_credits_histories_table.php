<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {

        
        // ✅ Step 1: Convert special_leaves table to InnoDB
        if (Schema::hasTable('special_leaves')) {
            DB::statement('ALTER TABLE special_leaves ENGINE = InnoDB;');
        }

        Schema::table('employee_leave_credits_histories', function (Blueprint $table) {
             // Make sure we’re not dropping the column if it doesn’t exist
            if (Schema::hasColumn('employee_leave_credits_histories', 'special_type')) {
                $table->dropColumn('special_type');
            }

            // Add the new column (no foreign key yet)
            if (!Schema::hasColumn('employee_leave_credits_histories', 'special_leave_id')) {
                $table->unsignedBigInteger('special_leave_id')->nullable()->after('leave_id');
            }
        });

        if (Schema::hasTable('special_leaves')) {
            Schema::table('employee_leave_credits_histories', function (Blueprint $table) {
                $table->foreign('special_leave_id')
                      ->references('id')
                      ->on('special_leaves')
                      ->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_leave_credits_histories', function (Blueprint $table) {
            if (Schema::hasColumn('employee_leave_credits_histories', 'special_leave_id')) {
                $table->dropForeign(['special_leave_id']);
                $table->dropColumn('special_leave_id');
            }
    
            $table->string('special_type')->nullable();
        });
    }
};
