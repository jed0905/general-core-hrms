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
        Schema::create('payroll_project_funds', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('allocation')->nullable();
            $table->string('employee_status');
            $table->string('employee_type');
            $table->string('status')->default('active');
            $table->foreignId('operating_unit_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_project_funds');
    }
};
