<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DevSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            JobStatusSeeder::class,
            RolesAndPermissionsSeeder::class,
            SuperAdminSeeder::class,
            // EmployeeTypeSeeder::class,
            SalarySeeder::class,
            ShiftSeeder::class,
            // UserSeeder::class,
            GovernmentPositionsSeeder::class,
            OrganizationSeeder::class,
            LeaveSeeder::class,
        ]);
    }
}
