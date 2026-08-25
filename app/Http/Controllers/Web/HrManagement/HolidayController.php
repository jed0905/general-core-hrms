<?php

namespace App\Http\Controllers\Web\HrManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreHolidayRequest;
use App\Http\Requests\UpdateHolidayRequest;
use App\Services\HolidayService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HolidayController extends Controller
{
    public function index(HolidayService $holidayService){
        $holidays = $holidayService->index();
        $operatingUnits = $holidayService->getOperatingUnits();

        return Inertia::render('app/HrManagement/Leave/Holidays/Index', [
            'holidays' => $holidays['holidays'],
            'holidayCount' => $holidays['holidayCount'],
            'operatingUnits' => $operatingUnits,
        ]);
    }

    public function create(HolidayService $holidayService){
        $operatingUnits = $holidayService->getOperatingUnits();
        return Inertia::render('app/HrManagement/Leave/Holidays/Create', [
            'operatingUnits' => $operatingUnits
        ]);
    }

    public function store(StoreHolidayRequest $request, HolidayService $holidayService){
        try{
            $holidayService->storeHoliday($request->validated());
            return redirect()->route('hrmanagement.holiday.index');
        }catch(\Exception $e){
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function edit(String $id, HolidayService $holidayService){
        $holiday = $holidayService->getHoliday($id);
        $operatingUnits = $holidayService->getOperatingUnits();
        return Inertia::render('app/HrManagement/Leave/Holidays/Edit', [
            'holiday' => $holiday,
            'operatingUnits' => $operatingUnits
        ]);
    }

    public function update(UpdateHolidayRequest $request, String $id, HolidayService $holidayService){
        try{
            $holidayService->updateHoliday($id, $request->validated());
            return redirect()->route('hrmanagement.holiday.index');
        }catch(\Exception $e){
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function destroy(String $id, HolidayService $holidayService){
        try{
            $holidayService->deleteHoliday($id);
            return redirect()->route('hrmanagement.holiday.index');
        }catch(\Exception $e){
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function bulkDestroy(Request $request, HolidayService $holidayService){
        try{
            $holidayService->deleteBulkHoliday($request->input('ids'));
            return redirect()->route('hrmanagement.holiday.index');
        }catch(\Exception $e){
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
