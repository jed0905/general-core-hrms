<?php

namespace App\Mail;

use App\Models\Leave;
use App\Models\Employee;
use App\Models\Designation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use App\Services\LeaveService;
use App\Models\LeaveApplication;
use Barryvdh\Snappy\Facades\SnappyPdf;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Contracts\Queue\ShouldQueue;

class LeaveUpdate extends Mailable
{
    use Queueable, SerializesModels;

    public $employeeName;
    public $status;
    public $leaveType;
    public $leaveDates;
    public $days;
    public $employeeLeaveId;


    /**
     * Create a new message instance.
     */
    public function __construct($employeeName, $status, $leaveType, $leaveDates, $days, $employeeLeaveId)
    {
        $this->employeeName = $employeeName;
        $this->status = $status;
        $this->leaveType = $leaveType;
        $this->leaveDates = $leaveDates;
        $this->days = $days;
        $this->employeeLeaveId = $employeeLeaveId;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Leave Application Update (' . $this->leaveDates . ')',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.leave_update',
            with: [
                'employeeName' => $this->employeeName,
                'status' => $this->status,
                'leaveType' => $this->leaveType,
                'leaveDates' => $this->leaveDates,
                'days' => $this->days,
            ],
        );
    }

    public function build()
    {
        $mail = $this->subject(
            $this->status === 'approved'
            ? 'Leave Application Approved'
            : 'Leave Application Rejected'
        )
            ->view('emails.leave_update');

        // Attach PDF ONLY if approved
        if ($this->status === 'approved') {
            $pdfBinary = $this->generateLeavePDF($this->employeeLeaveId);

            $mail->attachData(
                $pdfBinary,
                'Leave-Form.pdf',
                ['mime' => 'application/pdf']
            );
        }

        return $mail;
    }

    public function generateLeavePDF($employeeLeaveId)
    {
        $leaveApplication = LeaveApplication::with(
            'employee',
            'employee.personalInformation',
            'employee.position.government_position',
            'employee.employeeDesignations',
            'employee.salaryStep',
            'employee.department',
            'leave',
            'employee.leaveCreditsHistory',
            'specialLeaveCredit.specialLeave'
        )
            ->where('id', $employeeLeaveId)
            ->first();

        // dd($leaveApplication);

        $isTeaching = $leaveApplication->employee->employee_type === 'Teaching';
        $isEmployeeWithDesignation = $leaveApplication->employee->employeeDesignations()->exists();
        $serviceCredit = Leave::where('name', 'Service Credits')->first();

        // Leave Application Type
        $leaveApplicationId = $leaveApplication->leave_id;

        $sickLeaveBalance = 0;
        $vacationLeaveBalance = 0;

        $employee = $leaveApplication->employee;
        $filingDate = $leaveApplication->created_at;

        // statuses that RESERVE balance
        $reservingStatuses = ['pending', 'recommended'];

        if ($isTeaching && !$isEmployeeWithDesignation) {

            /** VACATION LEAVE */
            if ($leaveApplicationId == 1) {
                // Use Service Credit
                $postedVL = (float) (
                    $employee->leaveCreditsHistory()
                        ->where('leave_id', $serviceCredit->id)
                        ->where('created_at', '<=', $filingDate)
                        ->latest()
                        ->value('balance') ?? 0
                );

                $pendingVL = $employee->leaveApplications()
                    ->where('leave_id', 1) // ONLY VL
                    ->whereIn('status', $reservingStatuses)
                    ->where('created_at', '<', $filingDate)
                    ->sum('no_of_days');
            } else {
                // Normal VL
                $postedVL = (float) (
                    $employee->leaveCreditsHistory()
                        ->where('leave_id', 1)
                        ->where('created_at', '<=', $filingDate)
                        ->latest()
                        ->value('balance') ?? 0
                );

                $pendingVL = $employee->leaveApplications()
                    ->whereIn('leave_id', [1, 2]) // VL + Forced Leave
                    ->whereIn('status', $reservingStatuses)
                    ->where('created_at', '<', $filingDate)
                    ->sum('no_of_days');
            }

            $vacationLeaveBalance = max(0, $postedVL - $pendingVL);


            /** SICK LEAVE */
            if ($leaveApplicationId == 3) {
                // Use Service Credit
                $postedSL = (float) (
                    $employee->leaveCreditsHistory()
                        ->where('leave_id', $serviceCredit->id)
                        ->where('created_at', '<=', $filingDate)
                        ->latest()
                        ->value('balance') ?? 0
                );
            } else {
                // Normal SL
                $postedSL = (float) (
                    $employee->leaveCreditsHistory()
                        ->where('leave_id', 3)
                        ->where('created_at', '<=', $filingDate)
                        ->latest()
                        ->value('balance') ?? 0
                );
            }

            $pendingSL = $employee->leaveApplications()
                ->where('leave_id', 3)
                ->whereIn('status', $reservingStatuses)
                ->where('created_at', '<', $filingDate)
                ->sum('no_of_days');

            $sickLeaveBalance = max(0, $postedSL - $pendingSL);
        } else {

            /** ======================
             *  VACATION LEAVE
             *  ====================== */
            $postedVL = (float) (
                $employee->leaveCreditsHistory()
                    ->where('leave_id', 1)
                    ->where('created_at', '<=', $filingDate)
                    ->latest()
                    ->value('balance') ?? 0
            );

            $pendingVL = $employee->leaveApplications()
                ->whereIn('leave_id', [1, 2])
                ->whereIn('status', $reservingStatuses)
                ->where('created_at', '<', $filingDate)
                ->sum('no_of_days');

            $vacationLeaveBalance = max(0, $postedVL - $pendingVL);


            /** ======================
             *  SICK LEAVE
             *  ====================== */
            $postedSL = (float) (
                $employee->leaveCreditsHistory()
                    ->where('leave_id', 3)
                    ->where('created_at', '<=', $filingDate)
                    ->latest()
                    ->value('balance') ?? 0
            );

            $pendingSL = $employee->leaveApplications()
                ->where('leave_id', 3)
                ->whereIn('status', $reservingStatuses)
                ->where('created_at', '<', $filingDate)
                ->sum('no_of_days');

            $sickLeaveBalance = max(0, $postedSL - $pendingSL);
        }


        $approvingPerson = '';
        $approvingPersonDesignation = '';
        $authorizedOfficial = '';
        $authorizedOfficialDesignation = '';

        /* HRMO Head */
        $hrmoHead = Designation::getHeadHrmo($leaveApplication->employee->operating_unit_id);
        $hrmoName = $hrmoHead ? $hrmoHead->personalInformation->getFullNameWithMiddleInitialAttribute() : '';

        /* Head of Operating Unit */
        $headOperatingUnit = Designation::getHeadOperatingUnit($leaveApplication->employee->operating_unit_id);
        $headOperatingUnitName = $headOperatingUnit ? $headOperatingUnit['employee']->personalInformation->getFullNameWithMiddleInitialAttribute() : 'N/A';
        $headOperatingUnitDesignation = $headOperatingUnit ? $headOperatingUnit['designation'] : '';

        /* President */
        $president = Designation::getUniversityPresident();
        $presidentName = $president ? $president->personalInformation->getFullNameWithMiddleInitialAttribute() : '';

        // If employee is teaching personnel and regular faculty, supervisor will be the dean (next higher supervisor)
        if ($isTeaching && (!$employee->isDean() && !$employee->isDepartmentChairperson())) {

            $immediate_supervisor = Employee::where('id', $leaveApplication->employee->higher_supervisor_id)->first();
            if ($immediate_supervisor) {
                $immediate_supervisor_name = $immediate_supervisor->personalInformation->getFullNameWithMiddleInitialAttribute();
            } else {
                $immediate_supervisor_name = '';
            }
        } else if ($isTeaching && $employee->isDean()) {
            // If employee is dean, supervisor will be the head of operating unit
            $immediate_supervisor_name = $headOperatingUnitName;
        } else if ($isTeaching && $employee->isDepartmentChairperson()) {
            // If employee is department chairperson, supervisor will be the dean (next higher supervisor)
            $dean = Employee::where('id', $leaveApplication->employee->immediate_supervisor_id)->first();
            if ($dean) {
                $immediate_supervisor_name = $dean->personalInformation->getFullNameWithMiddleInitialAttribute();
            } else {
                $immediate_supervisor_name = '';
            }
        } else {
            $immediate_supervisor = Employee::where('id', $leaveApplication->employee->immediate_supervisor_id)->first();
            if ($immediate_supervisor) {
                $immediate_supervisor_name = $immediate_supervisor->personalInformation->getFullNameWithMiddleInitialAttribute();
            } else {
                $immediate_supervisor_name = '';
            }
        }

        if ($leaveApplication->no_of_days >= 30) {
            $approvingPerson = $headOperatingUnitName;
            $approvingPersonDesignation = $headOperatingUnitDesignation;
            $authorizedOfficial = $presidentName;
            $authorizedOfficialDesignation = 'University President';
        } else {
            $approvingPerson = $immediate_supervisor_name;
            $approvingPersonDesignation = 'Immediate Supervisor';
            $authorizedOfficial = $headOperatingUnitName;
            $authorizedOfficialDesignation = $headOperatingUnitDesignation;
        }

        $daysWithPay = 0;
        $daysWithoutPay = 0;
        $annotation = '';

        // Compute days with/without pay
        if ($leaveApplication->leave_id != 13) {
            if ($isTeaching && !$isEmployeeWithDesignation && in_array($leaveApplication->leave->name, ['Sick Leave', 'Vacation Leave'])) {
                // Get Service Credits for Teaching Personnel without Designation
                $leaveBalanceOnLeaveType = $leaveApplication->employee
                    ->leaveCreditsHistory()
                    ->where('leave_id', $serviceCredit->id)
                    ->where('created_at', '<=', $leaveApplication->created_at)
                    ->latest()
                    ->pluck('balance')
                    ->first() ?? 0;
            } else {
                // Regular leave balance retrieval
                $leaveBalanceOnLeaveType = $leaveApplication->employee
                    ->leaveCreditsHistory()
                    ->where('leave_id', $leaveApplication->leave_id)
                    ->where('created_at', '<=', $leaveApplication->created_at)
                    ->latest()
                    ->pluck('balance')
                    ->first() ?? 0;
            }

            if ($leaveBalanceOnLeaveType == 0) {
                $daysWithoutPay = $leaveApplication->credits;
            } elseif ($leaveBalanceOnLeaveType < $leaveApplication->credits) {
                $daysWithPay = $leaveBalanceOnLeaveType;
                $daysWithoutPay = $leaveApplication->credits - $leaveBalanceOnLeaveType;
            } else {
                $daysWithPay = $leaveApplication->credits;
                $daysWithoutPay = 0;
            }

            // ✅ Annotation for other leaves (not Sick, Vacation, or Mandatory/Forced)
            $excludedLeaves = ['Sick Leave', 'Vacation Leave', 'Mandatory/Forced Leave'];

            if (!in_array($leaveApplication->leave->name, $excludedLeaves)) {
                // Get shortcut (replace SPL with MC6)
                $shortcut = strtoupper($leaveApplication->leave->shortcut ?? 'LEAVE');
                if ($shortcut === 'SPL') {
                    $shortcut = 'SPL (MC6)';
                }

                $annotation = sprintf(
                    '%s CREDITS %.2f, AVAILED %.2f, BALANCE %.2f',
                    $shortcut,
                    $leaveBalanceOnLeaveType,
                    $leaveApplication->credits,
                    max(0, $leaveBalanceOnLeaveType - $leaveApplication->credits)
                );

            }
        } else {

            $employee = $leaveApplication->employee;
            $filingDate = $leaveApplication->created_at;

            // POSTED balance (approved only)
            $postedBalance = (float) (
                $leaveApplication->specialLeaveCredit->balance ?? 0
            );

            // PENDING reservations
            $pendingUsed = $employee->leaveApplications()
                ->where('leave_id', $leaveApplication->leave_id)
                ->whereIn('status', ['pending', 'recommended'])
                ->where('created_at', '<', $filingDate)
                ->sum('credits');

            // EFFECTIVE balance at date of filing
            $leaveBalanceOnLeaveType = max(0, $postedBalance - $pendingUsed);

            // TOTAL earned (CSC "EARNED")
            $earnedCredits = (float) (
                $leaveApplication->specialLeaveCredit->balance ?? 0
            );

            // COMPUTE paid vs unpaid
            if ($leaveBalanceOnLeaveType <= 0) {
                $daysWithPay = 0;
                $daysWithoutPay = $leaveApplication->credits;
            } elseif ($leaveBalanceOnLeaveType < $leaveApplication->credits) {
                $daysWithPay = $leaveBalanceOnLeaveType;
                $daysWithoutPay = $leaveApplication->credits - $leaveBalanceOnLeaveType;
            } else {
                $daysWithPay = $leaveApplication->credits;
                $daysWithoutPay = 0;
            }

            // REMAINING balance AFTER this application
            $remainingBalance = max(0, $leaveBalanceOnLeaveType - $daysWithPay);

            // ✅ CORRECT CSC ANNOTATION
            $annotation = sprintf(
                '%s EARNED %.2f, AVAILED %.2f, BALANCE %.2f',
                strtoupper(
                    $leaveApplication->specialLeaveCredit->specialLeave->shortcut
                    ?? $leaveApplication->leave->name
                    ?? 'LEAVE'
                ),
                $earnedCredits,
                $daysWithPay,
                $remainingBalance
            );
        }

        $pdf = SnappyPdf::loadView('templates/leave/leave', [
            'leaveApplication' => $leaveApplication,
            'sickLeaveBalance' => $sickLeaveBalance,
            'vacationLeaveBalance' => $vacationLeaveBalance,
            'hrmoName' => $hrmoName,
            'immediate_supervisor_name' => $immediate_supervisor_name,
            // 'headOperatingUnitName' => $headOperatingUnitName,
            // 'headOperatingUnitDesignation' => $headOperatingUnitDesignation,
            'approvingPerson' => $approvingPerson,
            'approvingPersonDesignation' => $approvingPersonDesignation,
            'authorizedOfficial' => $authorizedOfficial,
            'authorizedOfficialDesignation' => $authorizedOfficialDesignation,
            'daysWithPay' => $daysWithPay,
            'daysWithoutPay' => $daysWithoutPay,
            'annotationForOthers' => $annotation
        ])
            ->setPaper('a4')
            ->setOption('margin-top', '0.3in')
            ->setOption('margin-bottom', '0.3in')
            ->setOption('margin-left', '0.3in')
            ->setOption('margin-right', '0.3in')
            ->setOption('zoom', 0.998)
            ->setOption('enable-local-file-access', true);

        return $pdf->output();

    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
