<?php

namespace App\Services;

use App\Models\Designation;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\LeaveApplication;
use Barryvdh\Snappy\Facades\SnappyPdf;
use Carbon\Carbon;

class LeavePdfService
{
    public function printMyLeaveApplication(string $id)
    {
        $leaveApplication = LeaveApplication::with(
            'currentStatus',
            'employee',
            'employee.personalInformation',
            'employee.position.government_position',
            'employee.employeeDesignations',
            'employee.salaryStep.salaryMatrix',
            'employee.department',
            'leave',
            'leaveDates',
            'employee.leaveCreditsHistory',
            'specialLeaveCredit.specialLeave',
            'leaveStatuses'
        )
            ->where('id', $id)
            ->first();

        // Personal and Work Information
        $personalAndWorkInfo = $this->getPersonalAndWorkInformation($leaveApplication);

        // Application Date
        $dateOfFiling = Carbon::parse($leaveApplication->created_at)->format('F j, Y');

        // 6A Type of Leave To Be Availed Of
        $typeOfLeaveToBeAvailedOf = $this->getTypeOfLeaveToBeAvailedOf($leaveApplication);

        // 6B Details of Leave
        $detailsOfLeave = $this->getDetailsOfLeave($leaveApplication);

        // 6C Leave Dates
        $leaveDatesInfo = $this->getLeaveDates($leaveApplication);

        // 6D Commutation (not implemented, just pass the value)
        $commutation = $leaveApplication->commutation ?? '';
        $employeeEsignaturePath = $leaveApplication->employee?->personalInformation?->e_signature_path ?? '';
        $applicationTimestamp = $leaveApplication->created_at ? Carbon::parse($leaveApplication->created_at)->format('F j, Y, g:i A') : '';

        // 7A Certification of Leave Credits
        $certificationOfLeaveCredits = $this->getCertificationOfLeaveCredits($leaveApplication);
        $hrmoHeadOfOffice = $this->getHRMOHeadOfOffice($leaveApplication);
        $certificationTimestamp = $this->getCertificationTimestamp($leaveApplication);

        // 7B Recommendation
        $recommendation = $this->getRecommendationStatus($leaveApplication);
        $recommendationSignatoryName = $this->getRecommendationSignatory($leaveApplication, $leaveDatesInfo)['name'];
        $recommendationSignatoryEsignaturePath = $this->getRecommendationSignatory($leaveApplication, $leaveDatesInfo)['signaturePath'];
        $recommendationSignatoryDesignation = $this->getRecommendationSignatory($leaveApplication, $leaveDatesInfo)['designation'] ?? '';
        $recommendationTimestamp = $this->getRecommendationTimestamp($leaveApplication);

        // 7C Approved Days with/without pay
        $daysWithWithoutPay = $this->getDaysWithWithoutPay($leaveApplication);

        // 7D Disapproval Reason
        $disapprovalReason = $this->getDisapprovalReason($leaveApplication);

        // Leave Approver and Authorized Official
        $leaveApprover = $this->getLeaveApprover($leaveApplication, $leaveDatesInfo);
        $approvalTimestamp = $this->getApprovalTimestamp($leaveApplication);

        $pdf = SnappyPdf::loadView('templates/leave/leave', [
            // Numbers 1 to 5
            'officeOrDepartment' => $leaveApplication->employee->department->name ?? '',
            'employeeLastName' => $personalAndWorkInfo['employeeLastName'] ?? '',
            'employeeFirstName' => $personalAndWorkInfo['employeeFirstName'] ?? '',
            'employeeMiddleName' => $personalAndWorkInfo['employeeMiddleName'] ?? '',
            'dateOfFiling' => $dateOfFiling ?? '',
            'employeePosition' => $personalAndWorkInfo['employeePosition'] ?? '',
            'employeeSalary' => $personalAndWorkInfo['employeeSalary'] ?? '',

            // 6A Type of Leave
            'typeOfLeaveToBeAvailedOfId' => $typeOfLeaveToBeAvailedOf['leaveId'] ?? '',
            'specialLeaveName' => $typeOfLeaveToBeAvailedOf['specialLeaveName'] ?? '',

            // 6B Details of Leave
            'locationWithinPhilippines' => $detailsOfLeave['locationWithinPhilippines'] ?? '',
            'locationAbroad' => $detailsOfLeave['locationAbroad'] ?? '',
            'inHospital' => $detailsOfLeave['inHospital'] ?? '',
            'out_hospital' => $detailsOfLeave['out_hospital'] ?? '',
            'illness' => $detailsOfLeave['illness'] ?? '',
            'studyLeaveApplication' => $detailsOfLeave['studyLeaveApplication'] ?? '',

            // 6C Number of Working Days Applied For
            'inclusiveLeaveDates' => $leaveDatesInfo['leaveDatesFormatted'] ?? '',
            'numberOfDays' => $leaveDatesInfo['numberOfDays'] ?? '',

            // 6D Commutation
            'commutation' => $commutation ?? '',
            'employeeEsignaturePath' => $employeeEsignaturePath ?? '',
            'applicationTimestamp' => $applicationTimestamp ?? '',

            // 7A Certification of Leave Credits
            'leaveAsOf' => $certificationOfLeaveCredits['leaveAsOf'] ?? '',
            'totalEarnedVL' => $certificationOfLeaveCredits['totalEarnedVL'] ?? 0,
            'totalEarnedSL' => $certificationOfLeaveCredits['totalEarnedSL'] ?? 0,
            'balanceVL' => $certificationOfLeaveCredits['balanceVL'] ?? 0,
            'balanceSL' => $certificationOfLeaveCredits['balanceSL'] ?? 0,
            'annotation' => $certificationOfLeaveCredits['annotation'] ?? '',
            'hrmoHeadName' => $hrmoHeadOfOffice['name'] ?? '',
            'hrmoSignaturePath' => $hrmoHeadOfOffice['signaturePath'] ?? '',
            'certificationTimestamp' => $certificationTimestamp ?? '',

            // 7B Recommendation
            'isLeaveForApproval' => $recommendation['isLeaveForApproval'] ?? false,
            'isLeaveForDisapproval' => $recommendation['isLeaveForDisapproval'] ?? false,
            'remarksForDisapproval' => $recommendation['remarksForDisapproval'] ?? '',
            'recommendationSignatoryName' => $recommendationSignatoryName ?? '',
            'recommendationSignatoryDesignation' => $recommendationSignatoryDesignation ?? '',
            'recommendationSignatoryEsignaturePath' => $recommendationSignatoryEsignaturePath ?? '',
            'recommendationTimestamp' => $recommendationTimestamp ?? '',

            // 7C Approved Days with/without pay
            'daysWithPay' => $daysWithWithoutPay['daysWithPay'] ?? 0,
            'daysWithoutPay' => $daysWithWithoutPay['daysWithoutPay'] ?? 0,

            // 7D Disapproval Reason
            'disapprovalReason' => $disapprovalReason ?? '',

            // Leave Approver and Authorized Official
            'leaveApproverName' => $leaveApprover['name'] ?? '',
            'leaveApproverDesignation' => $leaveApprover['designation'] ?? '',
            'leaveApproverSignaturePath' => $leaveApprover['signaturePath'] ?? '',
            'approvalTimestamp' => $approvalTimestamp ?? '',

        ])
            ->setPaper('a4')
            ->setOption('margin-top', '0.3in')
            ->setOption('margin-bottom', '0.3in')
            ->setOption('margin-left', '0.3in')
            ->setOption('margin-right', '0.3in')
            ->setOption('zoom', 0.998)
            ->setOption('enable-local-file-access', true);

        return $pdf;
    }

