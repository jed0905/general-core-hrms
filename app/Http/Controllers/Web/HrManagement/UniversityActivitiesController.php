<?php

namespace App\Http\Controllers\Web\HrManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUniversityActivityFormRequest;
use App\Http\Requests\UpdateUniversityActivityFormRequest;
use App\Services\UniversityActivityService;
use Exception;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UniversityActivitiesController extends Controller
{
    public function index(UniversityActivityService $universityActivityService)
    {
        $universityActivities = $universityActivityService->getUniversityActivities();
        $operatingUnits = $universityActivityService->getOperatingUnits();
        return Inertia::render('app/HrManagement/Leave/UniversityActivities/Index', [
            'universityActivities' => $universityActivities,
            'operatingUnits' => $operatingUnits
        ]);
    }

    public function create(UniversityActivityService $universityActivityService)
    {
        $operatingUnits = $universityActivityService->getOperatingUnits();
        return Inertia::render('app/HrManagement/Leave/UniversityActivities/Create', [
            'operatingUnits' => $operatingUnits
        ]);
    }

    public function store(StoreUniversityActivityFormRequest $request, UniversityActivityService $universityActivityService)
    {
        try {
            $universityActivityService->storeUniversityActivity($request->validated());
            return redirect()->route('hrmanagement.universityActivities.create');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['message' => $e->getMessage()]);
        }
    }

    public function edit(UniversityActivityService $universityActivityService, string $id)
    {
        $universityActivity = $universityActivityService->getUniversityActivityById($id);
        $operatingUnits = $universityActivityService->getOperatingUnits();
        return Inertia::render('app/HrManagement/Leave/UniversityActivities/Edit', [
            'universityActivity' => $universityActivity,
            'operatingUnits' => $operatingUnits
        ]);
    }

    public function update(UpdateUniversityActivityFormRequest $request, UniversityActivityService $universityActivityService, string $id)
    {
        try {
            $universityActivityService->updateUniversityActivity($request->validated(), $id);
            return redirect()->route('hrmanagement.universityActivities.index');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['message' => $e->getMessage()]);
        }
    }

    public function destroy(string $id, UniversityActivityService $universityActivityService)
    {
        try {
            $universityActivityService->deleteUniversityActivity($id);

            return redirect()->route('hrmanagement.universityActivities.index');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['message' => $e->getMessage()]);
        }
    }
}
