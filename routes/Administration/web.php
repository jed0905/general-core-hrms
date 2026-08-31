<?php

use App\Http\Controllers\Web\Administration\Organization\CorporateBrandingController;
use App\Http\Controllers\Web\Administration\Organization\DepartmentController;
use App\Http\Controllers\Web\Administration\Organization\LocationController;
use App\Http\Controllers\Web\Administration\Organization\OrganizationGeneralController;
use App\Http\Controllers\Web\Administration\OrganizationController;
use App\Http\Controllers\Web\Administration\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Organization Management Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('administration/organization')->name('administration.organization.')->group(function () {

        // --- 1. Main Hub View (Tabbed UI) ---
        Route::get('/', [OrganizationGeneralController::class, 'index'])
            ->name('index')
            ->middleware('can:organization.view');

        // --- 2. Organization General Information ---
        Route::prefix('general')->name('general.')->middleware('can:organization.view')->group(function () {
            Route::post('/', [OrganizationGeneralController::class, 'update'])->name('update');
        });

        // --- 3. Corporate Branding ---
        Route::prefix('branding')->name('branding.')->middleware('can:company.view')->group(function () {
            Route::post('/', [CorporateBrandingController::class, 'update'])->name('update');
        });

        // --- 4. Department Management ---
        Route::prefix('departments')->name('department.')->middleware('can:department.view')->group(function () {
            Route::get('/', [DepartmentController::class, 'index'])->name('index');
            Route::post('/', [DepartmentController::class, 'store'])->name('store');
            Route::put('/{department}', [DepartmentController::class, 'update'])->name('update');
            Route::delete('/{department}', [DepartmentController::class, 'destroy'])->name('destroy');
        });

        // --- 5. Location / Branch Management ---
        Route::prefix('locations')->name('location.')->middleware('can:organization.view')->group(function () {
            Route::get('/', [LocationController::class, 'index'])->name('index');
            Route::post('/', [LocationController::class, 'store'])->name('store');
            Route::put('/{location}', [LocationController::class, 'update'])->name('update');
            Route::delete('/{location}', [LocationController::class, 'destroy'])->name('destroy');
        });
    });

    /*
    |--------------------------------------------------------------------------
    | User Management Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('administration/users')->name('administration.user.')->group(function () {

        // View Users
        Route::middleware('can:user.view')->group(function () {
            Route::get('/', [UserManagementController::class, 'index'])->name('index');
            Route::get('/{user}', [UserManagementController::class, 'show'])->name('show');
        });

        // Create User
        Route::middleware('can:user.create')->group(function () {
            Route::get('/create', [UserManagementController::class, 'create'])->name('create');
            Route::post('/', [UserManagementController::class, 'store'])->name('store');
        });

        // Update User
        Route::middleware('can:user.update')->group(function () {
            Route::get('/{user}/edit', [UserManagementController::class, 'edit'])->name('edit');
            Route::put('/{user}', [UserManagementController::class, 'update'])->name('update');
        });

        // Deactivate User
        Route::patch('/{user}/deactivate', [UserManagementController::class, 'deactivate'])
            ->middleware('can:user.deactivate')
            ->name('deactivate');

        // Activate User
        Route::patch('/{user}/activate', [UserManagementController::class, 'activate'])
            ->middleware('can:user.activate')
            ->name('activate');

        // Reset Password
        Route::put('/{user}/reset-password', [UserManagementController::class, 'resetPassword'])
            ->middleware('can:user.reset_password')
            ->name('reset-password');

        // Assign Role
        Route::put('/{user}/assign-role', [UserManagementController::class, 'assignRole'])
            ->middleware('can:user.assign_role')
            ->name('assign-role');
    });
});

// Route::middleware(['auth', 'web', 'role:superadmin|hr_director|ict|campus_hr|campus_hr_staff'])->group(function () {

//     Route::prefix('administration')->name('administration.')->group(function () {

//         // User Management Routes
//         Route::controller(UserManagementController::class)->name('user.')->group(function () {
//             Route::match(['get', 'post'], '/user', 'index')
//                 ->middleware(['permission:user.view'])
//                 ->name('index');

//             Route::get('/user/create', 'create')
//                 ->middleware(['permission:user.create'])
//                 ->name('create');

//             Route::post('/user/store', 'store')
//                 ->middleware(['permission:user.create'])
//                 ->name('store');

//             Route::get('/user/edit/{id}', 'edit')
//                 ->middleware(['signed', 'permission:user.edit'])
//                 ->name('edit');

//             Route::put('/user/{id}', 'update')
//                 ->middleware(['permission:user.edit'])
//                 ->name('update');

//             Route::delete('/user/{id}', 'destroy')
//                 ->middleware(['permission:user.delete'])
//                 ->name('destroy');

//             Route::put('/user/reset-password/{id}', 'resetPassword')
//                 ->middleware(['permission:user.edit'])
//                 ->name('admin-reset-password');
//         });

//         // Organization Routes
//         Route::controller(OrganizationController::class)->name('organization.')->group(function () {
//             Route::get('/organization', 'index')
//                 ->middleware(['permission:organization.view'])
//                 ->name('index');

//             Route::get('/organization/department', 'index')
//                 ->name('department.index');

//             Route::get('/organization/create', 'create')
//                 ->middleware('permission:organization.manage')
//                 ->name('create');

//             // Store Operating Unit
//             Route::post('/organization/store/operating-unit', 'storeOperatingUnit')->name('storeOperatingUnit');
//             // Store Department
//             Route::post('/organization/store/department', 'storeDepartment')->name('storeDepartment');
//             // Store SubUnit
//             Route::post('/organization/store/sub-unit', 'storeSubUnit')->name('storeSubUnit');

//             // Update Operating Unit
//             Route::put('/organization/update/operating-unit/{id}', 'updateOperatingUnit')->name('updateOperatingUnit');
//             // Update Department
//             Route::put('/organization/update/department/{id}', 'updateDepartment')->name('updateDepartment');
//             // Update Sub Unit
//             Route::put('/organization/update/sub-unit/{id}', 'updateSubUnit')->name('updateSubUnit');

//             // Delete Department
//             Route::delete('/organization/department/{id}', 'deleteDepartment')->name('deleteDepartment');
//             // Delete Sub Unit
//             Route::delete('/organization/sub-unit/{id}', 'deleteSubUnit')->name('deleteSubUnit');

//             // Lazy loading endpoint
//             Route::get('/organization/load-departments', 'loadDepartments')->name('loadDepartments');

//             Route::put('/organization/reset-password/{id}', 'resetPassword')
//                 ->name('reset-password');

//             Route::get('organization/locations', 'locations')
//                 ->name('locations.index');
//         });
//     });

// });
