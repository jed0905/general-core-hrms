<?php

namespace Database\Seeders;

use App\Models\Shift;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class ShiftSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        
        Shift::create([
            'name' => 'Shift 1',
            'start_time' => '07:30:00',
            'end_time' => '16:30:00',
        ]);

        Shift::create([
            'name' => 'Shift 2',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);
    }
}
