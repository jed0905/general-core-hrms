<?php

namespace App\Http\Controllers\Web\People;

use App\Http\Controllers\Controller;
use App\Models\EmploymentStatus;
use App\Services\EmploymentStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmploymentStatusController extends Controller
{
    public function __construct(
        protected EmploymentStatusService $employmentStatusService
    ) {
    }

    /**
     * Display a listing of employment statuses.
     */
    public function index(Request $request): Response
    {
        $statuses = $this->employmentStatusService->getPaginated(
            perPage: $request->integer('per_page', 10),
            search: $request->filled('search') ? $request->string('search')->trim()->value() : null
        );

        return Inertia::render('app/People/EmploymentStatus/Index', [
            'employmentStatuses' => $statuses,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Store a newly created employment status in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:employment_statuses,name'],
        ]);

        $this->employmentStatusService->create($validated);

        return back()->with('success', 'Employment status created successfully.');
    }

    /**
     * Update the specified employment status in storage.
     */
    public function update(Request $request, EmploymentStatus $employmentStatus): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:employment_statuses,name,' . $employmentStatus->id],
        ]);

        $this->employmentStatusService->update($employmentStatus, $validated);

        return back()->with('success', 'Employment status updated successfully.');
    }

    /**
     * Archive the specified employment status.
     */
    public function archive(EmploymentStatus $employmentStatus): RedirectResponse
    {
        $this->employmentStatusService->archive($employmentStatus);

        return back()->with('success', 'Employment status archived successfully.');
    }
}
