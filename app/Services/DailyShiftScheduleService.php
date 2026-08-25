<?php

namespace App\Services;

use App\Http\Resources\DailyShiftScheduleResource;
use App\Models\DailyShiftSchedule;
use Illuminate\Support\Facades\URL;

class DailyShiftScheduleService
{
    public function getDailyShiftSchedules(){
     

        $dailyShiftSchedules = fn() => DailyShiftScheduleResource::collection(
    DailyShiftSchedule::when(request('search'), function($query){
                    $query->where('day_of_week', 'like', '%'.request('search').'%')
                    ->orWhere('time_in', 'like', '%'.request('search').'%')
                    ->orWhere('time_out', 'like', '%'.request('search').'%')
                    ->orWhere('break_start', 'like', '%'.request('search').'%')
                    ->orWhere('break_end', 'like', '%'.request('search').'%');
                })
                ->when(request('day_of_week'), function($query){
                    $query->where('day_of_week', request('day_of_week'));
                })
                ->when(request('time_in'), function($query){
                    $query->where('time_in', request('time_in'));
                })
                ->when(request('time_out'), function($query){
                    $query->where('time_out', request('time_out'));
                })
                ->when(request('break_start'), function($query){
                    $query->where('break_start', request('break_start'));
                })
                ->when(request('break_end'), function($query){
                    $query->where('break_end', request('break_end'));
                })
                ->orderBy('day_of_week', request('direction') === 'Descending' ? 'DESC' : 'ASC')
                ->paginate(request('size',10))->through(function($dailyShiftSchedule){
            $dailyShiftSchedule->signed_url = URL::signedRoute('hrmanagement.jobstructure.dailyShiftSchedules.edit', ['id' => $dailyShiftSchedule->id]);
            return $dailyShiftSchedule;
        })->withQueryString());
        return $dailyShiftSchedules;
    }

    public function getDailyShiftScheduleById($id){
        $dailyShiftSchedule = DailyShiftSchedule::findOrFail($id);
        return $dailyShiftSchedule;
    }

    public function storeDailyShiftSchedule($validated){
        $dailyShiftSchedule = DailyShiftSchedule::create($validated);
        return $dailyShiftSchedule;
    }

    public function updateDailyShiftSchedule($id, $validated){
        $dailyShiftSchedule = DailyShiftSchedule::findOrFail($id);
        $dailyShiftSchedule->update($validated);
        return $dailyShiftSchedule;
    }

    public function destroyDailyShiftSchedule($id){
        $dailyShiftSchedule = DailyShiftSchedule::findOrFail($id);
        $dailyShiftSchedule->delete();
        return $dailyShiftSchedule;
    }
}