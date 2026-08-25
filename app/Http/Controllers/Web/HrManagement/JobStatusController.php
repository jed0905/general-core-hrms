<?php

namespace App\Http\Controllers\Web\HrManagement;

use App\Http\Requests\StoreJobStatusFormRequest;
use App\Http\Requests\UpdateJobStatusFormRequest;
use Exception;
use Inertia\Inertia;
use App\Models\JobStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use App\Http\Controllers\Controller;
use App\Services\EmployeeService;

class JobStatusController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $jobStatus = JobStatus::orderBy('created_at', 'asc')->get();

        // Add signed edit link for each job status
        $jobStatus->each(function ($jobStatus) {
            $jobStatus->edit_link = URL::signedRoute('hrmanagement.jobstructure.jobstatus.edit', ['id' => $jobStatus->id]);
        });

        return Inertia::render('app/HrManagement/JobStructure/JobStatus/Index', [
            'jobStatus' => $jobStatus,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('app/HrManagement/JobStructure/JobStatus/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreJobStatusFormRequest $request)
    {
        try {
            $validated = $request->validated();

            $job_status = JobStatus::create([
                'name' => $validated['name'],
            ]);

            return redirect()->route('hrmanagement.jobstructure.jobstatus.index');
        } catch (Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to create job status: ' . $e->getMessage()]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, string $id)
    {
        if (!$request->hasValidSignature()) {
            abort(403, 'Invalid or expired link');
        }

        $jobStatus = JobStatus::findOrFail($id);

        return Inertia::render('app/HrManagement/JobStructure/JobStatus/Edit', [
            'jobStatus' => $jobStatus,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateJobStatusFormRequest $request, string $id)
    {
        $jobstatus = JobStatus::findOrFail($id);

        try {
            $validated = $request->validated();

            $jobstatus->update($validated);

            return redirect()->route('hrmanagement.jobstructure.jobstatus.index');
        } catch (Exception $e) {

            return redirect()->back()->withErrors(['error' => 'Failed to update job status: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    
}
