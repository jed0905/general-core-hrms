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
        Schema::create('pay_grade_currencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pay_grade_id')->constrained('pay_grades');
            $table->string('currency');
            $table->double('minimum_salary');
            $table->double('maximum_salary');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pay_grade_currencies');
    }
};
