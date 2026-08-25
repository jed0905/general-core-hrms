<?php

namespace Database\Seeders;

use App\Models\Leave;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LeaveSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $leaves = [
            ['name' => 'Vacation Leave', 'shortcut' => 'VL'],
            ['name' => 'Mandatory/Forced Leave', 'shortcut' => 'FL'],
            ['name' => 'Sick Leave', 'shortcut' => 'SL'],
            ['name' => 'Maternity Leave', 'shortcut' => 'ML'],
            ['name' => 'Paternity Leave', 'shortcut' => 'PL'],
            ['name' => 'Special Privilege Leave', 'shortcut' => 'SPL'],
            ['name' => 'Solo Parent Leave', 'shortcut' => 'SPL'],
            ['name' => 'Study Leave', 'shortcut' => 'STL'],
            ['name' => 'Rehabilitation Privilege', 'shortcut' => 'RL'],
            ['name' => 'Special Leave Benefits for Women', 'shortcut' => 'SLW'],
            ['name' => 'Special Emergency (Calamity) Leave', 'shortcut' => 'SEL'],
            ['name' => 'Adoption Leave', 'shortcut' => 'AL'],
            ['name' => 'Others', 'shortcut' => 'OT'],
        ];

        foreach ($leaves as $leave) {
            Leave::create($leave);
        }
    }
}
