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
        Schema::create('spouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->string('spouse_lastname');
            $table->string('spouse_firstname');
            $table->string('spouse_middlename')->nullable();
            $table->string('spouse_suffix')->nullable();
            $table->string('occupation')->nullable();
            $table->string('employer_business_name')->nullable();
            $table->string('business_address')->nullable();
            $table->string('telephone_no')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spouses');
    }
};
