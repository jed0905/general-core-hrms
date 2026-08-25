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
        Schema::create('daily_shift_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('day_of_week');
            $table->time('time_in');
            $table->time('time_out');
            $table->time('break_start')->default('12:00:00');
            $table->time('break_end')->default('13:00:00');
            $table->boolean('is_csc_compliant')->default(true);
            $table->string('remarks')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_shift_schedules');
    }
};
