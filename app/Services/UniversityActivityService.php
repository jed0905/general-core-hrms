<?php

namespace App\Services;

use App\Http\Filters\UniversityActivityFilter;
use App\Http\Resources\UniversityActivityResource;
use App\Models\OperatingUnit;
use App\Models\UniversityActivity;
use App\Models\UniversityActivityOperatingUnit;
use Illuminate\Support\Facades\URL;

class UniversityActivityService
{
    public function getUniversityActivities()
    {
        $universityActivities = fn() => UniversityActivityResource::collection(
            UniversityActivityFilter::get()
        );
        return $universityActivities;
    }

    public function getOperatingUnits()
    {
        $operatingUnits = OperatingUnit::get();
        return $operatingUnits;
    }

    public function storeUniversityActivity($data)
    {
        $universityActivity = UniversityActivity::create($data);
        // Attach operating units if provided
        if (isset($data['operating_unit_ids']) && is_array($data['operating_unit_ids'])) {
            $universityActivity->operatingUnits()->attach($data['operating_unit_ids']);
        }

        return $universityActivity;
    }

    public function getUniversityActivityById($id)
    {
        $universityActivity = UniversityActivity::with('operatingUnits')->findOrFail($id);
        return $universityActivity;
    }

    public function updateUniversityActivity($data, $id)
    {
        $universityActivity = UniversityActivity::findOrFail($id);
        $universityActivity->update($data);
        if (array_key_exists('operating_unit_ids', $data)) {
            $ids = is_array($data['operating_unit_ids']) ? $data['operating_unit_ids'] : [];
            $universityActivity->operatingUnits()->sync($ids);
        }

        return $universityActivity;
    }

    public function deleteUniversityActivity($id)
    {
        UniversityActivity::where('id', $id)->delete();
    }
}
