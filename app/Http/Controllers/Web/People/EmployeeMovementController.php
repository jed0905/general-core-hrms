<?php

namespace App\Http\Controllers\Web\People;

use App\Exports\EmployeeMovementsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelEmployeeMovementRequest;
use App\Http\Requests\StoreEmployeeMovementRequest;
use App\Http\Requests\UpdateEmployeeMovementRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeMovement;
use App\Models\EmployeeMovementType;
use App\Models\EmploymentStatus;
use App\Models\JobTitle;
use App\Models\Location;
use App\Services\EmployeeMovementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EmployeeMovementController extends Controller
{
    private const FILTERS = ['search', 'employee_id', 'movement_type_id', 'status', 'department_id', 'employment_status_id', 'effective_from', 'effective_to'];

    public function __construct(protected EmployeeMovementService $movementService) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $filters = $request->only(self::FILTERS);

        return Inertia::render('app/People/EmployeeMovements/Index', [
            'movements' => $this->movementService->getPaginatedMovements($filters)
                ->through(fn (EmployeeMovement $movement) => array_merge($movement->toArray(), [
                    'can' => ['cancel' => $user->can('cancel', $movement)],
                ])),
            'filters' => $filters,
            'types' => EmployeeMovementType::orderBy('name')->get(['id', 'code', 'name', 'is_active']),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'employmentStatuses' => EmploymentStatus::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();
        $selected = $request->integer('employee_id') ? Employee::find($request->integer('employee_id')) : null;

        return Inertia::render('app/People/EmployeeMovements/Create', [
            'employees' => Employee::query()
                ->when($user->employee_id, fn ($q) => $q->whereKeyNot($user->employee_id))
                ->orderBy('emp_last_name')
                ->get(['id', 'employee_number', 'emp_first_name', 'emp_last_name', 'status']),
            'types' => EmployeeMovementType::where('is_active', true)->orderBy('name')
                ->get(['id', 'code', 'name', 'description', 'affected_fields', 'employee_status']),
            'fields' => EmployeeMovement::FIELDS,
            'options' => [
                'department_id' => Department::orderBy('name')->get(['id', 'name'])->map(fn ($d) => ['value' => $d->id, 'title' => $d->name]),
                'job_title_id' => JobTitle::orderBy('job_title')->get(['id', 'job_title'])->map(fn ($j) => ['value' => $j->id, 'title' => $j->job_title]),
                'employment_status_id' => EmploymentStatus::orderBy('name')->get(['id', 'name'])->map(fn ($s) => ['value' => $s->id, 'title' => $s->name]),
                'location_id' => Location::orderBy('city')->get(['id', 'city', 'address'])->map(fn ($l) => ['value' => $l->id, 'title' => trim(implode(', ', array_filter([$l->address, $l->city])))]),
                'supervisor_id' => Employee::whereNotIn('status', ['archived', 'terminated'])->orderBy('emp_last_name')
                    ->get(['id', 'emp_first_name', 'emp_last_name', 'employee_number'])
                    ->map(fn ($e) => ['value' => $e->id, 'title' => "{$e->emp_last_name}, {$e->emp_first_name} ({$e->employee_number})"]),
            ],
            'selectedEmployeeId' => $selected?->id,
            // Shown for reference only; the service re-reads the locked row on save.
            'currentState' => $selected && $user->can('create', [EmployeeMovement::class, $selected])
                ? $this->movementService->currentState($selected)
                : null,
        ]);
    }

    public function store(StoreEmployeeMovementRequest $request): RedirectResponse
    {
        $movement = $this->movementService->createMovement($request->validated(), $request->user());

        return redirect()
            ->route('people.employee-movements.show', $movement)
            ->with('success', $movement->isEffective() ? 'Movement recorded and applied.' : 'Movement scheduled for its effective date.');
    }

    public function show(Request $request, EmployeeMovement $employeeMovement): Response
    {
        $user = $request->user();

        $employeeMovement->load([
            'employee:id,employee_number,emp_first_name,emp_last_name,status',
            'type:id,code,name,description',
            'createdBy:id,username',
            'cancelledBy:id,username',
        ]);

        // Employees viewing their own movement (view_own) don't get HR's internal notes.
        if (! $user->can('employee_movement.view')) {
            $employeeMovement->makeHidden(['remarks', 'createdBy', 'cancelledBy', 'requested_by', 'approved_by', 'cancellation_reason']);
        }

        return Inertia::render('app/People/EmployeeMovements/Show', [
            'movement' => $employeeMovement,
            'can' => [
                'update' => $user->can('update', $employeeMovement),
                'cancel' => $user->can('cancel', $employeeMovement),
            ],
        ]);
    }

    public function update(UpdateEmployeeMovementRequest $request, EmployeeMovement $employeeMovement): RedirectResponse
    {
        $this->movementService->updateMovement($employeeMovement, $request->validated());

        return back()->with('success', 'Movement details updated.');
    }

    public function cancel(CancelEmployeeMovementRequest $request, EmployeeMovement $employeeMovement): RedirectResponse
    {
        $this->movementService->cancelMovement($employeeMovement, $request->user(), $request->validated('cancellation_reason'));

        return back()->with('success', 'Movement cancelled.');
    }

    public function export(Request $request): BinaryFileResponse
    {
        return Excel::download(
            new EmployeeMovementsExport($this->movementService->query($request->only(self::FILTERS))),
            'employee_movements_'.now()->format('Ymd_His').'.xlsx'
        );
    }
}
