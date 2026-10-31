<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Biometric devices owned by the HRMS (any vendor), their sync history, the
 * optional on-premises connectors, and per-device employee identity mappings.
 *
 * - Credentials, connector secrets and card numbers are encrypted (Laravel
 *   encryption); push tokens are stored only as SHA-256 hashes.
 * - A device is unique per (vendor, serial number) when the serial is known.
 * - Device user IDs are scoped to their device: at most one ACTIVE mapping per
 *   (device, device user ID) via a generated column; history rows are kept.
 * - No biometric templates or images are stored here (they stay on the device).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_connectors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('key', 40)->unique(); // public identifier sent by the connector
            $table->text('secret'); // encrypted HMAC secret
            $table->foreignId('legal_entity_id')->nullable()->constrained('legal_entities')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->string('last_seen_ip', 45)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('attendance_devices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('vendor', 24);
            $table->string('model', 64)->nullable();
            $table->string('serial_number', 64)->nullable();
            $table->foreignId('legal_entity_id')->nullable()->constrained('legal_entities')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('timezone', 64);
            $table->string('connection_method', 24);
            $table->string('host')->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->boolean('use_tls')->default(false);
            $table->boolean('verify_tls')->default(true);
            $table->text('username')->nullable(); // encrypted
            $table->text('password')->nullable(); // encrypted
            $table->string('push_token_hash', 64)->nullable()->unique();
            $table->foreignId('attendance_connector_id')->nullable()->constrained('attendance_connectors')->nullOnDelete();
            $table->unsignedSmallInteger('page_size')->default(30);
            $table->json('sync_cursor')->nullable();
            $table->json('capabilities')->nullable();
            $table->json('settings')->nullable(); // adapter options, e.g. Hikvision event minor codes
            $table->json('simulation')->nullable(); // simulator scenario (simulator devices only)
            $table->string('firmware_version', 128)->nullable();
            $table->string('status', 16)->default('unknown'); // online | offline | error | unknown
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->integer('clock_drift_seconds')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['vendor', 'serial_number'], 'attendance_devices_vendor_serial_unique');
            $table->index(['is_active', 'connection_method'], 'attendance_devices_active_idx');
        });

        Schema::create('attendance_device_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_device_id')->nullable()->constrained('attendance_devices')->nullOnDelete();
            $table->foreignId('attendance_connector_id')->nullable()->constrained('attendance_connectors')->nullOnDelete();
            $table->string('trigger', 16); // manual | scheduled | push | connector | import | test
            $table->string('status', 16)->default('running'); // running | succeeded | partial | failed
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('fetched')->default(0);
            $table->unsignedInteger('inserted')->default(0);
            $table->unsignedInteger('duplicates')->default(0);
            $table->unsignedInteger('unmatched')->default(0);
            $table->unsignedInteger('invalid')->default(0);
            $table->unsignedSmallInteger('attempts')->default(1);
            $table->string('error_code', 48)->nullable();
            $table->string('error_message', 500)->nullable();
            $table->json('window')->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['attendance_device_id', 'id'], 'attendance_sync_runs_device_idx');
        });

        Schema::create('biometric_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_device_id')->constrained('attendance_devices')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->string('device_user_id', 64);
            $table->text('card_number')->nullable(); // encrypted
            $table->string('external_reference', 128)->nullable();
            $table->string('status', 16)->default('active'); // pending | active | inactive
            $table->date('enrolled_on')->nullable();
            $table->foreignId('enrolled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deactivated_at')->nullable();
            $table->foreignId('deactivated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('remarks')->nullable();
            $table->timestamps();

            $key = DB::getDriverName() === 'sqlite'
                ? "attendance_device_id || ':' || device_user_id"
                : "concat(attendance_device_id, ':', device_user_id)";
            $table->string('active_device_user_key', 140)->nullable()
                ->storedAs("case when status <> 'inactive' then {$key} end");
            $table->unique('active_device_user_key', 'biometric_enrollments_active_unique');
            $table->index(['employee_id', 'status'], 'biometric_enrollments_employee_idx');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE attendance_devices ADD CONSTRAINT attendance_devices_vendor_check CHECK (vendor IN ('hikvision', 'zkteco', 'simulator', 'manual'))");
            DB::statement("ALTER TABLE attendance_devices ADD CONSTRAINT attendance_devices_status_check CHECK (status IN ('online', 'offline', 'error', 'unknown'))");
            DB::statement("ALTER TABLE biometric_enrollments ADD CONSTRAINT biometric_enrollments_status_check CHECK (status IN ('pending', 'active', 'inactive'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_enrollments');
        Schema::dropIfExists('attendance_device_sync_runs');
        Schema::dropIfExists('attendance_devices');
        Schema::dropIfExists('attendance_connectors');
    }
};
