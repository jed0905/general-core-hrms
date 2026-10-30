<?php

use App\Http\Controllers\Web\Payroll\PayrollAccountTypeController;
use App\Http\Controllers\Web\Payroll\PayrollCalendarController;
use App\Http\Controllers\Web\Payroll\PayrollDashboardController;
use App\Http\Controllers\Web\Payroll\PayrollDeductionController;
use App\Http\Controllers\Web\Payroll\PayrollDisbursementController;
use App\Http\Controllers\Web\Payroll\PayrollEmployeeAccountController;
use App\Http\Controllers\Web\Payroll\PayrollEmployeeController;
use App\Http\Controllers\Web\Payroll\PayrollEmployeeDeductionController;
use App\Http\Controllers\Web\Payroll\PayrollInputController;
use App\Http\Controllers\Web\Payroll\PayrollProjectFundController;
use App\Http\Controllers\Web\Payroll\PayrollRunController;
use App\Http\Controllers\Web\Payroll\PayrollSetupController;
use App\Http\Controllers\Web\Payroll\PayslipController;
use App\Http\Controllers\Web\Payroll\StatutoryRuleController;
use App\Models\PayrollRun;
use App\Models\PayrollRunEmployee;
use App\Models\StatutoryRuleVersion;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'web'])->group(function () {
    Route::prefix('payroll')->name('payroll.')->group(function () {
        /* Payroll Generation */
        Route::name('generation.')->group(function () {
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

/*
|--------------------------------------------------------------------------
| Global Payroll (core + country localizations)
|--------------------------------------------------------------------------
| Coexists with the legacy group above (different route names and URLs; the
| legacy controllers are not used). Every route carries a permission or policy
| check; record-level rules and separation of duties live in the policies and
| are re-checked in the services. Role names are never checked.
*/

Route::middleware(['auth', 'verified'])->prefix('payroll')->name('payroll.')->group(function () {
    Route::get('dashboard', [PayrollDashboardController::class, 'index'])
        ->middleware('can:payroll.dashboard.view')
        ->name('dashboard');

    // Self-service
    Route::get('my-payslips', [PayslipController::class, 'mine'])
        ->middleware('can:viewOwn,'.PayrollRunEmployee::class)
        ->name('my-payslips.index');
    Route::prefix('payslips')->name('payslips.')->group(function () {
        Route::get('{payslip}', [PayslipController::class, 'show'])->middleware('can:view,payslip')->name('show');
        Route::get('{payslip}/pdf', [PayslipController::class, 'pdf'])->middleware('can:view,payslip')->name('pdf');
    });

    // Configuration
    Route::prefix('setup')->name('setup.')->group(function () {
        Route::get('/', [PayrollSetupController::class, 'index'])->middleware('can:payroll.config.view')->name('index');

        Route::middleware('can:payroll.config.manage')->group(function () {
            Route::post('legal-entities', [PayrollSetupController::class, 'storeLegalEntity'])->name('legal-entities.store');
            Route::put('legal-entities/{legalEntity}', [PayrollSetupController::class, 'updateLegalEntity'])->name('legal-entities.update');
            Route::put('legal-entities/{legalEntity}/users', [PayrollSetupController::class, 'syncEntityUsers'])->middleware('can:payroll.scope.all')->name('legal-entities.users');
            Route::post('pay-groups', [PayrollSetupController::class, 'storePayGroup'])->name('pay-groups.store');
            Route::put('pay-groups/{payGroup}', [PayrollSetupController::class, 'updatePayGroup'])->name('pay-groups.update');
            Route::post('pay-groups/{payGroup}/calendars', [PayrollSetupController::class, 'generateCalendar'])->name('pay-groups.calendars.store');
            Route::put('periods/{payrollPeriod}', [PayrollSetupController::class, 'updatePeriod'])->name('periods.update');
            Route::put('periods/{payrollPeriod}/status', [PayrollSetupController::class, 'setPeriodStatus'])->name('periods.status');
            Route::post('pay-components', [PayrollSetupController::class, 'storePayComponent'])->name('pay-components.store');
            Route::put('pay-components/{payComponent}', [PayrollSetupController::class, 'updatePayComponent'])->name('pay-components.update');
            Route::post('salary-structures', [PayrollSetupController::class, 'storeSalaryStructure'])->name('salary-structures.store');
            Route::put('salary-structures/{salaryStructure}', [PayrollSetupController::class, 'updateSalaryStructure'])->name('salary-structures.update');
            Route::post('exchange-rates', [PayrollSetupController::class, 'storeExchangeRate'])->name('exchange-rates.store');
        });
    });

    // Employee payroll profiles
    Route::prefix('employees')->name('employees.')->group(function () {
        Route::get('/', [PayrollEmployeeController::class, 'index'])->middleware('can:viewPayrollEmployees')->name('index');
        Route::get('{employee}', [PayrollEmployeeController::class, 'show'])->middleware('can:viewPayrollEmployees')->name('show');
        Route::post('{employee}/assignments', [PayrollEmployeeController::class, 'storeAssignment'])->middleware('can:payroll.config.manage')->name('assignments.store');
        Route::post('{employee}/residencies', [PayrollEmployeeController::class, 'storeResidency'])->middleware('can:payroll.config.manage')->name('residencies.store');
        Route::post('{employee}/statutory-profiles', [PayrollEmployeeController::class, 'storeStatutoryProfile'])->middleware('can:payroll.config.manage')->name('statutory-profiles.store');
        Route::post('{employee}/bank-accounts', [PayrollEmployeeController::class, 'storeBankAccount'])->middleware('can:payroll.config.manage')->name('bank-accounts.store');
    });
    Route::put('assignments/{employmentAssignment}/end', [PayrollEmployeeController::class, 'endAssignment'])->middleware('can:payroll.config.manage')->name('assignments.end');
    Route::post('assignments/{employmentAssignment}/compensations', [PayrollEmployeeController::class, 'storeCompensation'])->middleware('can:compensation.create')->name('compensations.store');
    Route::put('compensations/{employeeCompensation}/approve', [PayrollEmployeeController::class, 'approveCompensation'])->middleware('can:compensation.approve')->name('compensations.approve');
    Route::delete('compensations/{employeeCompensation}', [PayrollEmployeeController::class, 'discardCompensation'])->middleware('can:compensation.update')->name('compensations.destroy');
    Route::post('assignments/{employmentAssignment}/pay-components', [PayrollEmployeeController::class, 'storePayComponent'])->middleware('can:compensation.create')->name('employee-pay-components.store');
    Route::put('employee-pay-components/{employeePayComponent}/end', [PayrollEmployeeController::class, 'endPayComponent'])->middleware('can:compensation.update')->name('employee-pay-components.end');
    Route::put('bank-accounts/{employeeBankAccount}/deactivate', [PayrollEmployeeController::class, 'deactivateBankAccount'])->middleware('can:payroll.config.manage')->name('bank-accounts.deactivate');

    // Statutory rules (any country)
    Route::prefix('statutory-rules')->name('statutory-rules.')->group(function () {
        Route::get('/', [StatutoryRuleController::class, 'index'])->middleware('can:viewAny,'.StatutoryRuleVersion::class)->name('index');
        Route::post('/', [StatutoryRuleController::class, 'store'])->middleware('can:create,'.StatutoryRuleVersion::class)->name('store');
        Route::put('{statutoryRule}', [StatutoryRuleController::class, 'update'])->middleware('can:update,statutoryRule')->name('update');
        Route::put('{statutoryRule}/verify', [StatutoryRuleController::class, 'verify'])->middleware('can:verify,statutoryRule')->name('verify');
        Route::put('{statutoryRule}/reject', [StatutoryRuleController::class, 'reject'])->middleware('can:reject,statutoryRule')->name('reject');
    });

    // Period inputs
    Route::prefix('inputs')->name('inputs.')->group(function () {
        Route::get('/', [PayrollInputController::class, 'index'])->middleware('can:accessPayrollInputs')->name('index');
        Route::post('periods/{payrollPeriod}/time', [PayrollInputController::class, 'storeTimeInput'])->middleware('can:payroll.input.manage')->name('time.store');
        Route::post('periods/{payrollPeriod}/time/import', [PayrollInputController::class, 'importTimeInputs'])->middleware('can:payroll.input.manage')->name('time.import');
        Route::put('periods/{payrollPeriod}/time/approve', [PayrollInputController::class, 'approveTimeInputs'])->middleware('can:payroll.input.approve')->name('time.approve');
        Route::delete('time/{payrollTimeInput}', [PayrollInputController::class, 'destroyTimeInput'])->middleware('can:payroll.input.manage')->name('time.destroy');
        Route::post('periods/{payrollPeriod}/adjustments', [PayrollInputController::class, 'storeAdjustment'])->middleware('can:payroll.input.manage')->name('adjustments.store');
        Route::put('adjustments/{payrollAdjustment}/approve', [PayrollInputController::class, 'approveAdjustment'])->middleware('can:payroll.input.approve')->name('adjustments.approve');
        Route::delete('adjustments/{payrollAdjustment}', [PayrollInputController::class, 'destroyAdjustment'])->middleware('can:payroll.input.manage')->name('adjustments.destroy');
    });

    // Runs
    Route::prefix('runs')->name('runs.')->group(function () {
        Route::get('/', [PayrollRunController::class, 'index'])->middleware('can:viewAny,'.PayrollRun::class)->name('index');
        Route::post('/', [PayrollRunController::class, 'store'])->middleware('can:create,'.PayrollRun::class)->name('store');
        Route::get('{payrollRun}', [PayrollRunController::class, 'show'])->middleware('can:view,payrollRun')->name('show');
        Route::get('{payrollRun}/export', [PayrollRunController::class, 'export'])->middleware('can:export,payrollRun')->name('export');
        Route::put('{payrollRun}/calculate', [PayrollRunController::class, 'calculate'])->middleware('can:calculate,payrollRun')->name('calculate');
        Route::put('{payrollRun}/exclude', [PayrollRunController::class, 'exclude'])->middleware('can:manageEmployees,payrollRun')->name('exclude');
        Route::put('{payrollRun}/include/{assignment}', [PayrollRunController::class, 'include'])->middleware('can:manageEmployees,payrollRun')->name('include');
        Route::put('{payrollRun}/review', [PayrollRunController::class, 'review'])->middleware('can:review,payrollRun')->name('review');
        Route::put('{payrollRun}/approve', [PayrollRunController::class, 'approve'])->middleware('can:approve,payrollRun')->name('approve');
        Route::put('{payrollRun}/return', [PayrollRunController::class, 'returnToDraft'])->middleware('can:returnToDraft,payrollRun')->name('return');
        Route::put('{payrollRun}/cancel', [PayrollRunController::class, 'cancel'])->middleware('can:cancel,payrollRun')->name('cancel');
        Route::put('{payrollRun}/finalize', [PayrollRunController::class, 'finalize'])->middleware('can:finalize,payrollRun')->name('finalize');
        Route::post('{payrollRun}/reverse', [PayrollRunController::class, 'reverse'])->middleware('can:reverse,payrollRun')->name('reverse');
        Route::get('{payrollRun}/disbursements/csv', [PayrollDisbursementController::class, 'csv'])->middleware('can:disburse,payrollRun')->name('disbursements.csv');
        Route::post('{payrollRun}/disbursements/pain001', [PayrollDisbursementController::class, 'pain001'])->middleware('can:disburse,payrollRun')->name('disbursements.pain001');
        Route::put('{payrollRun}/disbursements/{payrollDisbursement}', [PayrollDisbursementController::class, 'update'])->middleware('can:disburse,payrollRun')->name('disbursements.update');
    });
});
