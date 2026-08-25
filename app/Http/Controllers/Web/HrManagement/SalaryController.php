<?php

namespace App\Http\Controllers\Web\HrManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\SalaryScheduleFormRequest;
use App\Http\Requests\UploadSalaryFileRequest;
use App\Http\Resources\SalaryScheduleResource;
use App\Models\SalaryGrade;
use App\Models\SalarySchedule;
use App\Services\SalaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class SalaryController extends Controller
{
    public function index(SalaryService $salaryService)
    {
        $salary_schedules = $salaryService->index();

        return Inertia::render('app/HrManagement/JobStructure/Salary/Index', [
            'salary_schedules' => SalaryScheduleResource::collection($salary_schedules),
        ]);
    }

    // For the creation of salary schedules
    public function create()
    {
        return Inertia::render('app/HrManagement/JobStructure/Salary/Create');
    }

    public function edit(string $id, SalaryService $salaryService)
    {
        $schedule = $salaryService->edit($id);

        return Inertia::render('app/HrManagement/JobStructure/Salary/Edit', [
            'salary_schedule' => new SalaryScheduleResource($schedule),
        ]);
    }

    public function update(SalaryScheduleFormRequest $request, string $id, SalaryService $salaryService)
    {
        $schedule = $salaryService->update($id, $request->validated());
        return redirect()->route('hrmanagement.jobstructure.salary.index')->with('success', 'Salary schedule updated successfully.');
    }

    public function store(SalaryScheduleFormRequest $request, SalaryService $salaryService)
    {
        $schedule = $salaryService->store($request->validated());
        return redirect()->route('hrmanagement.jobstructure.salary.index')->with('success', 'Salary schedule created successfully.');
    }

    public function viewSalaryMatrix(string $id, SalaryService $salaryService)
    {
        $result = $salaryService->viewSalaryMatrix($id);

        // dd($result);

        return Inertia::render('app/HrManagement/JobStructure/Salary/View', [
            'salary_schedule' => new SalaryScheduleResource($result['schedule']),
            'salary_grades' => $result['matrix'],
        ]);
    }

    public function downloadTemplate(SalaryService $salaryService)
    {
        $csvContent = $salaryService->downloadTemplate();
        $fileName = 'salary_grades_template.csv';

        return response($csvContent)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename=\"$fileName\"");
    }

    /**
     * Upload salary grades from CSV/TXT file.
     * Logs input (file info), output (result summary), errors (validation/exception), and warnings (e.g. unknown grades).
     */
    public function upload(
        UploadSalaryFileRequest $request,
        $schedule,
        SalaryService $salaryService
    ) {
        $file = $request->file('file');

        Log::channel('input')->info('Salary upload: request received', [
            'schedule_id' => $schedule,
            'file_original_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'file_mime' => $file->getMimeType(),
            'client_ip' => $request->ip(),
        ]);

        try {
            // ✅ FIX: use correct service method name
            $result = $salaryService->uploadSalaryMatrix($file, $schedule);
        } catch (\Throwable $e) {
            Log::channel('error')->error('Salary upload: processing failed', [
                'schedule_id' => $schedule,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return redirect()->back()->with(
                'error',
                'Failed to process file: ' . $e->getMessage()
            );
        }

        Log::channel('output')->info('Salary upload: completed successfully', [
            'schedule_id' => $schedule,
            'rows_processed' => $result['rows_processed'] ?? 0,
            'grades_updated' => $result['grades_updated'] ?? 0,
            'warning_count' => count($result['warnings'] ?? []),
        ]);

        $message = 'Salary matrix uploaded successfully!';

        return redirect()->back()->with('success', $message);
    }

    public function updateStatus(Request $request, SalaryService $salaryService)
    {
        $id = $request->route('id');
        $isActive = $request->input('is_active');
        try {
            $schedule = $salaryService->updateStatus($id, $isActive);

            return redirect()->route('hrmanagement.jobstructure.salary.index')->with('success', 'Status updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update salary schedule status', [
                'schedule_id' => $id,
                'error_message' => $e->getMessage(),
            ]);

            return redirect()->route('hrmanagement.jobstructure.salary.index')->with('error', 'Failed to update status: ' . $e->getMessage());  
        }

    }
}
