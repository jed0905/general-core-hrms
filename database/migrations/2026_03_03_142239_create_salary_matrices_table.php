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
        Schema::create('salary_matrices', function (Blueprint $table) {
            $table->id();

            // Foreign keys
            $table->foreignId('salary_schedule_id')
                ->constrained('salary_schedules')
                ->cascadeOnDelete();

            $table->foreignId('salary_grade_id')
                ->constrained('salary_grades')
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('step_number'); // 1–8

            $table->decimal('amount', 12, 2);

            $table->timestamps();

            // Prevent duplicates
            $table->unique(
                ['salary_schedule_id', 'salary_grade_id', 'step_number'],
                'salary_matrix_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_matrices');
    }
};
