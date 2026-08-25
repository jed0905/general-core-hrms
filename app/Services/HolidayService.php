<?php

namespace App\Services;

use App\Models\Holiday;
use App\Models\OperatingUnit;
use App\Traits\SanitizesInput;
use Illuminate\Support\Facades\URL;

class HolidayService
{
    use SanitizesInput;

    public function index(){
        $currentYear = date('Y');
        $holidays = Holiday::with('operatingUnits')->whereYear('date', $currentYear)->get()->map(function($holiday) use ($currentYear){
            $holiday->edit_link = URL::signedRoute('hrmanagement.holiday.edit', ['id' => $holiday->id]);
            return $holiday;
        });

        $countTheCurrentHoliday = $holidays->count();
        return [
            'holidays' => $holidays,
            'holidayCount' => $countTheCurrentHoliday,
        ];
    }

    public function storeHoliday(array $data)
    {
        // Additional sanitization at service level
        $sanitizedData = $this->sanitizeData($data, [
            'name' => 'name',
            'date' => 'text',
            'recurring' => 'text',
            'length' => 'text',
        ]);

        $holiday = Holiday::create($sanitizedData);
        
        // Attach operating units if provided
        if (isset($data['operating_unit_ids']) && is_array($data['operating_unit_ids'])) {
            $holiday->operatingUnits()->attach($data['operating_unit_ids']);
        }
        
        return $holiday;
    }

    public function getHoliday(String $id)
    {
        $holiday = Holiday::with('operatingUnits')->where('id', $id)->first();
        return $holiday;
    }

    public function getOperatingUnits(){
        $operatingUnits = OperatingUnit::all();
        return $operatingUnits;
    }

    public function updateHoliday(String $id, array $data)
    {
        // Additional sanitization at service level
        $sanitizedData = $this->sanitizeData($data, [
            'name' => 'name',
            'date' => 'text',
            'recurring' => 'text',
            'length' => 'text',
        ]);

        $holiday = Holiday::where('id', $id)->first();
        if (!$holiday) {
            throw new \Exception('Holiday not found');
        }

          $holiday->update($sanitizedData);
          
          // Sync operating units if provided
        if (isset($data['operating_unit_ids'])) {
            $holiday->operatingUnits()->sync($data['operating_unit_ids']);
        }
        
        return $holiday;
    }

    public function deleteHoliday(String $id)
    {
        $holiday = Holiday::where('id', $id)->delete();
        return $holiday;
    }

    public function deleteBulkHoliday(array $ids)
    {
        $holidays = Holiday::whereIn('id', $ids)->delete();
        return $holidays;
    }
}
