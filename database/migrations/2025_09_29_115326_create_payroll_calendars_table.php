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
        Schema::create('payroll_calendars', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number');
            $table->date('date_start');
            $table->date('date_end');
            $table->foreignId('employee_status');
            $table->string('employee_type');
            $table->foreignId('payroll_type_id');
            $table->foreignId('operating_unit_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_calendars');
    }
};
