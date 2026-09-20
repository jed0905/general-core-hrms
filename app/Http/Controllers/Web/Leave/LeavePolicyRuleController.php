<?php

namespace App\Http\Controllers\Web\Leave;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeavePolicyRuleRequest;
use App\Http\Requests\UpdateLeavePolicyRuleRequest;
use App\Models\LeavePolicy;
use App\Models\LeavePolicyRule;
use App\Models\LeaveType;
use App\Services\LeavePolicyRuleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeavePolicyRuleController extends Controller
{
    public function __construct(protected LeavePolicyRuleService $ruleService) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['policy_id', 'leave_type_id']);

        return Inertia::render('app/Leave/Configuration/Rules/Index', [
            'rules' => $this->ruleService->getPaginatedRules($filters),
            'policies' => LeavePolicy::where('is_active', true)->get(['id', 'name']),
            'leaveTypes' => LeaveType::where('is_active', true)->get(['id', 'name', 'code']),
            'filters' => $filters,
        ]);
    }

    public function store(StoreLeavePolicyRuleRequest $request): RedirectResponse
    {
        $this->ruleService->createRule($request->validated());

        return redirect()->route('leave.config.rules.index')->with('success', 'Policy rule created successfully.');
    }

    public function update(UpdateLeavePolicyRuleRequest $request, LeavePolicyRule $leavePolicyRule): RedirectResponse
    {
        $this->ruleService->updateRule($leavePolicyRule, $request->validated());

        return redirect()->route('leave.config.rules.index')->with('success', 'Policy rule updated successfully.');
    }

    public function destroy(LeavePolicyRule $leavePolicyRule)
    {
        $this->ruleService->deleteRule($leavePolicyRule);

        return redirect()->route('leave.config.rules.index')->with('success', 'Policy rule deleted successfully.');
    }
}
