<?php

namespace App\Services\Payroll;

use App\Models\EmployeeCompensation;
use App\Models\PayrollAdjustment;
use App\Models\PayrollRun;
use App\Models\PayrollTimeInput;
use App\Models\StatutoryRuleVersion;
use App\Models\User;

/**
 * Work queues and recent totals for the payroll dashboard, within the user's
 * legal-entity scope. Totals are per currency; nothing is converted or summed
 * across currencies.
 */
class PayrollDashboardService
{
    public function __construct(protected PayrollScope $scope) {}

    public function summary(User $user): array
    {
        $runs = fn () => $this->scope->apply(PayrollRun::query(), $user);
        $entityIds = $this->scope->entityIds($user);
        $inEntities = fn ($q, string $relation) => $entityIds === null ? $q : $q->whereHas($relation, fn ($r) => $r->whereIn('legal_entity_id', $entityIds));

        return [
            'runsByStatus' => $runs()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'queues' => [
                'to_review' => $runs()->where('status', PayrollRun::STATUS_CALCULATED)->count(),
                'to_approve' => $runs()->where('status', PayrollRun::STATUS_REVIEWED)->count(),
                'to_finalize' => $runs()->where('status', PayrollRun::STATUS_APPROVED)->count(),
                'compensation_pending' => $inEntities(EmployeeCompensation::where('status', EmployeeCompensation::STATUS_PENDING), 'assignment')->count(),
                'time_inputs_pending' => $inEntities(PayrollTimeInput::where('status', PayrollTimeInput::STATUS_PENDING), 'period.payGroup')->count(),
                'adjustments_pending' => $inEntities(PayrollAdjustment::where('status', PayrollAdjustment::STATUS_PENDING), 'period.payGroup')->count(),
                'rules_to_verify' => StatutoryRuleVersion::where('verification_status', StatutoryRuleVersion::STATUS_DRAFT)->count(),
            ],
            'recentRuns' => $runs()
                ->with(['legalEntity:id,code,name,country_code', 'period:id,period_start,period_end,pay_date'])
                ->where('status', PayrollRun::STATUS_FINALIZED)
                ->orderByDesc('finalized_at')
                ->limit(8)
                ->get(['id', 'run_number', 'run_type', 'legal_entity_id', 'payroll_period_id', 'currency', 'gross_total', 'net_total', 'employer_cost_total', 'employee_count', 'finalized_at']),
        ];
    }
}
