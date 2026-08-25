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
        Schema::create('weekly_shift_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('weekly_shift_template_id')->constrained()->onDelete('cascade');
            $table->foreignId('daily_shift_schedule_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weekly_shift_days');
    }
};
