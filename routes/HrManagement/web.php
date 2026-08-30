<?php

use App\Http\Controllers\Api\PsaVerificationController;
use App\Http\Controllers\Web\HrManagement\AttendanceLogEventTypesController;
use App\Http\Controllers\Web\HrManagement\DailyShiftSchedulesController;
use App\Http\Controllers\Web\HrManagement\DailyTimeRecordController;
use App\Http\Controllers\Web\HrManagement\DesignationController;
use App\Http\Controllers\Web\HrManagement\EmployeeController;
use App\Http\Controllers\Web\HrManagement\EmployeeLeaveSchedulerController;
use App\Http\Controllers\Web\HrManagement\EmployeeWorkShiftsController;
use App\Http\Controllers\Web\HrManagement\HolidayController;
use App\Http\Controllers\Web\HrManagement\JobStatusController;
use App\Http\Controllers\Web\HrManagement\LeaveController;
use App\Http\Controllers\Web\HrManagement\PositionController;
use App\Http\Controllers\Web\HrManagement\SalaryController;
use App\Http\Controllers\Web\HrManagement\SeparatedEmployeesController;
use App\Http\Controllers\Web\HrManagement\SpecialLeaveController;
use App\Http\Controllers\Web\HrManagement\UniversityActivitiesController;
use App\Http\Controllers\Web\HrManagement\WeeklyShiftDaysController;
use App\Http\Controllers\Web\HrManagement\WeeklyShiftTemplatesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'web'])->group(function () {

    Route::prefix('hrmanagement')->name('hrmanagement.')->group(function () {

        // Job Structure
        Route::prefix('jobstructure')->name('jobstructure.')->group(function () {
            // Job Status Controller
            Route::controller(JobStatusController::class)->name('jobstatus.')->group(function () {
                Route::get('/jobstatus', 'index')
                    ->middleware(['permission:job_structure.view'])
                    ->name('index');
                Route::middleware('permission:job_structure.manage')->group(function () {
                    Route::get('/create', 'create')->name('create');
                    Route::post('/store', 'store')->name('store');
                    Route::get('/edit/{id}', 'edit')
                        ->middleware('signed')
                        ->name('edit');
                    Route::put('/{id}', 'update')->name('update');
                    Route::delete('/{id}', 'destroy')->name('destroy');
                });

            });

            // Positions Controller
            Route::controller(PositionController::class)->prefix('position')->name('position.')->group(function () {
                // View
                Route::match(['get', 'post'], '/', 'index')
                    ->middleware(['permission:job_structure.view'])
                    ->name('index');

                // Manage
                Route::middleware('permission:job_structure.manage')->group(function () {
                    Route::get('/create', 'create')->name('create');
                    Route::post('/store', 'store')->name('store');
                    Route::get('/edit/{id}', 'edit')
                        ->middleware('signed')
                        ->name('edit');
                    Route::put('/{id}', 'update')->name('update');
                    Route::delete('/{id}', 'destroy')->name('destroy');
                });
            });

            // Designations Controller
            Route::controller(DesignationController::class)->prefix('designation')->name('designation.')->group(function () {
                // View
                Route::match(['get', 'post'], '/', 'index')
                    ->middleware(['permission:job_structure.view'])
                    ->name('index');

                Route::post('/designations/{designation}/toggle-status', 'toggleStatus')
                    ->name('toggle-status');

                // Manage
                Route::middleware('permission:job_structure.manage')->group(function () {
                    Route::get('/create', 'create')->name('create');
                    Route::post('/store', 'store')->name('store');
                    Route::get('/edit/{id}', 'edit')
                        ->middleware('signed')
                        ->name('edit');
                    Route::put('/{id}', 'update')->name('update');
                    Route::delete('/{id}', 'destroy')->name('destroy');
                });

            });

            // Salary
            Route::controller(SalaryController::class)->prefix('salary')->name('salary.')->group(function () {
                // View
                Route::match(['get', 'post'], '/', 'index')
                    ->middleware(['permission:job_structure.view'])
                    ->name('index');

                Route::middleware('permission:job_structure.manage')->group(function () {
                    Route::get('/create', 'create')->name('create');
                    Route::post('/store', 'store')->name('store');
                    Route::get('/edit/{id}', 'edit')
                        ->middleware('signed')
                        ->name('edit');
                    Route::put('/update/{id}', 'update')->name('update');
                    Route::put('/toggle-status/{id}', 'updateStatus')->name('toggle-status');
                    Route::get('/view/{id}', 'viewSalaryMatrix')
                        ->middleware('signed')
                        ->name('view');

                    Route::get('/download-template', 'downloadTemplate')->name('download-template');
                    Route::post('/upload/{schedule}', 'upload')->name('upload');
                });
            });

            Route::controller(DailyShiftSchedulesController::class)->name('dailyShiftSchedules.')->group(function () {
                Route::match(['get', 'post'], 'daily-shift-schedules/index', 'index')->name('index');
                Route::get('daily-shift-schedules/create', 'create')->name('create');
                Route::post('daily-shift-schedules/store', 'store')->name('store');
                Route::get('daily-shift-schedules/edit/{id}', 'edit')
                    ->middleware('signed')
                    ->name('edit');
                Route::put('daily-shift-schedules/update/{id}', 'update')->name('update');
                Route::delete('daily-shift-schedules/delete/{id}', 'destroy')->name('destroy');
            });

            Route::controller(WeeklyShiftTemplatesController::class)->name('weeklyShiftTemplates.')->group(function () {
                Route::match(['get', 'post'], 'weekly-shift-templates/index', 'index')->name('index');
                Route::get('weekly-shift-templates/create', 'create')->name('create');
                Route::post('weekly-shift-templates/store', 'store')->name('store');
                Route::get('weekly-shift-templates/edit/{id}', 'edit')
                    ->middleware('signed')
                    ->name('edit');
                Route::put('weekly-shift-templates/update/{id}', 'update')->name('update');
                Route::delete('weekly-shift-templates/delete/{id}', 'destroy')->name('destroy');
                Route::match(['get', 'post'], 'weekly-shift-templates/manage/{id}', 'manage')->name('manage');
                Route::post('weekly-shift-templates/save-schedule/{id}', 'saveSchedule')->name('saveSchedule');
                Route::post('weekly-shift-templates/save-single-schedule/{id}', 'saveSingleSchedule')->name('saveSingleSchedule');
                Route::delete('weekly-shift-templates/delete-single-schedule/{id}', 'deleteSingleSchedule')->name('deleteSingleSchedule');
                Route::delete('weekly-shift-templates/delete-batch/{id}', 'deleteBatch')->name('deleteBatch');
            });

            // Route::controller(WeeklyShiftDaysController::class)->name('weeklyShiftDays.')->group(function () {
            //     Route::get('weekly-shift-days/index', 'index')->name('index');
            //     Route::get('weekly-shift-days/create', 'create')->name('create');
            //     Route::post('weekly-shift-days/store', 'store')->name('store');
            //     Route::get('weekly-shift-days/edit/{id}', 'edit')->name('edit');
            //     Route::put('weekly-shift-days/update/{id}', 'update')->name('update');
            //     Route::delete('weekly-shift-days/delete/{id}', 'destroy')->name('destroy');
            // });

            // Route::controller(EmployeeWorkShiftsController::class)->name('employeeWorkShifts.')->group(function () {
            //     Route::get('employee-work-shifts/index', 'index')->name('index');
            //     Route::get('employee-work-shifts/create', 'create')->name('create');
            //     Route::post('employee-work-shifts/store', 'store')->name('store');
            //     Route::get('employee-work-shifts/edit/{id}', 'edit')->name('edit');
            //     Route::put('employee-work-shifts/update/{id}', 'update')->name('update');
            //     Route::delete('employee-work-shifts/delete/{id}', 'destroy')->name('destroy');
            // });
        });

        // Employee Controller
        Route::name('employee.')->group(function () {

            Route::controller(EmployeeController::class)->group(function () {
                Route::match(['get', 'post'], '/employee', 'index')
                    ->middleware(['permission:employee.view'])
                    ->name('index');

                Route::get('employee/movement', 'index')
                    ->name('movement.index');

                Route::middleware('permission:employee.create')->group(function () {
                    Route::get('/employee/create', 'create')->name('create');
                    Route::post('/employee/store', 'store')->name('store');
                    Route::post('/employee/store-psa-data', 'storePsaVerificationResponse')->name('storePsaData');
                });

                Route::middleware('permission:employee.edit,')->group(function () {
                    Route::get('/employee/edit/{id}', 'edit')
                        ->middleware('signed')
                        ->name('edit');

                    // Picture Upload
                    Route::post('/employee/upload-picture/', 'uploadPicture')->name('uploadPicture');

                    Route::put('/employee/job_details/{id}', 'updateJobDetails')->name('updateJobDetails');
                    Route::delete('/employee/job_details/deleteDesignation/{id}', 'deleteEmployeeDesignation')->name('deleteEmployeeDesignation');

                    Route::put('/employee/biometrics-id/{id}', 'updateBiometricsId')->name('updateBiometricsId');

                    Route::put('/employee/personal_information/{id}', 'updatePersonalInformation')->name('updatePersonalInformation');

                    Route::put('/employee/family_background/{id}', 'updateFamilyBackground')->name('updateFamilyBackground');
                    Route::delete('/employee/family_background/deleteChild/{id}', 'deleteEmployeeChild')->name('deleteEmployeeChild');

                    Route::put('/employee/educational_background/{id}', 'updateEmployeeEducationalBackground')->name('updateEmployeeEducationalBackground');
                    Route::delete('/employee/educational_background/deleteSchool/{id}/{level}', 'deleteEmployeeEducationalBackground')->name('deleteEmployeeEducationalBackground');

                    Route::put('/employee/civil_service_eligibility/{id}', 'updateCivilServiceEligibility')->name('updateCivilServiceEligibility');
                    Route::delete('/employee/civil_service_eligibility/delete/{id}', 'deleteCivilServiceEligibility')->name('deleteCivilServiceEligibility');
                    Route::put('/employee/additional_information/{id}', 'updateAdditionalInformation')->name('updateAdditionalInformation');

                    Route::put('/employee/work_experience/{id}', 'updateEmployeeWorkExperience')->name('updateEmployeeWorkExperience');
                    Route::delete('/employee/work_experience/deleteWorkExperience/{id}', 'deleteEmployeeWorkExperience')->name('deleteEmployeeWorkExperience');

                    Route::put('/employee/voluntary_work/{id}', 'updateEmployeeVoluntaryWork')->name('updateEmployeeVoluntaryWork');
                    Route::delete('/employee/voluntary_work/deleteVoluntaryWork/{id}', 'deleteEmployeeVoluntaryWork')->name('deleteEmployeeVoluntaryWork');

                    Route::put('/employee/learning_and_development/{id}', 'updateEmployeeLearningAndDevelopment')->name('updateEmployeeLearningAndDevelopment');
                    Route::delete('/employee/learning_and_development/deleteEmployeeLearningAndDevelopment/{id}', 'deleteEmployeeLearningAndDevelopment')->name('deleteEmployeeLearningAndDevelopment');

                    Route::post('/employee/update-employment-status', 'updateEmploymentStatus')
                        ->name('updateEmploymentStatus');
                });


                Route::delete('/employee/{id}', 'destroy')
                    ->middleware(['permission:employee.delete'])
                    ->name('destroy');

                Route::get('/employee/report/index', 'report')->name('report.index');
                Route::get('/employee/report/generate', 'generateReport')->name('report.generate');

                Route::post('/employee/update-employement-status', 'updateEmployementStatus')
                    ->name('updateEmployementStatus');

            });

            // PSA Verification Controller
            Route::controller(PsaVerificationController::class)->group(function () {
                Route::post('/employee/psa-verification/verify', '__invoke')->name('psa.verify');
            });

        });

        // Separated Employees

        Route::controller(SeparatedEmployeesController::class)->group(function () {
            Route::match(['get', 'post'], '/separated-employees', 'index')
                ->name('separated-employees.index');
        });


        // Daily Time Record Controller
        Route::prefix('dailytimerecord')->name('time.')->group(function () {
            Route::controller(DailyTimeRecordController::class)->group(function () {
                Route::match(['get', 'post'], '/dailytimerecord', 'index')
                    ->middleware('permission:dtr.view')
                    ->name('index');
                Route::get('/dailytimerecord/view', 'view')
                    ->middleware(['permission:dtr.view'])
                    ->name('view');
                Route::post('/dailytimerecord/updateTime', 'update')
                    ->name('update');

                // Print DTR
                Route::get('/dailytimerecord/printDtr', 'printDailyTimeRecord')
                    ->name('printDailyTimeRecord')
                    ->middleware(['permission:dtr.print']);

                // Official Events
                Route::post('/dailytimerecord/events', 'storeEvent')->name('events.store');
                Route::delete('/dailytimerecord/events/{id}', 'destroyEvent')->name('events.destroy');
                Route::delete('/dailytimerecord/documents/{id}', 'destroyDocument')->name('documents.destroy');

                Route::get('dailytimerecord/employee/schedules', 'employeeSchedules')
                    ->name('schedules.index');
            });

            Route::controller(EmployeeWorkShiftsController::class)->name('work-shifts.')->group(function () {
                Route::match(['get', 'post'], '/employee-work-shifts/index', 'index')->name('index');
                Route::match(['get', 'post'], '/employee-work-shifts/manage/{id}', 'manage')
                    ->middleware('signed')
                    ->name('manage');
                Route::post('/employee-work-shifts/assignEmployees', 'assignEmployees')->name('assign');
                Route::post('/employee-work-shifts/assign', 'assign')->name('assignPerEmployee');
                Route::post('/employee-work-shifts/remove', 'removeEmployees')->name('remove');
            });

            Route::controller(AttendanceLogEventTypesController::class)->name('attendanceLogEventTypes.')->group(function () {
                Route::get('attendance-log-event-types/index', 'index')->name('index');
                Route::get('attendance-log-event-types/create', 'create')->name('create');
                Route::post('attendance-log-event-types/store', 'store')->name('store');
                Route::get('attendance-log-event-types/edit/{id}', 'edit')
                    ->middleware('signed')
                    ->name('edit');
                Route::put('attendance-log-event-types/update/{id}', 'update')->name('update');
                Route::delete('attendance-log-event-types/delete/{id}', 'destroy')->name('destroy');
            });

        });

        Route::controller(PsaVerificationController::class)->name('psaVerification.')->group(function () {
            Route::post('/psa-verification/verify', '__invoke')->name('verify');
        });

        // Leave Controller
        Route::prefix('leaves')->name('leaves.')->group(function () {

            Route::controller(LeaveController::class)->group(function () {
                /* eto pala yung index viewing ng employee list ng leave entitlements */
                Route::match(['get', 'post'], '/leave', 'index')->name('leave-types.index');

                /* Leave List */
                Route::match(['get', 'post'], '/leave/leaveList', 'leaveList')->name('leaveList');

                /* Add Entitlement to Employee */
                Route::match(['get', 'post'], '/leave/addLeaveEntitlements', 'addLeaveEntitlements')->name('addLeaveEntitlements');
                Route::post('/leave/storeEmployeeLeaveEntitlements', 'storeEmployeeLeaveEntitlements')->name('storeEmployeeLeaveEntitlements');

                Route::get('/leave/viewLeaveEntitlements', 'viewLeaveEntitlements')->name('entitlements.index');

                /* Employee Entitlements */
                Route::get('/leave/viewEmployeeLeaveEntitlements/{id}', 'viewEmployeeLeaveEntitlements')
                    ->name('viewEmployeeLeaveEntitlements');

                Route::post('/leave/deductLeave', 'deductLeave')
                    ->name('deductLeave');

                Route::post('/leave/deductSpecialLeave', 'deductSpecialLeave')
                    ->name('deductSpecialLeave');

                Route::delete('/leave/deleteEmployeeLeaveEntitlements/{id}', 'deleteEmployeeLeaveEntitlements')
                    ->name('deleteEmployeeLeaveEntitlements');

                Route::get('/leaveType', 'leaveTypeIndex')->name('leaveType.index');
                Route::get('/leaveType/create', 'leaveTypeCreate')->name('leaveType.create');
                Route::post('/leaveType/store', 'leaveTypeStore')->name('leaveType.store');
                Route::get('/leaveType/edit/{id}', 'leaveTypeEdit')->name('leaveType.edit');
                Route::delete('/leaveType/{id}', 'leaveTypeDelete')->name('leaveType.delete');
                Route::post('/leaveType/deleteSelected', 'leaveTypeDeleteSelected')->name('leaveType.bulkDelete');

                Route::post('/leave/updateLeaveApplicationStatus', 'updateLeaveApplicationStatus')
                    ->name('updateLeaveApplicationStatus');

                Route::get('/leave/viewLeaveApplication/{id}', 'viewLeaveApplication')
                    ->middleware(['signed', 'permission:leave.view'])
                    ->name('viewLeaveApplication');

                Route::get('/leave/printLeaveApplication/{id}', 'printLeaveApplication')
                    ->middleware('signed')
                    ->name('printLeaveApplication');

                Route::match(['get', 'post'], '/leave/assignLeave', 'assignLeave')->name('assignLeave');
                Route::post('/leave/storeAssignLeave', 'storeAssignLeave')->name('storeAssignLeave');

                Route::get('/leave/leaveLedger/{id}', 'viewLeaveLedger')
                    // ->middleware(['signed', 'permission:leave.view'])
                    ->middleware(['permission:leave.view'])
                    ->name('viewEmployeeLeaveLedger');

                Route::get('/leave-ledger/{employee}/print', 'printLeaveLedger')
                    ->name('ledger.print');

                Route::get('/leave/policies', 'policies')
                    ->name('policies.index');
            });

            Route::controller(EmployeeLeaveSchedulerController::class)->name('scheduler.')->group(function () {
                Route::match(['get', 'post'], '/employee-leave-scheduler', 'index')->name('index');
                Route::match(['get', 'post'], '/employee-leave-scheduler/manage', 'manage')->name('manage');
                Route::post('/employee-leave-scheduler/store', 'store')->name('store');
                Route::post('/employee-leave-scheduler/remove', 'remove')->name('remove');
                Route::get('/employee-leave-scheduler/edit/{id}', 'edit')
                    ->middleware('signed')
                    ->name('edit');
                Route::put('/employee-leave-scheduler/update/{id}', 'update')->name('update');
                Route::delete('/employee-leave-scheduler/destroy/{id}', 'destroy')->name('destroy');
                Route::post('/employee-leave-scheduler/destroyAll', 'destroyAll')->name('destroyAll');
            });

            Route::controller(SpecialLeaveController::class)->name('specialLeave.')->group(function () {
                Route::get('/special-leave', 'index')->name('index');
                Route::get('/special-leave/create', 'create')->name('create');
                Route::post('/special-leave/store', 'store')->name('store');
                Route::get('/special-leave/edit/{id}', 'edit')
                    ->middleware('signed')
                    ->name('edit');

                Route::put('/special-leave/{id}', 'update')->name('update');
                Route::delete('/special-leave/{id}', 'destroy')->name('destroy');
                Route::post('/special-leave/bulkDestroy', 'bulkDestroy')->name('bulkDestroy');
            });
        });

        Route::controller(UniversityActivitiesController::class)->name('universityActivities.')->group(function () {
            Route::match(['get', 'post'], '/universityActivities', 'index')->name('index');
            Route::get('/universityActivities/create', 'create')->name('create');
            Route::post('/universityActivities/store', 'store')->name('store');
            Route::get('/universityActivities/edit/{id}', 'edit')
                ->middleware('signed')
                ->name('edit');
            Route::put('/universityActivities/{id}', 'update')->name('update');
            Route::delete('/universityActivities/{id}', 'destroy')->name('destroy');
        });

        Route::controller(HolidayController::class)->name('holidays.')->group(function () {
            Route::get('/holiday', 'index')->name('index');
            Route::get('/holiday/create', 'create')->name('create');
            Route::post('/holiday/store', 'store')->name('store');
            Route::get('/holiday/edit/{id}', 'edit')->name('edit');
            Route::put('/holiday/{id}', 'update')->name('update');
            Route::delete('/holiday/{id}', 'destroy')->name('destroy');
            Route::post('/holiday/bulkDestroy', 'bulkDestroy')->name('bulkDestroy');
        });
    });
});
