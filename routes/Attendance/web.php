<?php

use App\Http\Controllers\Web\Attendance\AttendanceAdjustmentController;
use App\Http\Controllers\Web\Attendance\AttendanceConnectorController;
use App\Http\Controllers\Web\Attendance\AttendanceDashboardController;
use App\Http\Controllers\Web\Attendance\AttendanceDayController;
use App\Http\Controllers\Web\Attendance\AttendanceDeviceController;
use App\Http\Controllers\Web\Attendance\AttendanceLogController;
use App\Http\Controllers\Web\Attendance\AttendanceRosterController;
use App\Http\Controllers\Web\Attendance\AttendanceSettingsController;
use App\Http\Controllers\Web\Attendance\AttendanceSummaryController;
use App\Http\Controllers\Web\Attendance\BiometricEnrollmentController;
use App\Http\Controllers\Web\Attendance\MyAttendanceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Attendance
|--------------------------------------------------------------------------
| Every route is authorized by permission (and policy where a record decides).
| Employee-level visibility is applied by AttendanceScope in the services.
| Device-initiated endpoints live in routes/Attendance/device.php.
*/

Route::middleware(['auth', 'verified'])->prefix('attendance')->name('attendance.')->group(function () {
    Route::get('dashboard', [AttendanceDashboardController::class, 'index'])->middleware('can:attendance.view')->name('dashboard');

    // Employee self-service: own records only.
    Route::prefix('my')->name('my.')->group(function () {
        Route::get('/', [MyAttendanceController::class, 'index'])->middleware('can:attendance.view_own')->name('index');
        Route::get('export', [MyAttendanceController::class, 'export'])->middleware('can:attendance.export_own')->name('export');
    });

    Route::prefix('devices')->name('devices.')->group(function () {
        Route::get('/', [AttendanceDeviceController::class, 'index'])->middleware('can:attendance.device.view')->name('index');
        Route::post('/', [AttendanceDeviceController::class, 'store'])->middleware('can:attendance.device.manage')->name('store');
        Route::get('{attendanceDevice}', [AttendanceDeviceController::class, 'show'])->middleware('can:attendance.device.view')->name('show');
        Route::put('{attendanceDevice}', [AttendanceDeviceController::class, 'update'])->middleware('can:attendance.device.manage')->name('update');
        Route::put('{attendanceDevice}/connection', [AttendanceDeviceController::class, 'updateConnection'])->middleware('can:attendance.device.configure')->name('connection');
        Route::post('{attendanceDevice}/push-token', [AttendanceDeviceController::class, 'rotatePushToken'])->middleware('can:attendance.device.configure')->name('push-token');
        Route::put('{attendanceDevice}/toggle', [AttendanceDeviceController::class, 'toggle'])->middleware('can:attendance.device.manage')->name('toggle');
        Route::post('{attendanceDevice}/test', [AttendanceDeviceController::class, 'test'])->middleware('can:attendance.device.sync')->name('test');
        Route::post('{attendanceDevice}/sync', [AttendanceDeviceController::class, 'sync'])->middleware('can:attendance.device.sync')->name('sync');
    });

    Route::prefix('connectors')->name('connectors.')->middleware('can:attendance.device.configure')->group(function () {
        Route::post('/', [AttendanceConnectorController::class, 'store'])->name('store');
        Route::post('{attendanceConnector}/rotate', [AttendanceConnectorController::class, 'rotate'])->name('rotate');
        Route::put('{attendanceConnector}/toggle', [AttendanceConnectorController::class, 'toggle'])->name('toggle');
    });

    Route::prefix('enrollments')->name('enrollments.')->group(function () {
        Route::get('/', [BiometricEnrollmentController::class, 'index'])->middleware('can:attendance.enrollment.view')->name('index');
        Route::middleware('can:attendance.enrollment.manage')->group(function () {
            Route::post('/', [BiometricEnrollmentController::class, 'store'])->name('store');
            Route::put('{biometricEnrollment}/deactivate', [BiometricEnrollmentController::class, 'deactivate'])->name('deactivate');
            Route::put('{biometricEnrollment}/activate', [BiometricEnrollmentController::class, 'activate'])->name('activate');
            Route::put('{biometricEnrollment}/reassign', [BiometricEnrollmentController::class, 'reassign'])->name('reassign');
        });
    });

    Route::prefix('logs')->name('logs.')->group(function () {
        Route::get('/', [AttendanceLogController::class, 'index'])->middleware('can:attendance.log.view')->name('index');
        Route::get('template', [AttendanceLogController::class, 'template'])->middleware('can:attendance.log.import')->name('template');
        Route::post('/', [AttendanceLogController::class, 'store'])->middleware('can:attendance.create')->name('store');
        Route::post('import', [AttendanceLogController::class, 'import'])->middleware('can:attendance.log.import')->name('import');
    });

    Route::prefix('daily')->name('daily.')->group(function () {
        Route::get('/', [AttendanceDayController::class, 'index'])->middleware('can:attendance.view')->name('index');
        Route::post('recalculate', [AttendanceDayController::class, 'recalculate'])->middleware('can:attendance.process')->name('recalculate');
        Route::get('employees/{employee}', [AttendanceDayController::class, 'employee'])->middleware('can:attendance.view')->name('employee');
    });

    Route::prefix('adjustments')->name('adjustments.')->group(function () {
        Route::get('/', [AttendanceAdjustmentController::class, 'index'])->middleware('can:accessAttendanceAdjustments')->name('index');
        Route::post('/', [AttendanceAdjustmentController::class, 'store'])->middleware('can:attendance.adjust')->name('store');
        Route::put('{attendanceAdjustment}/approve', [AttendanceAdjustmentController::class, 'approve'])->middleware('can:decide,attendanceAdjustment')->name('approve');
        Route::put('{attendanceAdjustment}/reject', [AttendanceAdjustmentController::class, 'reject'])->middleware('can:decide,attendanceAdjustment')->name('reject');
    });

    Route::prefix('roster')->name('roster.')->group(function () {
        Route::get('/', [AttendanceRosterController::class, 'index'])->middleware('can:attendance.view')->name('index');
        Route::post('/', [AttendanceRosterController::class, 'store'])->middleware('can:attendance.roster.manage')->name('store');
    });

    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [AttendanceSettingsController::class, 'index'])->middleware('can:accessAttendanceSettings')->name('index');
        Route::post('policies', [AttendanceSettingsController::class, 'storePolicy'])->middleware('can:attendance.config.manage')->name('policies.store');
        Route::put('policies/{attendancePolicy}', [AttendanceSettingsController::class, 'updatePolicy'])->middleware('can:attendance.config.manage')->name('policies.update');
    });

    Route::prefix('summaries')->name('summaries.')->group(function () {
        Route::get('/', [AttendanceSummaryController::class, 'index'])->middleware('can:accessAttendanceSummaries')->name('index');
        Route::post('generate', [AttendanceSummaryController::class, 'generate'])->middleware('can:attendance.process')->name('generate');
        Route::put('approve', [AttendanceSummaryController::class, 'approve'])->middleware('can:attendance.finalize')->name('approve');
    });
});
