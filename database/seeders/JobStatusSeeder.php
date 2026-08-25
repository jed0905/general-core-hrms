<?php

namespace Database\Seeders;

use App\Models\JobStatus;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class JobStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        

        $statuses = ['Permanent', 'Temporary', 'Contractual', 'Casual', 'Contract of Service', 'Job Order', 'Part Time', 'Coterminus'];

        foreach($statuses as $status){
            JobStatus::create([
                'name' => $status,
            ]);
        }
    }
}
