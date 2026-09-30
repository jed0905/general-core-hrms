<?php

namespace App\Http\Controllers\Web\People;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\EmployeeMovementService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Employee self-service: the logged-in user's own effective movements only.
 * The employee is always users.employee_id.
 */
class MyEmploymentHistoryController extends Controller
{
    public function __construct(protected EmployeeMovementService $movementService) {}

    public function index(Request $request): Response
    {
        $employeeId = $request->user()->employee_id;
        $employee = $employeeId ? Employee::find($employeeId) : null;

        return Inertia::render('app/People/MyEmploymentHistory/Index', [
            'hasEmployeeRecord' => $employee !== null,
            // Only what the page shows: internal HR remarks and user ids stay out of the props.
            'movements' => $employee
                ? $this->movementService->getHistoryForEmployee($employee)->map(fn ($movement) => [
                    'id' => $movement->id,
                    'effective_date' => $movement->effective_date?->toDateString(),
                    'type' => $movement->type?->only(['code', 'name']),
                    'snapshot' => $movement->snapshot,
                    'reason' => $movement->reason,
                ])->values()
                : [],
        ]);
    }
}
