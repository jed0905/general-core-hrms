<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\DB;
use App\Models\EmployeeLeaveCreditsHistory;

class RestoreLeaveCreditService
{
    public function restore($leaveApplication)
    {
        // =========================
        // SAFETY CHECK (EARLY EXIT)
        // =========================
        if ($leaveApplication->is_restored) {
            return;
        }

        DB::transaction(function () use ($leaveApplication) {

            // 🔒 Re-fetch with lock to avoid double restore race condition
            $leaveApplication = $leaveApplication->fresh(['employee', 'specialLeaveCredit']);

            if ($leaveApplication->is_restored) {
                return;
            }

            $employee = $leaveApplication->employee;
            $employeeId = $employee->id;
            $leaveId = $leaveApplication->leave_id;
            $credits = $this->getActualDeductedCredits($leaveApplication);

            $isTeaching = $employee->employee_type === 'Teaching';
            $hasDesignation = $employee->employeeDesignations()->exists();

            $serviceCreditId = optional(
                \App\Models\Leave::where('name', 'Service Credits')->first()
            )->id;

            switch ($leaveId) {

                case 1: // VL
                    // ❌ DO NOT restore VL/SL for teaching without designation
                    if ($isTeaching && !$hasDesignation && $serviceCreditId) {
                        $this->restoreBalance($employeeId, $serviceCreditId, $credits, $leaveApplication);
                    } else {
                        // normal employees still restore VL/SL
                        $this->restoreBalance($employeeId, $leaveId, $credits, $leaveApplication);
                    }

                    break;


                case 2: // Forced Leave
                    $this->restoreBalance($employeeId, 1, $credits, $leaveApplication);
                    $this->restoreBalance($employeeId, 2, $credits, $leaveApplication);

                    if ($isTeaching && !$hasDesignation && $serviceCreditId) {
                        $this->restoreBalance($employeeId, $serviceCreditId, $credits, $leaveApplication);
                    }
                    break;

                case 3: // SL
                    // ❌ DO NOT restore VL/SL for teaching without designation
                    if ($isTeaching && !$hasDesignation && $serviceCreditId) {
                        $this->restoreBalance($employeeId, $serviceCreditId, $credits, $leaveApplication);
                    } else {
                        // normal employees still restore VL/SL
                        $this->restoreBalance($employeeId, $leaveId, $credits, $leaveApplication);
                    }

                    break;


                case 4:
                case 5:
                case 6:
                case 7:
                case 8:
                case 9:
                case 10:
                case 11:
                case 12:
                    $this->restoreBalance($employeeId, $leaveId, $credits, $leaveApplication);
                    break;

                case 13:
                    $this->restoreSpecialLeave($leaveApplication, $credits);
                    break;

                default:
                    throw new Exception("Unsupported leave type: {$leaveId}");
            }

            // =========================
            // MARK AS RESTORED (IMPORTANT)
            // =========================
            $leaveApplication->update([
                'is_restored' => true,
            ]);
        });
    }

    /**
     * Restore standard leave balance
     */
    private function restoreBalance($employeeId, $leaveId, $credits, $leaveApplication = null)
    {
        $latest = EmployeeLeaveCreditsHistory::where('employee_id', $employeeId)
            ->where('leave_id', $leaveId)
            ->latest()
            ->first();

        if (!$latest) {
            throw new Exception("No leave credit history found for leave_id {$leaveId}");
        }

        EmployeeLeaveCreditsHistory::create([
            'employee_id' => $employeeId,
            'leave_id' => $leaveId,
            'total_earned' => $latest->balance,
            'credit_addition' => $credits,
            'credit_deduction' => 0,
            'balance' => $latest->balance + $credits,
            'credit_origin' => EmployeeLeaveCreditsHistory::ORIGIN_LEAVE_APPLICATION,
            'remarks' => $leaveApplication ? "Restored for leave application (Leave ID: {$leaveApplication->id})" : null,
        ]);
    }

    /**
     * Restore special leave (COC / Others)
     */
    private function restoreSpecialLeave($leaveApplication, $credits)
    {
        $special = $leaveApplication->specialLeaveCredit;

        if (!$special) {
            throw new Exception('Missing special leave reference.');
        }

        $latest = EmployeeLeaveCreditsHistory::where('employee_id', $leaveApplication->employee_id)
            ->where('document_type_number', $special->document_type_number)
            ->latest()
            ->first();

        if (!$latest) {
            throw new Exception('No special leave credit history found.');
        }

        EmployeeLeaveCreditsHistory::create([
            'employee_id' => $leaveApplication->employee_id,
            'leave_id' => $leaveApplication->leave_id,
            'total_earned' => $latest->balance,
            'credit_addition' => $credits,
            'credit_deduction' => 0,
            'balance' => $latest->balance + $credits,
            'special_leave_id' => $latest->special_leave_id,
            'document_type_number' => $latest->document_type_number,
            'expiration_date_from' => $latest->expiration_date_from,
            'expiration_date_to' => $latest->expiration_date_to,
            'credit_origin' => EmployeeLeaveCreditsHistory::ORIGIN_LEAVE_APPLICATION,
            'remarks' => $leaveApplication ? "Restored for special leave application (Leave ID: {$leaveApplication->id})" : null,
        ]);
    }

    private function getActualDeductedCredits($leaveApplication)
    {
        $employee = $leaveApplication->employee;
        $employeeId = $employee->id;
        $leaveId = $leaveApplication->leave_id;
        $filingTime = $leaveApplication->created_at;

        $isTeaching = $employee->employee_type === 'Teaching';
        $hasDesignation = $employee->employeeDesignations()->exists();

        $serviceCreditId = optional(
            \App\Models\Leave::where('name', 'Service Credits')->first()
        )->id;

        // =========================
        // 🔥 DETERMINE SOURCE OF DEDUCTION
        // =========================

        // SPECIAL LEAVE (CTO / WELLNESS)
        if ($leaveId == 13) {
            $docNumber = optional($leaveApplication->specialLeaveCredit)->document_type_number;

            if (!$docNumber) return 0;

            return $this->computeDelta(
                $employeeId,
                'document_type_number',
                $docNumber,
                $filingTime
            );
        }

        // VL / SL for Teaching without designation → SERVICE CREDIT
        if (in_array($leaveId, [1, 3]) && $isTeaching && !$hasDesignation && $serviceCreditId) {
            return $this->computeDelta(
                $employeeId,
                'leave_id',
                $serviceCreditId,
                $filingTime
            );
        }

        // NORMAL CASE
        return $this->computeDelta(
            $employeeId,
            'leave_id',
            $leaveId,
            $filingTime
        );
    }

    private function computeDelta($employeeId, $column, $value, $filingTime)
    {
        $before = (float) EmployeeLeaveCreditsHistory::where('employee_id', $employeeId)
            ->where($column, $value)
            ->where('created_at', '<', $filingTime)
            ->latest()
            ->value('balance') ?? 0;

        $after = (float) EmployeeLeaveCreditsHistory::where('employee_id', $employeeId)
            ->where($column, $value)
            ->where('created_at', '>=', $filingTime)
            ->oldest()
            ->value('balance') ?? $before;

        return max(0, $before - $after);
    }
}