    private function getPersonalAndWorkInformation($leaveApplication)
    {
        $employee = $leaveApplication->employee;
        $personalInfo = $employee->personalInformation;
        $position = $employee->position?->government_position;
        $department = $employee->department;

        return [
            'officeOrDepartment' => $department->name ?? '',
            'employeeLastName' => $personalInfo->lastname ?? '',
            'employeeFirstName' => $personalInfo->firstname ?? '',
            'employeeMiddleName' => $personalInfo->middlename ?? '',
            'employeePosition' => $position->name ?? '',
            'employeeSalary' => $employee?->getSalaryAttribute() ? number_format($employee->getSalaryAttribute(), 2, '.', ',') : '',
        ];
    }

    private function getTypeOfLeaveToBeAvailedOf($leaveApplication)
    {
        $specialLeaveName = '';

        if ($leaveApplication->leave_id == 13) {
            $name = $leaveApplication->specialLeaveCredit->specialLeave->name;
            $shortcut = $leaveApplication->specialLeaveCredit->specialLeave->shortcut;

            // 🔁 Replace specific name
            if (strtolower($name) === 'compensatory overtime credit') {
                $specialLeaveName = strtoupper('Compensatory Time Off (CTO)');
            } else {
                $specialLeaveName = $name . ' (' . $shortcut . ')';
            }
        }

        return [
            'leaveId' => $leaveApplication->leave_id,
            'specialLeaveName' => $specialLeaveName,
        ];
    }

