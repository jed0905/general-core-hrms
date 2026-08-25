<?php

namespace App\Http\Controllers\Web\HrManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWeeklyShiftTemplatesFormRequest;
use App\Http\Requests\UpdateWeeklyShiftTemplateFormRequest;
use App\Http\Resources\WeeklyShiftTemplateResource;
use App\Models\DailyShiftSchedule;
use App\Models\WeeklyShiftDay;
use App\Models\WeeklyShiftTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;

class WeeklyShiftTemplatesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $direction = request('direction') === 'Descending' ? 'DESC' : 'ASC';
    $perPage = request('size', 10); // ✅ dynamic page size
    $search = request('search');

    $weeklyShiftTemplatesQuery = WeeklyShiftTemplate::with(['weeklyShiftDays', 'employees'])
        ->when($search, function ($query) use ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        })
        ->orderBy('name', $direction);

        $weeklyShiftTemplates = WeeklyShiftTemplateResource::collection(
            $weeklyShiftTemplatesQuery
                ->paginate($perPage)
                ->through(function ($template) {
                    $template->edit_link = URL::signedRoute(
                        'hrmanagement.jobstructure.weeklyShiftTemplates.edit',
                        ['id' => $template->id]
                    );
                    return $template;
                })
                ->withQueryString() // ✅ ensures size, search, direction persist
        );

        return Inertia::render('app/HrManagement/JobStructure/WorkShifts/WeeklyShiftTemplates/Index', [
            'weeklyShiftTemplates' => $weeklyShiftTemplates,
            'filters' => [
                'search' => $search,
                'direction' => request('direction', 'Ascending'),
                'size' => $perPage,
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('app/HrManagement/JobStructure/WorkShifts/WeeklyShiftTemplates/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreWeeklyShiftTemplatesFormRequest $request)
    {
        try {
            $validated = $request->validated();
            $weeklyShiftTemplate = WeeklyShiftTemplate::create($validated);
            return redirect()->route('hrmanagement.jobstructure.weeklyShiftTemplates.index');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
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
    public function edit(string $id)
    {
        $weeklyShiftTemplate = WeeklyShiftTemplate::where('id', $id)->first();
        return Inertia::render('app/HrManagement/JobStructure/WorkShifts/WeeklyShiftTemplates/Edit', [
            'weeklyShiftTemplate' => $weeklyShiftTemplate,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateWeeklyShiftTemplateFormRequest $request, string $id)
    {
        try{
            $validated = $request->validated();
            WeeklyShiftTemplate::where('id', $id)->update($validated);
            return redirect()->route('hrmanagement.jobstructure.weeklyShiftTemplates.index');
        }catch(\Exception $e){
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try{
            WeeklyShiftTemplate::where('id', $id)->delete();
            return redirect()->route('hrmanagement.jobstructure.weeklyShiftTemplates.index');
        }catch(\Exception $e){
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function manage(string $id)
    {
        
        $weeklyShiftTemplate = WeeklyShiftTemplate::where('id', $id)->first();
        $DailyShiftSchedules = Request('selectedDay') ? DailyShiftSchedule::where('day_of_week', request('selectedDay'))
        ->get() : [];

        $weeklyShiftDays = WeeklyShiftDay::where('weekly_shift_template_id', $id)
        ->with('dailyShiftSchedule')->get();

        
        return Inertia::render('app/HrManagement/JobStructure/WorkShifts/WeeklyShiftTemplates/Manage', [
            'weeklyShiftTemplate' => $weeklyShiftTemplate,
            'DailyShiftSchedules' => $DailyShiftSchedules,
            'weeklyShiftDays' => $weeklyShiftDays,
        ]);
    }

    public function saveSchedule(Request $request, string $id)
    {
        try {
            $validated = $request->validate([
                'schedules' => 'required|array',
                'schedules.*.day_of_week' => 'required|string',
                'schedules.*.daily_shift_schedule_id' => 'required|integer|exists:daily_shift_schedules,id',
            ]);

            // Delete existing weekly shift days for this template
            WeeklyShiftDay::where('weekly_shift_template_id', $id)->delete();

            // Create new weekly shift days
            foreach ($validated['schedules'] as $schedule) {
                WeeklyShiftDay::create([
                    'weekly_shift_template_id' => $id,
                    'daily_shift_schedule_id' => $schedule['daily_shift_schedule_id'],
                ]);
            }

            return redirect()->back()->with('success', 'Weekly shift schedule saved successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function saveSingleSchedule(Request $request, string $id)
    {

        /* Validate the request */
        /* Don't allow multiple same day of the week with the same template */
        try {
            $validated = $request->validate([
                'day_of_week' => 'required|string',
                'daily_shift_schedule_id' => 'required|integer|exists:daily_shift_schedules,id',
            ]);

            // Delete existing schedule for this day if it exists
            WeeklyShiftDay::where('weekly_shift_template_id', $id)
                ->whereHas('dailyShiftSchedule', function($query) use ($validated) {
                    $query->where('day_of_week', $validated['day_of_week']);
                })
                ->delete();

            // Create new weekly shift day
            WeeklyShiftDay::create([
                'weekly_shift_template_id' => $id,
                'daily_shift_schedule_id' => $validated['daily_shift_schedule_id'],
            ]);

            return redirect()->back();
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function deleteSingleSchedule(string $id)
    {
       
        try {
            $weeklyShiftDay = WeeklyShiftDay::findOrFail($id);
            $weeklyShiftDay->delete();
            return redirect()->back();
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function deleteBatch(Request $request, string $id)
    {
        try {
            $validated = $request->validate([
                'selectedIds' => 'required|array',
                'selectedIds.*' => 'required|integer|exists:weekly_shift_days,id',
            ]);

            // Delete the selected weekly shift days
            WeeklyShiftDay::whereIn('id', $validated['selectedIds'])->delete();

            return redirect()->back()->with('success', 'Selected schedules deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
