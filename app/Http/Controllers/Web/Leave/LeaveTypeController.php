<?php

namespace App\Http\Controllers\Web\Leave;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeaveTypeRequest;
use App\Http\Requests\UpdateLeaveTypeRequest;
use App\Models\LeaveType;
use App\Services\LeaveTypeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaveTypeController extends Controller
{
    public function __construct(protected LeaveTypeService $typeService) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'is_active']);

        return Inertia::render('app/Leave/Configuration/Types/Index', [
            'leaveTypes' => $this->typeService->getPaginatedTypes($filters),
            'filters' => $filters,
        ]);
    }

    public function store(StoreLeaveTypeRequest $request): RedirectResponse
    {
        $this->typeService->createType($request->validated());

        return redirect()->route('leave.config.types.index')->with('success', 'Leave type created successfully.');
    }

    public function update(UpdateLeaveTypeRequest $request, LeaveType $leaveType): RedirectResponse
    {
        $this->typeService->updateType($leaveType, $request->validated());

        return redirect()->route('leave.config.types.index')->with('success', 'Leave type updated successfully.');
    }

    public function destroy(LeaveType $leaveType): RedirectResponse
    {
        $this->typeService->archiveType($leaveType);

        return redirect()->route('leave.config.types.index')->with('success', 'Leave type archived successfully.');
    }
}
