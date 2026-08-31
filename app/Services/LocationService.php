<?php

namespace App\Services;

use App\Models\Location;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class LocationService
{
    public function getAllLocations(): Collection
    {
        return Location::query()
            ->orderByDesc('is_main')
            ->orderBy('city', 'asc')
            ->get();
    }

    public function storeLocation(array $data): Location
    {
        return DB::transaction(function () use ($data) {
            $isMain = filter_var($data['is_main'] ?? false, FILTER_VALIDATE_BOOLEAN);

            // Enforce single main location across all records
            if ($isMain) {
                Location::query()->update(['is_main' => false]);
            }

            $data['is_main'] = $isMain;

            return Location::create($data);
        });
    }

    public function updateLocation(Location $location, array $data): Location
    {
        return DB::transaction(function () use ($location, $data) {
            if (array_key_exists('is_main', $data)) {
                $isMain = filter_var($data['is_main'], FILTER_VALIDATE_BOOLEAN);

                // Enforce single main location excluding the current record
                if ($isMain) {
                    Location::where('id', '!=', $location->id)->update(['is_main' => false]);
                }

                $data['is_main'] = $isMain;
            }

            $location->update($data);
            return $location;
        });
    }

    public function deleteLocation(Location $location): bool
    {
        return $location->delete();
    }
}
