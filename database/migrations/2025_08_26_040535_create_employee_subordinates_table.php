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
        Schema::create('employee_subordinates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supervisor_id')->constrained('employees')->onDelete('cascade');
            $table->foreignId('subordinate_id')->constrained('employees')->onDelete('cascade');
            $table->timestamps();

            // Prevent duplicate supervisor-subordinate pairs
            $table->unique(['supervisor_id', 'subordinate_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_subordinates');
    }
};
