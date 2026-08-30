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
        Schema::create('employee_employment_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete();

            $table->foreignId('job_title_id')
                ->nullable()
                ->constrained('job_titles')
                ->nullOnDelete();

            $table->foreignId('department_id')
                ->nullable()
                ->constrained('departments')
                ->nullOnDelete();

            $table->decimal('salary', 12, 2)->nullable();

            $table->string('pay_grade')->nullable();

            $table->date('effective_from');
            $table->date('effective_to')->nullable();

            $table->foreignId('movement_id')
                ->nullable()
                ->constrained('employee_movements')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['employee_id', 'effective_from']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_movement_histories');
    }
};
