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
        Schema::create('tardiness_notifications', function ($table) {
            $table->id();
            $table->foreignId('employee_id');
            $table->integer('month');
            $table->integer('year');
            $table->integer('last_notified_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tardiness_notifications');
    }
};
