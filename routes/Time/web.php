<?php

use App\Http\Controllers\Web\Time\EmployeeWorkScheduleController;
use App\Http\Controllers\Web\Time\HolidayController;
use App\Http\Controllers\Web\Time\MyAttendanceController;
use App\Http\Controllers\Web\Time\MyScheduleController;
use App\Http\Controllers\Web\Time\ShiftController;
use App\Http\Controllers\Web\Time\TimeLogController;
use App\Http\Controllers\Web\Time\WorkScheduleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('time')->name('time.')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Shift Routes
    |--------------------------------------------------------------------------
    */
    /*
    |--------------------------------------------------------------------------
    | Employee Self-Service (own records only; no employee parameter)
    |--------------------------------------------------------------------------
    */
    Route::get('my-schedule', [MyScheduleController::class, 'index'])
        ->name('my-schedule.index')
        ->middleware('can:work_schedule.view_own');

    Route::prefix('my-attendance')->name('my-attendance.')->group(function () {
        Route::get('/export', [MyAttendanceController::class, 'export'])
            ->name('export')
            ->middleware('can:attendance.export_own');
        Route::get('/', [MyAttendanceController::class, 'index'])
            ->name('index')
            ->middleware('can:attendance.view_own');
    });

    Route::prefix('shifts')->name('shifts.')->group(function () {
        Route::get('/', [ShiftController::class, 'index'])
            ->name('index')
            ->middleware('can:shift.view');

        Route::get('/create', [ShiftController::class, 'create'])
            ->name('create')
            ->middleware('can:shift.create');

        Route::post('/', [ShiftController::class, 'store'])
            ->name('store')
            ->middleware('can:shift.create');

        Route::get('/{shift}/edit', [ShiftController::class, 'edit'])
            ->name('edit')
            ->middleware('can:shift.update');

        Route::put('/{shift}', [ShiftController::class, 'update'])
            ->name('update')
            ->middleware('can:shift.update');

        Route::delete('/{shift}', [ShiftController::class, 'destroy'])
            ->name('destroy')
            ->middleware('can:shift.archive');
    });

    /*
    |--------------------------------------------------------------------------
    | Work Schedule Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('work-schedules')->name('work-schedules.')->group(function () {
        Route::get('/', [WorkScheduleController::class, 'index'])
            ->name('index')
            ->middleware('can:work_schedule.view');

        Route::get('/create', [WorkScheduleController::class, 'create'])
            ->name('create')
            ->middleware('can:work_schedule.create');

        Route::post('/', [WorkScheduleController::class, 'store'])
            ->name('store')
            ->middleware('can:work_schedule.create');

        Route::get('/{workSchedule}', [WorkScheduleController::class, 'show'])
            ->name('show')
            ->middleware('can:work_schedule.view');

        Route::get('/{workSchedule}/edit', [WorkScheduleController::class, 'edit'])
            ->name('edit')
            ->middleware('can:work_schedule.update');

        Route::put('/{workSchedule}', [WorkScheduleController::class, 'update'])
            ->name('update')
            ->middleware('can:work_schedule.update');

        Route::delete('/{workSchedule}', [WorkScheduleController::class, 'destroy'])
            ->name('destroy')
            ->middleware('can:work_schedule.archive');
    });

    /*
    |--------------------------------------------------------------------------
    | Employee Work Schedule Assignment Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('employee-schedules')->name('employee-schedules.')->group(function () {
        Route::get('/', [EmployeeWorkScheduleController::class, 'index'])
            ->name('index')
            ->middleware('can:employee_work_schedule.view');

        Route::post('/', [EmployeeWorkScheduleController::class, 'store'])
            ->name('store')
            ->middleware('can:employee_work_schedule.assign');

        Route::put('/{employeeWorkSchedule}', [EmployeeWorkScheduleController::class, 'update'])
            ->name('update')
            ->middleware('can:employee_work_schedule.update');

        Route::delete('/{employeeWorkSchedule}', [EmployeeWorkScheduleController::class, 'destroy'])
            ->name('destroy')
            ->middleware('can:employee_work_schedule.remove');
    });

    /*
    |--------------------------------------------------------------------------
    | Time Logs: raw device punches, organization-wide (not a DTR)
    |--------------------------------------------------------------------------
    */
    Route::get('time-logs', [TimeLogController::class, 'index'])
        ->name('time-logs.index')
        ->middleware('can:attendance.view');

    Route::prefix('holidays')->name('holidays.')->group(function () {
        Route::get('/', [HolidayController::class, 'index'])
            ->name('index')
            ->middleware('can:holiday.view');

        Route::post('/', [HolidayController::class, 'store'])
            ->name('store')
            ->middleware('can:holiday.create');

        Route::put('/{holiday}', [HolidayController::class, 'update'])
            ->name('update')
            ->middleware('can:holiday.update');

        Route::delete('/{holiday}', [HolidayController::class, 'destroy'])
            ->name('destroy')
            ->middleware('can:holiday.delete');
    });
});
