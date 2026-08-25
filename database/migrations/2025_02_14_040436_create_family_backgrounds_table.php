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
        Schema::create('family_backgrounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')
                ->unique() // 👈 ensures employee_id is not repeated
                ->constrained('employees')
                ->onDelete('cascade');
            $table->string('father_lastname')->nullable();
            $table->string('father_firstname')->nullable();
            $table->string('father_middlename')->nullable();
            $table->string('father_suffix')->nullable();
            $table->string('mother_lastname')->nullable();
            $table->string('mother_firstname')->nullable();
            $table->string('mother_middlename')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('family_backgrounds');
    }
};
