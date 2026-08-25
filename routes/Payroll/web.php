<?php

use App\Http\Controllers\Web\Payroll\PayrollAccountTypeController;
use App\Http\Controllers\Web\Payroll\PayrollCalendarController;
use App\Http\Controllers\Web\Payroll\PayrollDeductionController;
use App\Http\Controllers\Web\Payroll\PayrollEmployeeAccountController;
use App\Http\Controllers\Web\Payroll\PayrollEmployeeDeductionController;
use App\Http\Controllers\Web\Payroll\PayrollProjectFundController;
use Illuminate\Support\Facades\Route; 

Route::middleware(['auth', 'web'])->group(function () {
    Route::prefix('payroll')->name('payroll.')->group(function () {
        /* Payroll Generation */
        Route::name('generation.')->group(function(){
            Route::controller(PayrollCalendarController::class)->name('calendar.')->group(function () {
                Route::get('/calendar', 'index')->name('index');
                Route::get('/calendar/create', 'create')->name('create');
                Route::post('/calendar/store', 'store')->name('store');
                Route::get('/calendar/edit/{id}', 'edit')->name('edit');
                Route::put('/calendar/update/{id}', 'update')->name('update');
                Route::delete('/calendar/delete/{id}', 'delete')->name('delete');
            });
        });
        
        /* Employee Maintenance */
        Route::name('employee.maintenance.')->group(function () {

            Route::controller(PayrollEmployeeAccountController::class)->name('accounts.')->group(function () {
                Route::match(['get', 'post'], '/employee-account', 'index')->name('index');
                Route::get('/employee-account/create/{id}', 'create')->name('create');
                Route::post('/employee-account/store', 'store')->name('store');
                Route::get('/employee-account/view/{id}', 'view')->name('view');
                Route::get('/employee-account/edit/{id}', 'edit')->name('edit');
                Route::put('/employee-account/update/{id}', 'update')->name('update');
                Route::delete('/employee-account/delete/{id}', 'delete')->name('delete');
            });

            Route::controller(PayrollEmployeeDeductionController::class)->name('deductions.')->group(function () {
                Route::match(['get', 'post'], '/employee-deduction', 'index')->name('index');
                Route::get('/employee-deduction/view/{id}', 'view')->name('view');
                Route::get('/employee-deduction/create/{id}', 'create')->name('create');
                Route::post('/employee-deduction/store', 'store')->name('store');
                Route::get('/employee-deduction/edit/{id}', 'edit')->name('edit');
                Route::put('/employee-deduction/update/{id}', 'update')->name('update');
                Route::delete('/employee-deduction/delete/{id}', 'delete')->name('delete');
            });
        });

        /* Maintenance */
        Route::name('maintenance.')->group(function () {
            Route::controller(PayrollAccountTypeController::class)->name('accounttype.')->group(function () {
                Route::get('/account-type', 'index')->name('index');
                Route::get('/account-type/create', 'create')->name('create');
                Route::post('/account-type/store', 'store')->name('store');
                Route::get('/account-type/edit/{id}', 'edit')
                ->middleware('signed')
                ->name('edit');
                Route::put('/account-type/update/{id}', 'update')->name('update');
                Route::delete('/account-type/delete/{id}', 'delete')->name('delete');
            });

            Route::controller(PayrollDeductionController::class)->name('deduction.')->group(function () {
                Route::get('/deduction', 'index')->name('index');
                Route::get('/deduction/create', 'create')->name('create');
                Route::post('/deduction/store', 'store')->name('store');
                Route::get('/deduction/edit/{id}', 'edit')
                ->middleware('signed')
                ->name('edit');
                Route::put('/deduction/update/{id}', 'update')->name('update');
                Route::delete('/deduction/delete/{id}', 'delete')->name('delete');
            });

            Route::controller(PayrollProjectFundController::class)->name('projectfund.')->group(function () {
                Route::match(['get', 'post'], '/project-fund', 'index')->name('index');
                Route::get('/project-fund/create', 'create')->name('create');
                Route::post('/project-fund/store', 'store')->name('store');
                Route::get('/project-fund/edit/{id}', 'edit')
                ->middleware('signed')
                ->name('edit');

                Route::get('/project-fund/assign/{id}', 'assign')
                ->middleware('signed')
                ->name('assign');
                
                Route::post('/project-fund/assign-employee', 'assignEmployee')->name('assignEmployee');
                Route::post('/project-fund/assign-employees-bulk', 'assignEmployeesBulk')->name('assignEmployeesBulk');
                Route::delete('/project-fund/remove-employee/{employeeNumber}', 'removeEmployee')->name('removeEmployee');
                Route::post('/project-fund/remove-employees-bulk', 'removeEmployeesBulk')->name('removeEmployeesBulk');
                Route::put('/project-fund/update/{id}', 'update')->name('update');
                Route::delete('/project-fund/delete/{id}', 'delete')->name('delete');
            });
        });
    });
});
