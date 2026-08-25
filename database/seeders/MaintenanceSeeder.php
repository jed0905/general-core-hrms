<?php

namespace Database\Seeders;

use App\Models\OperatingUnit;
use App\Models\Position;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class MaintenanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $operatingUnit = OperatingUnit::create([
            'prefix_id' => '2',
            'name' => 'Mid La Union Campus',
            'shortcut' => 'MLUC',
            'barangay' => 'Catbangen',
            'city_municipality' => 'San Fernando City',
            'province' => 'La Union',
            'zip' => '2500',
        ]);

        $department = $operatingUnit->department()->create([
            'name' => 'University Systems Development Office',
            'shortcut' => 'USDO',
        ]);

        $position = Position::create([
            'name' => 'Administrative Aide I',
            'shortcut' => 'AA I',
            'plantilla_number' => 'AAI-001',
        ]);

    }
}
