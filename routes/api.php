<?php

use App\Http\Controllers\Web\Administration\OrganizationController;
use App\Http\Controllers\Web\HrManagement\EmployeeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware(['auth:sanctum', 'role:api-consumer'])
    ->prefix('v1')
    ->group(function () {
        Route::get('/employee/{employee_number}', [EmployeeController::class, 'getEmployeeByEmployeeNumber']);
        Route::get('/employee/{email}', [EmployeeController::class, 'getEmployeeByEmail']);
        Route::get('/operating-units', [OrganizationController::class, 'getOperatingUnits']);
        Route::get('/departments', [OrganizationController::class, 'getDepartments']);
    });
