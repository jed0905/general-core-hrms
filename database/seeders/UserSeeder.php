<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $hr_director = Role::where('name', 'hr_director')->first();
        // Create hr director user
        $hrDirectorUser = User::firstOrCreate(
            ['username' => 'hr_director'],
            [
                'password' => Hash::make('password'),
                'status' => 'active',
                'employee_id' => 000001,
            ]
        );

        $employee = Role::where('name', 'employee')->first();
        $employeeUser = User::firstOrCreate(
            ['username' => 'employee'],
            [
                'password' => Hash::make('password'),
                'status' => 'active',
                'employee_id' => 000002,
            ]
        );

        // Assign role to hr director user
        if (!$hrDirectorUser->hasRole('hr_director')) {
            $hrDirectorUser->assignRole($hr_director);
        }

        if(!$employeeUser->hasRole('employee')){
            $employeeUser->assignRole($employee);
        }


    }
}
