<?php

namespace App\Http\Controllers\Web\HrManagement;

use App\Http\Filters\PositionsFilter;
use Exception;
use Inertia\Inertia;
use App\Models\Employee;
use App\Models\Position;
use App\Models\SalaryGrade;
use Illuminate\Http\Request;
use App\Models\OperatingUnit;
use App\Models\GovernmentPosition;
use Illuminate\Support\Facades\URL;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\PositionsResource;
use App\Http\Requests\StorePositionFormRequest;
use App\Http\Requests\UpdatePositionRequest;

class PositionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();

        $employee_operating_unit = Employee::where('id', $user->employee_id)
        ->pluck('operating_unit_id')->first();

        $query = Position::with([
            'government_position',
            'salary_grade',
            'operating_unit'
        ]);

        $filter = new PositionsFilter(request()->all());
        $query = $filter->apply($query);

        // Restrict if not superadmin/hr_director
        if (!($user->hasRole('superadmin') || $user->hasRole('hr_director'))) {
            $query->where('operating_unit_id', $employee_operating_unit);
        }

        $query->orderBy('id', request('direction') === 'Descending' ? 'DESC' : 'ASC');

        $positions = $query->paginate(request('size', 10))->through(function ($position) {
            $position->edit_link = URL::signedRoute('hrmanagement.jobstructure.position.edit', ['id' => $position->id]);
            return $position;
        })->withQueryString();

        // dd($positions);

        $operating_units = OperatingUnit::all();
        $salary_grades = SalaryGrade::all();

        return Inertia::render('app/HrManagement/JobStructure/Position/Index', [
            'positions' => PositionsResource::collection($positions),
            'operating_units' => $operating_units,
            'salary_grades' => $salary_grades
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $user = Auth::user();

        $employee_operating_unit = Employee::where('id', $user->employee_id)->pluck('operating_unit_id')->first();


        $positions = GovernmentPosition::all();
        $salary_grades = SalaryGrade::all();

        $operating_units = $user->hasRole('superadmin') || $user->hasRole('hr_director') ? OperatingUnit::all() : OperatingUnit::where('id', $employee_operating_unit)->get();

        return Inertia::render('app/HrManagement/JobStructure/Position/Create', [
            'positions' => $positions,
            'salary_grades' => $salary_grades,
            'operating_units' => $operating_units,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePositionFormRequest $request)
    {
        try {
            $validated = $request->validated();
            $position = Position::create($validated);

            return redirect()->route('hrmanagement.jobstructure.position.index');
        } catch (Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to create position: ' . $e->getMessage()]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, string $id)
    {


        
        if (!$request->hasValidSignature()) {
            abort(403, 'Invalid or expired link');
        }

        // $position = fn() => PositionsResource::make(
        //     Position::with([
        //         'government_position',
        //         'salary_grade'
        //     ])
        //         ->findOrFail($id)
        // );

        $positions = Position::with('operating_unit', 'salary_grade', 'government_position')->findOrFail($id);

        $operating_units = OperatingUnit::get();
        $salary_grades = SalaryGrade::get();
        $government_positions = GovernmentPosition::all();

        return Inertia::render('app/HrManagement/JobStructure/Position/Edit', [
            'positions' => $positions,
            'operating_units' => $operating_units,
            'salary_grades' => $salary_grades,
            'government_positions' => $government_positions,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePositionRequest $request, string $id)
    {

        $duplicateCheck = Position::where('government_positions_id', $request->government_positions_id)
            ->where('salary_grade_id', $request->salary_grade_id)
            ->where('operating_unit_id', $request->operating_unit_id)
            ->where('plantilla_item_number', $request->plantilla_item_number)
            ->where('id', '!=', $id)
            ->first();
        if ($duplicateCheck) {
            return redirect()->back()->withErrors(['error' => 'Position already exists.']);
        }

        if ($request->plantilla_item_number) {
            $duplicateCheck = Position::where('plantilla_item_number', $request->plantilla_item_number)
                ->where('id', '!=', $id)
                ->first();
            if ($duplicateCheck) {
                return redirect()->back()->withErrors(['error' => 'Plantilla item number already exists.']);
            }
        }

        try {
            $position = Position::findOrFail($id);
            $position->update($request->validated());
            return redirect()->route('hrmanagement.jobstructure.position.index');
        } catch (Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update position: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $position = Position::findOrFail($id);
            $position->delete();
            return redirect()->route('hrmanagement.jobstructure.position.index');
        } catch (Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to delete position: ' . $e->getMessage()]);
        }
    }
}
