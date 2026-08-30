<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {


        $superadminRole = Role::where('name', 'superadmin')->first();
        // Create superadmin user (no employee_id)
        $superadminUser = User::firstOrCreate(
            ['username' => 'superadmin'],
            [
                'password' => Hash::make('Administrator123'),
                'status' => 'active',
                'employee_id' => null,
            ]
        );

        // Assign role to superadmin user
        if (!$superadminUser->hasRole('superadmin')) {
            $superadminUser->assignRole($superadminRole);
        }
    }

}
