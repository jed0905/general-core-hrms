<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Retires the legacy attendance system; the attendance module
 * (2026_10_31_000001-000005) is the only attendance architecture.
 *
 * - Drops employee_attendance_logs and employee_biometric_ids (created by
 *   2025_05_19_000018 and 2026_01_07_082554, which stay in the history
 *   because they ran on existing databases). Their rows are discarded: the
 *   owner confirmed they hold test/demo data only. No table references them.
 * - Removes attendance.update, attendance.delete and attendance.reprocess,
 *   which no code checks (raw events are append-only; corrections are
 *   approved adjustments; recalculation is attendance.process).
 *
 * down() recreates the empty legacy tables and the permissions with the
 * seeder's former default grants. Dropped rows cannot be restored.
 */
return new class extends Migration
{
    public const PERMISSIONS = ['attendance.update', 'attendance.delete', 'attendance.reprocess'];

    public const FORMER_GRANTS = [
        'superadmin' => self::PERMISSIONS,
        'hr_director' => self::PERMISSIONS,
        'hr_manager' => ['attendance.update', 'attendance.reprocess'],
        'hr_staff' => ['attendance.update'],
    ];

    public function up(): void
    {
        Schema::dropIfExists('employee_attendance_logs');
        Schema::dropIfExists('employee_biometric_ids');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::whereIn('name', self::PERMISSIONS)->where('guard_name', 'web')->get()->each->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (! Schema::hasTable('employee_attendance_logs')) {
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

        if (! Schema::hasTable('employee_biometric_ids')) {
            Schema::create('employee_biometric_ids', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete()->cascadeOnUpdate();
                $table->string('biometric_id')->unique();
                $table->timestamps();
            });
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (self::FORMER_GRANTS as $roleName => $permissions) {
            Role::where('name', $roleName)->where('guard_name', 'web')->first()?->givePermissionTo($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
