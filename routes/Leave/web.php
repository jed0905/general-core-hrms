<?php

use App\Http\Controllers\Web\Leave\LeavePolicyController;
use App\Http\Controllers\Web\Leave\LeavePolicyRuleController;
use App\Http\Controllers\Web\Leave\LeaveTypeController;
use Illuminate\Support\Facades\Route;


Route::middleware(['auth', 'verified'])->prefix('leave')->name('leave.config.')->group(function () {
    Route::prefix('leave/configuration')->middleware(['auth'])->group(function () {
        // Leave Types
        Route::prefix('types')->name('types.')->group(function () {
            Route::get('/', [LeaveTypeController::class, 'index'])->name('index')->middleware('can:leave_type.view');
            Route::post('/', [LeaveTypeController::class, 'store'])->name('store')->middleware('can:leave_type.create');
            Route::put('/{leaveType}', [LeaveTypeController::class, 'update'])->name('update')->middleware('can:leave_type.update');
            Route::delete('/{leaveType}', [LeaveTypeController::class, 'destroy'])->name('destroy')->middleware('can:leave_type.archive');
        });

        // Leave Policies
        Route::prefix('policies')->name('policies.')->group(function () {
            Route::get('/', [LeavePolicyController::class, 'index'])->name('index')->middleware('can:leave_policy.view');
            Route::post('/', [LeavePolicyController::class, 'store'])->name('store')->middleware('can:leave_policy.create');
            Route::put('/{leavePolicy}', [LeavePolicyController::class, 'update'])->name('update')->middleware('can:leave_policy.update');
            Route::delete('/{leavePolicy}', [LeavePolicyController::class, 'destroy'])->name('destroy')->middleware('can:leave_policy.archive');
        });

        // Leave Policy Rules
        Route::prefix('rules')->name('rules.')->group(function () {
            Route::get('/', [LeavePolicyRuleController::class, 'index'])->name('index')->middleware('can:leave_policy_rule.view');
            Route::post('/', [LeavePolicyRuleController::class, 'store'])->name('store')->middleware('can:leave_policy_rule.create');
            Route::put('/{leavePolicyRule}', [LeavePolicyRuleController::class, 'update'])->name('update')->middleware('can:leave_policy_rule.update');
            Route::delete('/{leavePolicyRule}', [LeavePolicyRuleController::class, 'destroy'])->name('destroy')->middleware('can:leave_policy_rule.delete');
        });
    });
});
