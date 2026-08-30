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
        Schema::create('work_schedule_days', function (Blueprint $table) {
            $table->id();

            $table->foreignId('work_schedule_id')
                ->constrained('work_schedules')
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('day_of_week');

            $table->foreignId('shift_id')
                ->nullable()
                ->constrained('shifts')
                ->nullOnDelete();

            $table->boolean('is_working_day')->default(true);

            $table->timestamps();

            $table->unique(['work_schedule_id', 'day_of_week']);
            $table->index('shift_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_schedule_days');
    }
};
