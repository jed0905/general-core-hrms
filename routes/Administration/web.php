<?php

use App\Http\Controllers\Web\Administration\OrganizationController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\Administration\UserManagementController;

// Route::middleware(['auth', ])


Route::middleware(['auth', 'web', 'role:superadmin|hr_director|ict|campus_hr|campus_hr_staff'])->group(function () {

    Route::prefix('administration')->name('administration.')->group(function () {

        // User Management Routes
        Route::controller(UserManagementController::class)->name('user.')->group(function () {
            Route::match(['get', 'post'], '/user', 'index')
                ->middleware(['permission:user.view'])
                ->name('index');

            Route::get('/user/create', 'create')
                ->middleware(['permission:user.create'])
                ->name('create');

            Route::post('/user/store', 'store')
                ->middleware(['permission:user.create'])
                ->name('store');

            Route::get('/user/edit/{id}', 'edit')
                ->middleware(['signed', 'permission:user.edit'])
                ->name('edit');

            Route::put('/user/{id}', 'update')
                ->middleware(['permission:user.edit'])
                ->name('update');

            Route::delete('/user/{id}', 'destroy')
                ->middleware(['permission:user.delete'])
                ->name('destroy');

            Route::put('/user/reset-password/{id}', 'resetPassword')
                ->middleware(['permission:user.edit'])
                ->name('admin-reset-password');
        });

        // Organization Routes
        Route::controller(OrganizationController::class)->name('organization.')->group(function () {
            Route::get('/organization', 'index')
                ->middleware(['permission:organization.view'])
                ->name('index');

            Route::get('/organization/department', 'index')
                ->name('department.index');

            Route::get('/organization/create', 'create')
                ->middleware('permission:organization.manage')
                ->name('create');

            // Store Operating Unit
            Route::post('/organization/store/operating-unit', 'storeOperatingUnit')->name('storeOperatingUnit');
            // Store Department
            Route::post('/organization/store/department', 'storeDepartment')->name('storeDepartment');
            // Store SubUnit
            Route::post('/organization/store/sub-unit', 'storeSubUnit')->name('storeSubUnit');

            // Update Operating Unit
            Route::put('/organization/update/operating-unit/{id}', 'updateOperatingUnit')->name('updateOperatingUnit');
            // Update Department
            Route::put('/organization/update/department/{id}', 'updateDepartment')->name('updateDepartment');
            // Update Sub Unit
            Route::put('/organization/update/sub-unit/{id}', 'updateSubUnit')->name('updateSubUnit');

            // Delete Department
            Route::delete('/organization/department/{id}', 'deleteDepartment')->name('deleteDepartment');
            // Delete Sub Unit
            Route::delete('/organization/sub-unit/{id}', 'deleteSubUnit')->name('deleteSubUnit');

            // Lazy loading endpoint
            Route::get('/organization/load-departments', 'loadDepartments')->name('loadDepartments');

            Route::put('/organization/reset-password/{id}', 'resetPassword')
                ->name('reset-password');

            Route::get('organization/locations', 'locations')
                ->name('locations.index');
        });
    });

});
