<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $operating_units = [
            [
                'prefix_id' => 1,
                'name' => 'North La Union Campus',
                'shortcut' => 'NLUC',
                'barangay' => 'Sapilang',
                'city_municipality' => 'Bacnotan',
                'province' => 'La Union',
                'zip' => '2515',
            ],
            [
                'prefix_id' => 2,
                'name' => 'Mid La Union Campus',
                'shortcut' => 'MLUC',
                'barangay' => 'Catbangen',
                'city_municipality' => 'City of San Fernando',
                'province' => 'La Union',
                'zip' => '2500',
            ],
            [
                'prefix_id' => 3,
                'name' => 'South La Union Campus',
                'shortcut' => 'SLUC',
                'barangay' => 'Consolacion',
                'city_municipality' => 'Agoo',
                'province' => 'La Union',
                'zip' => '2504',
            ],
            [
                'prefix_id' => 4,
                'name' => 'Open University System',
                'shortcut' => 'OUS',
                'barangay' => 'Catbangen',
                'city_municipality' => 'City of San Fernando',
                'province' => 'La Union',
                'zip' => '2500',
            ],
            [
                'prefix_id' => 5,
                'name' => 'Central Administration',
                'shortcut' => 'CA',
                'barangay' => 'Raois',
                'city_municipality' => 'Bacnotan',
                'province' => 'La Union',
                'zip' => '2515',
            ]
            // Add more operating units as needed
        ];

        foreach ($operating_units as $unit) {
            \App\Models\OperatingUnit::create($unit);
        }
    }
}
