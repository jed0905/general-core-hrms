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
        Schema::create('employee_attendance_log_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete();

            $table->date('date');

            $table->foreignId('att_log_event_type_id')
                ->nullable() // ✅ REQUIRED
                ->constrained('attendance_log_event_types')
                ->nullOnDelete();

            $table->enum('coverage', ['whole_day', 'am', 'pm', 'custom'])
                ->default('whole_day');

            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            $table->text('remarks')->nullable();

            $table->enum('status', ['pending', 'approved', 'rejected'])
                ->default('pending');

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->enum('source', ['employee_upload', 'hr_upload'])
                ->default('employee_upload');

            $table->timestamps();

            $table->unique(['employee_id', 'date']);
            $table->index(['employee_id', 'date']);
            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_attendance_log_events');
    }
};
