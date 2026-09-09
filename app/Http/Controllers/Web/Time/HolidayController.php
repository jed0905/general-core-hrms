<?php

namespace App\Http\Controllers\Web\Time;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreHolidayRequest;
use App\Http\Requests\UpdateHolidayRequest;
use App\Models\Holiday;
use App\Services\HolidayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HolidayController extends Controller
{
    public function __construct(
        protected HolidayService $holidayService
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'year', 'type', 'status']);

        return Inertia::render('app/Time/Holidays/Index', [
            'holidays' => $this->holidayService->getPaginatedHolidays($filters),
            'filters' => $filters,
            'holidayTypes' => [
                ['value' => 'regular', 'title' => 'Regular Holiday'],
                ['value' => 'special_non_working', 'title' => 'Special Non-Working Holiday'],
                ['value' => 'special_working', 'title' => 'Special Working Holiday'],
                ['value' => 'company', 'title' => 'Company Holiday'],
            ],
        ]);
    }

    public function store(StoreHolidayRequest $request): RedirectResponse
    {
        $this->holidayService->createHoliday($request->validated());

        return redirect()
            ->route('time.holidays.index')
            ->with('success', 'Holiday created successfully.');
    }

    public function update(UpdateHolidayRequest $request, Holiday $holiday): RedirectResponse
    {
        $this->holidayService->updateHoliday($holiday, $request->validated());

        return redirect()
            ->route('time.holidays.index')
            ->with('success', 'Holiday updated successfully.');
    }

    public function destroy(Holiday $holiday): RedirectResponse
    {
        $this->holidayService->deleteHoliday($holiday);

        return redirect()
            ->route('time.holidays.index')
            ->with('success', 'Holiday deleted successfully.');
    }
}
