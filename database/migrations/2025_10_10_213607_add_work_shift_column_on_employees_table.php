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
        Schema::dropIfExists('employee_work_shifts');

        Schema::table('employees', function (Blueprint $table) {
            $table->string('work_shift')
                ->constrained('weekly_shift_days')
                ->onDelete('cascade')
                ->nullable()
                ->after('place_issue');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('work_shift');
        });
    }
};
