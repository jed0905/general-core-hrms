<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Raw attendance events from every source (device pull/push, connector,
 * simulator, manual entry, CSV import). Append-only:
 *
 * - The device's original time string and timezone are kept next to the
 *   normalized UTC instant; received_at is ingestion time, never event time.
 * - dedupe_key (unique) makes ingestion idempotent: the device event ID when the
 *   device provides one, otherwise a hash of device, user, instant and type.
 * - Events are never edited or deleted when rules change; corrections are
 *   attendance_adjustments, which reference events they void.
 * - payload holds a sanitized, encrypted subset of the device record (no
 *   biometric templates or images).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_events', function (Blueprint $table) {
            $table->id();
            $table->string('source', 16); // device_pull | device_push | connector | simulator | manual | import | adjustment
            $table->foreignId('attendance_device_id')->nullable()->constrained('attendance_devices')->restrictOnDelete();
            $table->foreignId('biometric_enrollment_id')->nullable()->constrained('biometric_enrollments')->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->restrictOnDelete();
            $table->foreignId('attendance_device_sync_run_id')->nullable()->constrained('attendance_device_sync_runs')->nullOnDelete();
            $table->string('vendor', 24)->nullable();
            $table->string('device_model', 64)->nullable();
            $table->string('device_user_id', 64)->nullable();
            $table->string('device_event_id', 64)->nullable();
            $table->string('event_time_raw', 64);
            $table->string('device_timezone', 64);
            $table->dateTime('occurred_at'); // UTC
            $table->date('local_date'); // date in the device timezone
            $table->string('event_type', 16)->default('unknown'); // check_in | check_out | break_out | break_in | overtime_in | overtime_out | unknown
            $table->string('verify_mode', 48)->nullable();
            $table->text('payload')->nullable(); // encrypted sanitized JSON
            $table->timestamp('received_at');
            $table->string('dedupe_key', 64)->unique();
            $table->string('status', 24)->default('accepted'); // accepted | unmatched | invalid
            $table->string('status_reason', 255)->nullable();
            $table->string('remarks', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'occurred_at'], 'attendance_events_employee_time_idx');
            $table->index(['attendance_device_id', 'occurred_at'], 'attendance_events_device_time_idx');
            $table->index(['status', 'attendance_device_id'], 'attendance_events_status_idx');
            $table->index(['attendance_device_id', 'device_user_id'], 'attendance_events_device_user_idx');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE attendance_events ADD CONSTRAINT attendance_events_source_check CHECK (source IN ('device_pull', 'device_push', 'connector', 'simulator', 'manual', 'import', 'adjustment'))");
            DB::statement("ALTER TABLE attendance_events ADD CONSTRAINT attendance_events_status_check CHECK (status IN ('accepted', 'unmatched', 'invalid'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_events');
    }
};
