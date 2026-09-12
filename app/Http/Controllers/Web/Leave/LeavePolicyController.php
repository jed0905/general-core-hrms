<?php

namespace App\Http\Controllers\Web\Leave;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeavePolicyRequest;
use App\Http\Requests\UpdateLeavePolicyRequest;
use App\Models\LeavePolicy;
use App\Services\LeavePolicyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeavePolicyController extends Controller
{
    public function __construct(protected LeavePolicyService $policyService) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'is_active']);

        return Inertia::render('app/Leave/Configuration/Policies/Index', [
            'policies' => $this->policyService->getPaginatedPolicies($filters),
            'filters' => $filters,
        ]);
    }

    public function store(StoreLeavePolicyRequest $request): RedirectResponse
    {
        $this->policyService->createPolicy($request->validated());

        return redirect()->route('leave.config.policies.index')->with('success', 'Leave policy created successfully.');
    }

    public function update(UpdateLeavePolicyRequest $request, LeavePolicy $leavePolicy): RedirectResponse
    {
        $this->policyService->updatePolicy($leavePolicy, $request->validated());

        return redirect()->route('leave.config.policies.index')->with('success', 'Leave policy updated successfully.');
    }

    public function destroy(LeavePolicy $leavePolicy): RedirectResponse
    {
        $this->policyService->archivePolicy($leavePolicy);

        return redirect()->route('leave.config.policies.index')->with('success', 'Leave policy archived successfully.');
    }
}
