<?php

use App\Http\Controllers\Web\Leave\LeaveApplicationController;
use App\Http\Controllers\Web\Leave\LeaveApprovalController;
use App\Http\Controllers\Web\Leave\LeaveApprovalWorkflowController;
use App\Http\Controllers\Web\Leave\LeaveBalanceController;
use App\Http\Controllers\Web\Leave\LeavePolicyController;
use App\Http\Controllers\Web\Leave\LeavePolicyRuleController;
use App\Http\Controllers\Web\Leave\LeaveTypeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Leave Management
|--------------------------------------------------------------------------
|
| Every route is gated with `can:`. Collection and configuration routes check
| a capability permission. Routes on a single application check a policy
| ability (`can:view,leaveApplication`), which combines the permission with
| ownership, approver assignment and application state.
|
*/

Route::middleware(['auth', 'verified'])->prefix('leave')->group(function () {
    Route::prefix('configuration')->name('leave.config.')->group(function () {
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

        // Leave Approval Workflows (steps are saved with their workflow)
        Route::prefix('workflows')->name('workflows.')->group(function () {
            Route::get('/', [LeaveApprovalWorkflowController::class, 'index'])->name('index')->middleware('can:leave_approval_workflow.view');
            Route::post('/', [LeaveApprovalWorkflowController::class, 'store'])->name('store')->middleware('can:leave_approval_workflow.create');
            Route::put('/{leaveApprovalWorkflow}', [LeaveApprovalWorkflowController::class, 'update'])->name('update')->middleware('can:leave_approval_workflow.update');
            Route::delete('/{leaveApprovalWorkflow}', [LeaveApprovalWorkflowController::class, 'destroy'])->name('destroy')->middleware('can:leave_approval_workflow.archive');
        });
    });

    // Leave Balances & Entitlements
    Route::prefix('balances')->name('leave.balances.')->group(function () {
        Route::get('/', [LeaveBalanceController::class, 'index'])->name('index')->middleware('can:leave.balance.view');
        Route::post('/', [LeaveBalanceController::class, 'store'])->name('store')->middleware('can:leave.balance.create');
        Route::post('/adjust', [LeaveBalanceController::class, 'adjust'])->name('adjust')->middleware('can:leave.balance.adjust');
        Route::put('/{leaveBalance}', [LeaveBalanceController::class, 'update'])->name('update')->middleware('can:leave.balance.update');
    });

    // Employee Self-Service Leave Applications
    Route::prefix('applications')->name('leave.applications.')->group(function () {
        Route::get('/', [LeaveApplicationController::class, 'index'])->name('index')->middleware('can:leave.view_own');
        Route::post('/', [LeaveApplicationController::class, 'store'])->name('store')->middleware('can:leave.create');
        Route::get('/my-balances', [LeaveApplicationController::class, 'myBalances'])->name('my-balances')->middleware('can:leave.view_balance_own');
        Route::get('/history', [LeaveApplicationController::class, 'history'])->name('history')->middleware('can:leave.view_history_own');

        Route::prefix('{leaveApplication}')->group(function () {
            Route::get('/', [LeaveApplicationController::class, 'show'])->name('show')->middleware('can:view,leaveApplication');
            Route::put('/', [LeaveApplicationController::class, 'update'])->name('update')->middleware('can:update,leaveApplication');
            Route::post('/cancel', [LeaveApplicationController::class, 'cancel'])->name('cancel')->middleware('can:cancel,leaveApplication');
            Route::post('/comments', [LeaveApplicationController::class, 'storeComment'])->name('comments.store')->middleware('can:comment,leaveApplication');
            Route::get('/attachments/{attachment}', [LeaveApplicationController::class, 'downloadAttachment'])
                ->name('attachments.download')
                ->middleware('can:viewAttachments,leaveApplication')
                ->scopeBindings();
        });
    });

    // Leave Approvals: the permission is the capability; the form request's
    // policy check makes sure the user is the approver on the current step.
    Route::prefix('approvals')->name('leave.approvals.')->group(function () {
        Route::get('/', [LeaveApprovalController::class, 'index'])->name('index')->middleware('can:leave.approval.view');
        Route::post('/{leaveApplication}/approve', [LeaveApprovalController::class, 'approve'])->name('approve')->middleware('can:leave.approval.approve');
        Route::post('/{leaveApplication}/reject', [LeaveApprovalController::class, 'reject'])->name('reject')->middleware('can:leave.approval.reject');
        Route::post('/{leaveApplication}/return', [LeaveApprovalController::class, 'return'])->name('return')->middleware('can:leave.approval.return');
    });
});