    private function getDetailsOfLeave($leaveApplication)
    {
        return [
            'locationWithinPhilippines' => $leaveApplication->location_within_philippines ?? '',
            'locationAbroad' => $leaveApplication->location_abroad ?? '',
            'inHospital' => $leaveApplication->in_hospital ?? '',
            'out_hospital' => $leaveApplication->out_hospital ?? '',
            'illness' => $leaveApplication->illness ?? '',
            'studyLeaveApplication' => $leaveApplication->study_leave_application ?? '',
            'otherPurpose' => $leaveApplication->other_purposes ?? '',
        ];
    }

    private function getLeaveDates($leaveApplication)
    {
        $numberOfDays = $leaveApplication->leaveDates->sum('credits');

        // Keep BOTH date + duration
        $leaveDates = $leaveApplication->leaveDates
            ->map(function ($d) {
                return [
                    'date' => Carbon::parse($d->date),
                    'duration' => $d->duration, // full_day | half_day_am | half_day_pm
                ];
            })
            ->sortBy(fn($d) => $d['date'])
            ->values();

        // Group by Month-Year
        $datesByMonthYear = $leaveDates->groupBy(function ($d) {
            return $d['date']->format('F Y');
        });

        $formattedDates = $datesByMonthYear->map(function ($dates) {

            $month = $dates->first()['date']->format('F');
            $year = $dates->first()['date']->year;

            $ranges = [];
            $start = $dates[0];
            $prev = $dates[0];

            for ($i = 1; $i < count($dates); $i++) {
                $current = $dates[$i];

                $isConsecutive =
                    $current['date']->diffInDays($prev['date']) === 1 &&
                    $current['duration'] === 'full_day' &&
                    $prev['duration'] === 'full_day';

                // Only group if BOTH are full_day and consecutive
                if (!$isConsecutive) {
                    $ranges[] = $this->formatLeaveRange($start, $prev);
                    $start = $current;
                }

                $prev = $current;
            }

            // last range
            $ranges[] = $this->formatLeaveRange($start, $prev);

            return $month . ' ' . implode(', ', $ranges) . ', ' . $year;
        });

        return [
            'numberOfDays' => $numberOfDays,
            'leaveDatesFormatted' => $formattedDates->implode(', '),
        ];
    }

    private function formatLeaveRange($start, $end)
    {
        $startDay = $start['date']->day;
        $endDay = $end['date']->day;

        // Helper for AM/PM label
        $formatDay = function ($item) {
            if ($item['duration'] === 'half_day_am') {
                return $item['date']->day . ' (AM)';
            } elseif ($item['duration'] === 'half_day_pm') {
                return $item['date']->day . ' (PM)';
            }
            return $item['date']->day;
        };

        // Single day
        if ($startDay === $endDay) {
            return $formatDay($start);
        }

        // Range (only full days reach here)
        return "$startDay - $endDay";
    }

