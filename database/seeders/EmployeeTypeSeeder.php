<?php

namespace Database\Seeders;

use App\Models\EmployeeType;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class EmployeeTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        EmployeeType::truncate();

        $empTypes = ['TEACHING', 'NON-TEACHING'];

        foreach($empTypes as $empType){
            EmployeeType::create([
                'name' => $empType,
            ]);
        }
    }
}
