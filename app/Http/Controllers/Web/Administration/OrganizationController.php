<?php

namespace App\Http\Controllers\Web\Administration;

use App\Http\Requests\StoreOperatingUnitFormRequest;
use App\Http\Requests\UpdateDepartmentFormRequest;
use App\Http\Requests\UpdateOperatingUnitFormRequest;
use App\Http\Requests\UpdateSubUnitFormRequest;
use App\Services\OrganizationStructureService;
use Exception;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDepartmentFormRequest;
use App\Http\Requests\StoreSubUnitFormRequest;

class OrganizationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(OrganizationStructureService $organizationStructureService)
    {
        // Load only operating units initially for faster page load
        // $operatingUnits = $organizationStructureService->getOperatingUnits();

        $data = $organizationStructureService->index();

        return Inertia::render('app/Administration/Organization/Index', [
            // 'operating_units' => $operatingUnits,
            // 'departments' => [], // Empty initially - will be loaded on demand
            'operating_units' => $data['operating_units'],
            'departments' => $data['departments'],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function storeOperatingUnit(StoreOperatingUnitFormRequest $request, OrganizationStructureService $organizationStructureService)
    {
        try {
            $organizationStructureService->storeOperatingUnit($request->validated());

            return redirect()->route('administration.organization.index');
        } catch (Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to create operating unit: ' . $e->getMessage()]);
        }

    }

    /**
     * Store a newly created department in storage.
     */
    public function storeDepartment(StoreDepartmentFormRequest $request, OrganizationStructureService $organizationStructureService)
    {
        try {
            $organizationStructureService->storeDepartment($request->validated());

            return redirect()->route('administration.organization.index');
        } catch (Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to create department: ' . $e->getMessage()]);
        }
    }


    /**
     * Update an existing Operating Unit.
     */
    public function updateOperatingUnit(UpdateOperatingUnitFormRequest $request, OrganizationStructureService $organizationStructureService, string $id)
    {
        try {
            $organizationStructureService->updateOperatingUnit($request->validated(), $id);

            return redirect()->route('administration.organization.index');
        } catch (Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update operating unit: ' . $e->getMessage()]);
        }
    }


    /**
     * Update an existing Department.
     */
    public function updateDepartment(UpdateDepartmentFormRequest $request, OrganizationStructureService $organizationStructureService, string $id)
    {
        try {
            $organizationStructureService->updateDepartment($request->validated(), $id);

            return redirect()->route('administration.organization.index');
        } catch (Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update department: ' . $e->getMessage()]);
        }
    }


    /**
     * Remove the specified resource from storage.
     */
    public function deleteDepartment(string $id, OrganizationStructureService $organizationStructureService)
    {
        try {
            $organizationStructureService->deleteDepartment($id);

            return redirect()->route('administration.organization.index');
        } catch (Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to delete department/sub-unit: ' . $e->getMessage()]);
        }
    }

    /**
     * Load departments for a specific operating unit (lazy loading)
     */
    public function loadDepartments(Request $request, OrganizationStructureService $organizationStructureService)
    {
        $operatingUnitId = $request->get('operating_unit_id');
        $parentId = $request->get('parent_id', null);

        $departments = $organizationStructureService->getDepartmentsByParent($operatingUnitId, $parentId);

        return response()->json([
            'departments' => $departments
        ]);
    }


    public function getOperatingUnits(OrganizationStructureService $organizationStructureService)
    {
        $data = $organizationStructureService->getOperatingUnitsForApi();

        return response()->json([
            'operating_units' => $data,
        ]);
    }

    public function getDepartments(OrganizationStructureService $organizationStructureService)
    {
        $data = $organizationStructureService->getDepartmentsForApi();

        return response()->json([
            'departments' => $data,
        ]);
    }

}