    private function getCertificationOfLeaveCredits($leaveApplication)
    {
        $employee = $leaveApplication->employee;
        $filingDate = $leaveApplication->created_at;
        $leaveId = $leaveApplication->leave_id;

        $isTeaching = $employee->employee_type === 'Teaching';
        $hasDesignation = $employee->employeeDesignations()->exists();

        $hasVslDesignation = $employee->employeeDesignations()
            ->whereHas('designation', function ($query) {
                $query->where('is_vsl', 1);
            })
            ->whereDate('assumption_date', '<=', $filingDate)
            ->where(function ($query) use ($filingDate) {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $filingDate);
            })
            ->exists();

        $serviceCredit = Leave::where('name', 'Service Credits')->first();

        // 🔥 Normalize requested days
        $rawDays = $this->getLeaveDates($leaveApplication)['numberOfDays'];

        // =========================
        // HANDLE SPECIAL LEAVE (ID = 13)
        // =========================
        $specialName = strtolower(
            $leaveApplication->specialLeaveCredit->specialLeave->name ?? ''
        );

        $isCTO = $specialName === 'compensatory overtime credit';

        $numberOfDays = $isCTO
            ? (float) $rawDays
            : (int) ceil($rawDays);

        // "As of" date
        $latestDate = $this->getLatestLeaveReferenceDate($leaveApplication, $filingDate);
        $leaveAsOf = \Carbon\Carbon::parse($latestDate)->format('F j, Y');

        // =========================
        // GET BASE BALANCES FIRST (ALWAYS)
        // =========================
        if ($isTeaching && !$hasVslDesignation) {

            $service = $this->getBalanceBefore($employee, $serviceCredit->id, $filingDate);

            if ($leaveId == 1) {
                // ✅ Filing VL
                $vl = $service;
                $sl = 0;
            } elseif ($leaveId == 3) {
                // ✅ Filing SL
                $vl = 0;
                $sl = $service;
            } else {
                // ✅ All other leaves (including 13)
                $vl = $service;
                $sl = 0;
            }
        } else {
            $vl = $this->getBalanceBefore($employee, 1, $filingDate);
            $sl = $this->getBalanceBefore($employee, 3, $filingDate);
        }

        // Default values
        $totalEarnedVL = $vl;
        $totalEarnedSL = $sl;
        $balanceVL = $vl;
        $balanceSL = $sl;
        $annotation = '';

        // =========================
        // APPLY DEDUCTIONS BASED ON LEAVE TYPE
        // =========================

        if ($leaveId == 1) { // VL
            $totalEarnedVL = $vl;
            $balanceVL = max(0, $vl - $numberOfDays);
        } elseif ($leaveId == 2) { // Forced Leave
            $totalEarnedVL = $vl;
            $balanceVL = max(0, $vl - $numberOfDays);
        } elseif ($leaveId == 3) { // SL
            $totalEarnedSL = $sl;
            $balanceSL = max(0, $sl - $numberOfDays);
        } elseif ($leaveId == 13) { // 🔥 SPECIAL LEAVE

            $credits = $this->getSpecialBalanceBefore($leaveApplication, $filingDate);

            $annotation = $this->buildAnnotation(
                $leaveApplication,
                $credits,
                $numberOfDays
            );

            // ✅ DO NOT TOUCH VL/SL — keep them as-is

        } else {
            // Other leave types → no VL/SL deduction
            $other = $this->getBalanceBefore($employee, $leaveId, $filingDate);

            $annotation = $this->buildAnnotation(
                $leaveApplication,
                $other,
                $numberOfDays
            );
        }

