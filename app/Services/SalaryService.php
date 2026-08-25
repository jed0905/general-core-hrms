<?php

namespace App\Services;

use App\Models\SalaryGrade;
use App\Models\SalaryMatrix;
use App\Models\SalarySchedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class SalaryService
{
    public function index()
    {
        // Fetch the salary schedules with signed edit link
        $salarySchedules = SalarySchedule::all()->map(function ($schedule) {
            $schedule->view_link = URL::signedRoute('hrmanagement.jobstructure.salary.view', ['id' => $schedule->id]);
            $schedule->edit_link = URL::signedRoute('hrmanagement.jobstructure.salary.edit', ['id' => $schedule->id]);
            return $schedule;
        });

        return $salarySchedules;
    }

    public function edit($id)
    {
        $schedule = SalarySchedule::findOrFail($id);
        return $schedule;
    }

    public function store($data)
    {
        $schedule = SalarySchedule::create([
            'name' => $data['name'],
            'law_reference' => $data['law_reference'],
            'effective_from' => $data['effective_from'],
            'effective_to' => $data['effective_to'] ?? null,
        ]);

        return $schedule;
    }

    public function update($id, $data)
    {
        $schedule = SalarySchedule::findOrFail($id);
        $schedule->update([
            'name' => $data['name'],
            'law_reference' => $data['law_reference'],
            'effective_from' => $data['effective_from'],
            'effective_to' => $data['effective_to'] ?? null,
        ]);

        return $schedule;
    }

    public function viewSalaryMatrix($id)
    {
        $schedule = SalarySchedule::findOrFail($id);

        $grades = SalaryMatrix::with('salaryGrade')
            ->where('salary_schedule_id', $id)
            ->orderBy('salary_grade_id')
            ->orderBy('step_number')
            ->get();

        // Transform into matrix
        $matrix = [];

        foreach ($grades as $item) {
            $gradeId = $item->salaryGrade->id;
            $gradeName = $item->salaryGrade->salary_grade;

            if (!isset($matrix[$gradeId])) {
                $matrix[$gradeId] = [
                    'salary_grade_id' => $gradeId,
                    'salary_grade' => $gradeName,
                    'steps' => []
                ];
            }

            // Use step as column key
            $matrix[$gradeId]['steps'][$item->step_number] = $item->amount;
        }

        // Re-index array (optional but cleaner for frontend)
        $matrix = array_values($matrix);

        // dd($matrix);

        return [
            'schedule' => $schedule,
            'matrix' => $matrix,
        ];
    }

    public function downloadTemplate()
    {
        $salary_grades = SalaryGrade::with('salarySteps')->get();

        $csvHead = ['Salary Grade', 'Step 1', 'Step 2', 'Step 3', 'Step 4', 'Step 5', 'Step 6', 'Step 7', 'Step 8'];
        $content = implode(',', $csvHead) . "\n";

        foreach ($salary_grades as $grade) {
            $row = [$grade->salary_grade];
            for ($i = 1; $i <= 8; $i++) {
                $step = $grade->salarySteps->where('salary_step_no', $i)->first();
                $row[] = $step ? $step->amount : 0;
            }
            $content .= implode(',', $row) . "\n";
        }

        return $content;
    }

    public function uploadSalaryMatrix($file, $salaryScheduleId)
    {
        $rows = array_map('str_getcsv', file($file->getRealPath()));

        if (count($rows) <= 1) {
            throw new \Exception('CSV is empty or invalid.');
        }

        $header = array_map('trim', $rows[0]);

        // Remove header row
        unset($rows[0]);

        DB::beginTransaction();

        try {
            foreach ($rows as $row) {
                if (empty($row) || count($row) < 2) {
                    continue;
                }

                $salaryGradeValue = trim($row[0]);


                // Find salary grade
                $salaryGrade = SalaryGrade::where('salary_grade', $salaryGradeValue)->first();

                if (!$salaryGrade) {
                    throw new \Exception("Salary Grade {$salaryGradeValue} not found.");
                }

                // Loop through steps
                for ($i = 1; $i < count($row); $i++) {
                    $step = $i; // Step 1 = index 1
                    $amount = $row[$i];

                    if ($amount === null || $amount === '') {
                        continue;
                    }

                    SalaryMatrix::updateOrCreate(
                        [
                            'salary_schedule_id' => $salaryScheduleId,
                            'salary_grade_id' => $salaryGrade->id,
                            'step_number' => $step,
                        ],
                        [
                            'amount' => $amount
                        ]
                    );
                }
            }

            DB::commit();

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function updateStatus($id, $isActive)
    {
        $schedule = SalarySchedule::findOrFail($id);
        $schedule->is_active = $isActive;
        $schedule->save();

        return $schedule;
    }
}
