<?php

namespace App\Http\Controllers\Web\Administration\Organization;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Services\LocationService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class LocationController extends Controller
{
    public function __construct(
        protected LocationService $locationService
    ) {}

    public function index(): Response
    {
        return Inertia::render('Administration/Organization/Index', [
            'initialLocations' => $this->locationService->getAllLocations(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'     => ['nullable', 'string', 'max:255'],
            'city'     => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'zip_code' => ['nullable', 'string', 'max:20'],
            'address'  => ['nullable', 'string', 'max:500'],
            'phone'    => ['nullable', 'string', 'max:50'],
            'notes'    => ['nullable', 'string', 'max:1000'],
            'is_main'  => ['nullable', 'boolean'],
        ]);

        $this->locationService->storeLocation($validated);

        return redirect()->back()->with('success', 'Branch location created successfully.');
    }

    public function update(Request $request, Location $location): RedirectResponse
    {
        $validated = $request->validate([
            'name'     => ['nullable', 'string', 'max:255'],
            'city'     => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'zip_code' => ['nullable', 'string', 'max:20'],
            'address'  => ['nullable', 'string', 'max:500'],
            'phone'    => ['nullable', 'string', 'max:50'],
            'notes'    => ['nullable', 'string', 'max:1000'],
            'is_main'  => ['nullable', 'boolean'],
        ]);

        $this->locationService->updateLocation($location, $validated);

        return redirect()->back()->with('success', 'Branch location updated successfully.');
    }

    public function destroy(Location $location): RedirectResponse
    {
        $this->locationService->deleteLocation($location);

        return redirect()->back()->with('success', 'Branch location deleted successfully.');
    }
}
