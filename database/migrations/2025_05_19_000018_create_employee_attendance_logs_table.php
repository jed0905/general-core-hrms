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
        Schema::create('employee_attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id');
            $table->dateTime('auth_date_time');
            $table->date('auth_date');
            $table->time('auth_time');
            $table->string('direction');
            $table->string('device_name');
            $table->string('device_sn');
            $table->string('person_name');
            $table->string('card_no');
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('broadcasted_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_attendance_logs');
    }
};
