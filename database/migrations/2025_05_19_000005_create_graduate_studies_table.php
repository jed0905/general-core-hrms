<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('graduate_studies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees');
            $table->string('name_of_school')->nullable();
            $table->string('degree_course')->nullable();
            $table->string('period_from')->nullable();
            $table->string('period_to')->nullable();
            $table->string('highest_level')->nullable();
            $table->string('year_graduated')->nullable();
            $table->string('academic_award')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('graduate_studies');
    }
};