        return [
            'leaveAsOf' => $leaveAsOf,
            'totalEarnedVL' => max(0, $totalEarnedVL),
            'totalEarnedSL' => max(0, $totalEarnedSL),
            'balanceVL' => max(0, $balanceVL),
            'balanceSL' => max(0, $balanceSL),
            'annotation' => $annotation,
        ];
    }
    private function getSpecialBalanceBefore($leaveApplication, $date)
    {
        $docNumber = $leaveApplication->specialLeaveCredit->document_type_number;

        return (float) (
            $leaveApplication->employee
            ->leaveCreditsHistory()
            ->where('document_type_number', $docNumber)
            ->where('created_at', '<', $date)
            ->latest('created_at')
            ->value('balance') ?? 0
        );
    }

    private function getBalanceBefore($employee, $leaveId, $date)
    {
        return (float) (
            $employee->leaveCreditsHistory()
            ->where('leave_id', $leaveId)
            ->where('created_at', '<', $date)
            ->latest('created_at')
            ->value('balance') ?? 0
        );
    }

    private function buildAnnotation($leaveApplication, $credits, $used)
    {
        $shortcut = strtolower($leaveApplication->leave->shortcut ?? '');

        $label = $shortcut === 'spl'
            ? 'MC6'
            : strtoupper(
                $leaveApplication->specialLeaveCredit->specialLeave->shortcut
                    ?? $leaveApplication->leave->shortcut
                    ?? 'LEAVE'
            );

        return sprintf(
            '%s CREDITS %.2f, AVAILED %.2f, BALANCE %.2f',
            $label,
            $credits,
            $leaveApplication->credits,
            max(0, $credits - $leaveApplication->credits)
        );
    }

    private function getHRMOHeadOfOffice($leaveApplication)
    {
        $certifiedStatus = $leaveApplication->leaveStatuses()
            ->where('status', 'certified')
            ->latest()
            ->first();
        
        if ($certifiedStatus) {
            // Replace employee with the correct relationship/column
            $hrmoHead = $certifiedStatus->signatory;

            return [
                'name' => $hrmoHead->personalInformation->getFullNameWithMiddleInitialAttribute(),
                'signaturePath' => $hrmoHead->personalInformation->e_signature_path,
            ];
        }

        // Fallback to current HRMO Head of the operating unit
        $hrmoHead = Designation::getHeadHrmo($leaveApplication->employee->operating_unit_id);

        if ($hrmoHead) {
            return [
                'name' => $hrmoHead->personalInformation->getFullNameWithMiddleInitialAttribute(),
                'signaturePath' => null,
            ];
        }

        return [
            'name' => '',
            'signaturePath' => null,
        ];
    }

    private function getCertificationTimestamp($leaveApplication)
    {
        $certificationTimestamp = $leaveApplication->leaveStatuses()
            ->where('status', 'certified')
            ->latest('acted_at')
            ->first();

        return $certificationTimestamp ? Carbon::parse($certificationTimestamp->acted_at)->format('F j, Y, g:i A') : '';
    }

    private function getLatestLeaveReferenceDate($leaveApplication, $fallbackDate)
    {
        $employee = $leaveApplication->employee;

        $latestVL = $employee->leaveCreditsHistory()
            ->where('created_at', '<=', $leaveApplication->created_at)
            ->where('leave_id', 1) // Vacation Leave
            ->latest('created_at')
            ->value('created_at');

        $latestSL = $employee->leaveCreditsHistory()
            ->where('created_at', '<=', $leaveApplication->created_at)
            ->where('leave_id', 3) // Sick Leave
            ->latest('created_at')
            ->value('created_at');

        $latestCurrent = $employee->leaveCreditsHistory()
            ->where('created_at', '<=', $leaveApplication->created_at)
            ->where('leave_id', $leaveApplication->leave_id)
            ->latest('created_at')
            ->value('created_at');

        return collect([$latestVL, $latestSL, $latestCurrent])
            ->filter()
            ->max() ?? $fallbackDate;
    }

    private function getRecommendationStatus($leaveApplication)
    {
        $isLeaveForApproval = false;
        $isLeaveForDisapproval = false;
        $remarksForDisapproval = '';

        $leaveStatuses = $leaveApplication->leaveStatuses()
            ->select('status', 'remarks', 'acted_at')
            ->get();

        foreach ($leaveStatuses as $status) {
            $currentStatus = strtolower($status->status ?? '');

            if ($currentStatus === 'for approval') {
                $isLeaveForApproval = true;
            }

            if ($currentStatus === 'for disapproval') {
                $isLeaveForDisapproval = true;

                if (
                    !$latestDisapprovalDate ||
                    ($status->acted_at && $status->acted_at > $latestDisapprovalDate)
                ) {
                    $latestDisapprovalDate = $status->acted_at;
                    $remarksForDisapproval = $status->remarks ?? '';
                }
            }
        }

        return [
            'isLeaveForApproval' => $isLeaveForApproval,
            'isLeaveForDisapproval' => $isLeaveForDisapproval,
            'remarksForDisapproval' => $remarksForDisapproval,
        ];
    }

    private function getRecommendationSignatory($leaveApplication, $leaveDatesInfo)
    {
        $recommendationStatus = $leaveApplication->leaveStatuses()
            ->whereIn('status', ['for approval', 'for disapproval'])
            ->with('signatory.personalInformation')
            ->latest()
            ->first();

        // Use the stored signatory if the leave has already been recommended
        if ($recommendationStatus?->signatory) {
            return [
                'name' => $recommendationStatus->signatory->personalInformation->getFullNameWithMiddleInitialAttribute(),
                'designation' => $leaveDatesInfo['numberOfDays'] >= 30
                    ? Designation::getHeadOperatingUnit($leaveApplication->employee->operating_unit_id)['designation'] ?? ''
                    : 'Immediate Supervisor',
                'signaturePath' => $recommendationStatus->signatory->personalInformation->e_signature_path,
            ];
        }

        // Otherwise use the current signatory
        if ($leaveDatesInfo['numberOfDays'] >= 30) {
            $headOperatingUnit = Designation::getHeadOperatingUnit($leaveApplication->employee->operating_unit_id);

            if ($headOperatingUnit) {
                return [
                    'name' => $headOperatingUnit['employee']->personalInformation->getFullNameWithMiddleInitialAttribute(),
                    'designation' => $headOperatingUnit['designation'],
                    'signaturePath' => null,
                ];
            }
        }

        $immediateSupervisor = Employee::find($leaveApplication->employee->immediate_supervisor_id);

        if ($immediateSupervisor) {
            return [
                'name' => $immediateSupervisor->personalInformation->getFullNameWithMiddleInitialAttribute(),
                'designation' => 'Immediate Supervisor',
                'signaturePath' => null,
            ];
        }

        return [
            'name' => '',
            'designation' => '',
            'signaturePath' => null,
        ];
    }

    private function getRecommendationTimestamp($leaveApplication)
    {
        $recommendationTimestamp = $leaveApplication->leaveStatuses()
            ->whereIn('status', ['for approval', 'for disapproval'])
            ->latest('acted_at')
            ->first();

        return $recommendationTimestamp ? Carbon::parse($recommendationTimestamp->acted_at)->format('F j, Y, g:i A') : '';
    }

    private function getDaysWithWithoutPay($leaveApplication)
    {
        $employee = $leaveApplication->employee;

        $isSpecialLeave = $leaveApplication->leave_id == 13;

        // 🔥 Identify special leave type properly
        $specialName = strtolower(trim(
            $leaveApplication->specialLeaveCredit->specialLeave->name ?? ''
        ));

        $isCTO = $specialName === 'compensatory overtime credit';
        $isWellness = $specialName === 'wellness leave';

        // =========================
        // TOTAL REQUESTED
        // =========================
        $rawCredits = (float) $leaveApplication->leaveDates->sum('credits');

        // ✅ Normalize request:
        // - CTO allows 0.5
        // - EVERYTHING ELSE = whole days
        $totalCredits = $isCTO
            ? $rawCredits
            : (int) ceil($rawCredits);

        // =========================
        // DETERMINE CORRECT BALANCE SOURCE
        // =========================

        $isTeaching = $employee->employee_type === 'Teaching';
        $hasDesignation = $employee->employeeDesignations()->exists();

        $hasVslDesignation = $employee->employeeDesignations()
            ->whereHas('designation', function ($query) {
                $query->where('is_vsl', 1);
            })
            ->exists();

        // 🔥 Get Service Credit ID
        $serviceCreditId = optional(
            Leave::where('name', 'Service Credits')->first()
        )->id;

        if ($isSpecialLeave) {

            // ✅ Special leave (CTO / Wellness / Others)
            $balance = (float) (
                $employee->leaveCreditsHistory()
                ->where('document_type_number', $leaveApplication->specialLeaveCredit->document_type_number)
                ->where('created_at', '<', $leaveApplication->created_at)
                ->latest()
                ->value('balance') ?? 0
            );
        } else {

            // ============================================
            // 🔥 SERVICE CREDIT OVERRIDE (IMPORTANT FIX)
            // ============================================
            if (
                $isTeaching &&
                !$hasVslDesignation &&
                in_array($leaveApplication->leave_id, [1, 3]) &&
                $serviceCreditId
            ) {
                $balance = (float) (
                    $employee->leaveCreditsHistory()
                    ->where('leave_id', $serviceCreditId)
                    ->where('created_at', '<', $leaveApplication->created_at)
                    ->latest()
                    ->value('balance') ?? 0
                );
            } else {
                $balance = (float) (
                    $employee->leaveCreditsHistory()
                    ->where('leave_id', $leaveApplication->leave_id)
                    ->where('created_at', '<', $leaveApplication->created_at)
                    ->latest()
                    ->value('balance') ?? 0
                );
            }
        }

        // =========================
        // CORE COMPUTATION
        // =========================

        if ($isCTO) {
            // ✅ ONLY CTO allows fractional
            $daysWithPay = min($balance, $totalCredits);
        } else {
            // ❌ EVERYTHING ELSE = whole days only
            $daysWithPay = min((int) floor($balance), (int) $totalCredits);
        }

        $daysWithoutPay = max(0, $totalCredits - $daysWithPay);

        return [
            'daysWithPay' => $daysWithPay,
            'daysWithoutPay' => $daysWithoutPay,
        ];
    }

    private function getDisapprovalReason($leaveApplication)
    {
        $disapprovalStatus = $leaveApplication->leaveStatuses()
            ->where('status', 'disapproved')
            ->latest('acted_at')
            ->first();

        return $disapprovalStatus->remarks ?? '';
    }

    private function getLeaveApprover($leaveApplication, $leaveDatesInfo)
    {
        $approvalStatus = $leaveApplication->leaveStatuses()
            ->whereIn('status', ['approved', 'disapproved'])
            ->with('signatory.personalInformation')
            ->latest()
            ->first();

        // Use the historical signatory if the leave has already been acted upon
        if ($approvalStatus?->signatory) {
            return [
                'name' => $approvalStatus->signatory->personalInformation->getFullNameWithMiddleInitialAttribute(),
                'designation' => $leaveDatesInfo['numberOfDays'] >= 30
                    ? 'University President'
                    : (Designation::getHeadOperatingUnit($leaveApplication->employee->operating_unit_id)['designation'] ?? ''),
                'signaturePath' => $approvalStatus->signatory->personalInformation->e_signature_path,
            ];
        }

        // Otherwise use the current approver
        if ($leaveDatesInfo['numberOfDays'] >= 30) {
            $universityPresident = Designation::getUniversityPresident();

            if ($universityPresident) {
                return [
                    'name' => $universityPresident['personalInformation']->getFullNameWithMiddleInitialAttribute(),
                    'designation' => 'University President',
                    'signaturePath' => null,
                ];
            }
        }

        $headOfOperatingUnit = Designation::getHeadOperatingUnit($leaveApplication->employee->operating_unit_id);

        if ($headOfOperatingUnit) {
            return [
                'name' => $headOfOperatingUnit['employee']->personalInformation->getFullNameWithMiddleInitialAttribute(),
                'designation' => $headOfOperatingUnit['designation'],
                'signaturePath' => null,
            ];
        }

        return [
            'name' => '',
            'designation' => '',
            'signaturePath' => null,
        ];
    }

    private function getApprovalTimestamp($leaveApplication)
    {
        $approvalStatus = $leaveApplication->leaveStatuses()
            ->where('status', 'approved')
            ->latest('acted_at')
            ->first();

        return $approvalStatus ? Carbon::parse($approvalStatus->acted_at)->format('F j, Y, g:i A') : '';
    }
}
