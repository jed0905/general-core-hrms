<?php

namespace App\Http\Controllers\Web\Time;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreShiftRequest;
use App\Http\Requests\UpdateShiftRequest;
use App\Models\Shift;
use App\Services\ShiftService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShiftController extends Controller
{
    public function __construct(
        protected ShiftService $shiftService
    ) {
    }

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'is_active']);
        $shifts = $this->shiftService->getPaginatedShifts($filters);

        return Inertia::render('app/Time/Shifts/Index', [
            'shifts' => $shifts,
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('app/Time/Shifts/Create');
    }

    public function store(StoreShiftRequest $request): RedirectResponse
    {
        $this->shiftService->createShift($request->validated());

        return redirect()
            ->route('time.shifts.index')
            ->with('success', 'Work shift created successfully.');
    }

    public function edit(Shift $shift): Response
    {
        return Inertia::render('app/Time/Shifts/Edit', [
            'shift' => $shift,
        ]);
    }

    public function update(UpdateShiftRequest $request, Shift $shift): RedirectResponse
    {
        $this->shiftService->updateShift($shift, $request->validated());

        return redirect()
            ->route('time.shifts.index')
            ->with('success', 'Work shift updated successfully.');
    }

    public function destroy(Shift $shift): RedirectResponse
    {
        $this->shiftService->archiveShift($shift);

        return redirect()
            ->route('time.shifts.index')
            ->with('success', 'Work shift archived successfully.');
    }
}
