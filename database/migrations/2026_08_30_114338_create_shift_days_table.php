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
        Schema::create('shift_days', function (Blueprint $table) {
            $table->id();

            $table->foreignId('shift_id')
                ->constrained('shifts')
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('day_of_week');

            $table->boolean('is_working_day')->default(true);

            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            $table->decimal('break_hours', 5, 2)->default(0);
            $table->decimal('required_hours', 5, 2)->nullable();

            $table->timestamps();

            $table->unique(['shift_id', 'day_of_week']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_days');
    }
};
