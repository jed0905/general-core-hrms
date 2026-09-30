<?php

namespace App\Http\Controllers\Web\People;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeMovementTypeRequest;
use App\Http\Requests\UpdateEmployeeMovementTypeRequest;
use App\Models\Employee;
use App\Models\EmployeeMovement;
use App\Models\EmployeeMovementType;
use App\Services\EmployeeMovementTypeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeMovementTypeController extends Controller
{
    public function __construct(protected EmployeeMovementTypeService $typeService) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['is_active']);

        return Inertia::render('app/People/EmployeeMovementTypes/Index', [
            'types' => $this->typeService->getTypes($filters),
            'fields' => EmployeeMovement::FIELDS,
            'employeeStatuses' => Employee::STATUSES,
            'filters' => $filters,
        ]);
    }

    public function store(StoreEmployeeMovementTypeRequest $request): RedirectResponse
    {
        $this->typeService->createType($request->validated());

        return redirect()->route('people.employee-movement-types.index')->with('success', 'Movement type created.');
    }

    public function update(UpdateEmployeeMovementTypeRequest $request, EmployeeMovementType $employeeMovementType): RedirectResponse
    {
        $this->typeService->updateType($employeeMovementType, $request->validated());

        return redirect()->route('people.employee-movement-types.index')->with('success', 'Movement type updated.');
    }

    public function destroy(EmployeeMovementType $employeeMovementType): RedirectResponse
    {
        $this->typeService->archiveType($employeeMovementType);

        return redirect()->route('people.employee-movement-types.index')->with('success', 'Movement type archived.');
    }
}
