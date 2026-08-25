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
        Schema::create('payroll_employee_project_funds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_number')->constrained('employees')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('project_fund_id')->constrained('payroll_project_funds')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_employee_project_funds');
    }
};
