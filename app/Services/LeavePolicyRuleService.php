<?php

namespace App\Services;

use App\Models\LeavePolicyRule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class LeavePolicyRuleService
{
    public function getPaginatedRules(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return LeavePolicyRule::query()
            ->with(['policy', 'leaveType'])
            ->when(!empty($filters['policy_id']), fn($q) => $q->where('leave_policy_id', $filters['policy_id']))
            ->when(!empty($filters['leave_type_id']), fn($q) => $q->where('leave_type_id', $filters['leave_type_id']))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function createRule(array $data): LeavePolicyRule
    {
        return DB::transaction(fn() => LeavePolicyRule::create([
            'leave_policy_id' => $data['leave_policy_id'],
            'leave_type_id' => $data['leave_type_id'],
            'accrual_method' => $data['accrual_method'],
            'accrual_rate' => $data['accrual_rate'],
            'grant_frequency' => $data['grant_frequency'],
            'max_balance' => $data['max_balance'],
            'carry_forward_limit' => $data['carry_forward_limit'],
            'min_service_months' => $data['min_service_months'],
            'allow_negative' => $data['allow_negative'] ?? false,
            'requires_approval' => $data['requires_approval'] ?? true,
            'requires_attachment' => $data['requires_attachment'] ?? false,
            'allows_half_day' => $data['allows_half_day'] ?? true,
            'allows_hourly' => $data['allows_hourly'] ?? false,
            'expires' => $data['expires'] ?? false,
        ]));
    }

    public function updateRule(LeavePolicyRule $rule, array $data): LeavePolicyRule
    {
        return DB::transaction(function () use ($rule, $data) {
            $rule->update([
                'leave_policy_id' => $data['leave_policy_id'],
                'leave_type_id' => $data['leave_type_id'],
                'accrual_method' => $data['accrual_method'],
                'accrual_rate' => $data['accrual_rate'],
                'grant_frequency' => $data['grant_frequency'],
                'max_balance' => $data['max_balance'],
                'carry_forward_limit' => $data['carry_forward_limit'],
                'min_service_months' => $data['min_service_months'],
                'allow_negative' => $data['allow_negative'] ?? false,
                'requires_approval' => $data['requires_approval'] ?? false,
                'requires_attachment' => $data['requires_attachment'] ?? false,
                'allows_half_day' => $data['allows_half_day'] ?? false,
                'allows_hourly' => $data['allows_hourly'] ?? false,
                'expires' => $data['expires'] ?? false,
            ]);

            return $rule->fresh();
        });
    }

    public function deleteRule(LeavePolicyRule $rule): bool
    {
        return DB::transaction(fn() => $rule->delete());
    }
}
