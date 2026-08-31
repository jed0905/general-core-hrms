<?php

use App\Http\Controllers\Web\Administration\Organization\CorporateBrandingController;
use App\Http\Controllers\Web\Administration\Organization\DepartmentController;
use App\Http\Controllers\Web\Administration\Organization\LocationController;
use App\Http\Controllers\Web\Administration\Organization\OrganizationGeneralController;
use App\Http\Controllers\Web\Administration\RolePermissionController;
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

    /*
    |--------------------------------------------------------------------------
    | Roles & Permissions Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('administration/roles')->name('administration.role.')->group(function () {

        // View Roles & Permissions Matrix
        Route::middleware('can:role.view')->group(function () {
            Route::get('/', [RolePermissionController::class, 'index'])->name('index');
            Route::get('/{role}', [RolePermissionController::class, 'show'])->name('show');
        });

        // Create Role
        Route::middleware('can:role.create')->group(function () {
            Route::post('/', [RolePermissionController::class, 'store'])->name('store');
        });

        // Update Role & Permissions Matrix
        Route::middleware('can:role.update')->group(function () {
            Route::put('/{role}', [RolePermissionController::class, 'update'])->name('update');
            Route::put('/{role}/permissions', [RolePermissionController::class, 'syncPermissions'])->name('sync-permissions');
        });

        // Delete Role
        Route::middleware('can:role.delete')->group(function () {
            Route::delete('/{role}', [RolePermissionController::class, 'destroy'])->name('destroy');
        });
    });
});
