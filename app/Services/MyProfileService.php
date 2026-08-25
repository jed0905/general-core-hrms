<?php

namespace App\Services;

use App\Models\Shift;
use App\Models\College;
use App\Models\Employee;
use App\Models\Position;
use App\Models\JobStatus;
use App\Models\Secondary;
use App\Models\Department;
use App\Models\Elementary;
use App\Models\SalaryStep;
use App\Models\Vocational;
use App\Models\Designation;
use App\Models\GraduateStudy;
use App\Models\OperatingUnit;
use App\Models\WorkExperience;
use App\Models\EmployeeReference;
use Illuminate\Http\UploadedFile;
use App\Models\EmployeeDesignation;
use App\Models\PersonalInformation;
use Illuminate\Support\Facades\Auth;
use App\Models\OtherInfoOrganization;
use App\Models\OtherInfoSpecialSkills;
use App\Models\CivilServiceEligibility;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\EmployeeResource;
use App\Http\Resources\MyProfileJobResource;
use App\Models\EmployeeAdditionalInformation;
use App\Models\OtherInfoNonAcademicDistinction;
use App\Http\Resources\MyProfilePersonalInformationResource;

class MyProfileService
{
    public function index()
    {
        $user = Auth::user();
        $employee = $user->employee;
    }

    public function getEmployeeJobDetails()
    {
        $user = Auth::user();

        $employee = Employee::with('personalInformation', 'subordinates.position.government_position')->where('id', $user->employee_id)->first();

        // Set Operating units based on authenticated user
        // Get all operating units if the logged in user is superadmin or hr director
        // Set the operating unit of the logged in user if not superadmin or hr director
        $operating_units = OperatingUnit::where('id', $employee->operating_unit_id)->get()->toArray();
        $departments = Department::all();

        $positions = Position::with('operating_unit')->get();
        $job_statuses = JobStatus::all();
        $shifts = Shift::all();
        $salary_steps = SalaryStep::all();
        $designations = Designation::with('operatingUnit')
            ->get();
        $employee_designations = EmployeeDesignation::with('designation', 'employee.personalInformation')
            ->where('employee_id', '!=', $employee->id)
            ->get();

        // $resource = new MyProfileJobResource($employee);
        // dd((new MyProfileJobResource($employee))->toArray(request()));
        // dd($resource->toArray(request()));
        return [
            'operating_units' => $operating_units,
            'departments' => $departments,
            'positions' => $positions,
            'job_statuses' => $job_statuses,
            'shifts' => $shifts,
            'salary_steps' => $salary_steps,
            'designations' => $designations,
            'employee_designations' => $employee_designations,
            'employee_job_details' => $employee
        ];
    }

    public function getEmployeePersonalInformation()
    {
        $user = Auth::user();
        $employeePersonalInformation = PersonalInformation::where('employee_id', $user->employee_id)->firstOrFail();

        return $employeePersonalInformation;
    }

    public function getMyProfileEducationalBackground(string $employee_id)
    {
        $elementaries = Elementary::where('employee_id', $employee_id)->get();
        $secondaries = Secondary::where('employee_id', $employee_id)->get();
        $vocationals = Vocational::where('employee_id', $employee_id)->get();
        $colleges = College::where('employee_id', $employee_id)->get();
        $graduate_studies = GraduateStudy::where('employee_id', $employee_id)->get();

        return [
            'elementaries' => $elementaries,
            'secondaries' => $secondaries,
            'vocationals' => $vocationals,
            'colleges' => $colleges,
            'graduate_studies' => $graduate_studies,
        ];
    }


    public function getMyProfileCivilServiceEligibilities(string $employee_id)
    {
        $eligibilities = CivilServiceEligibility::where('employee_id', $employee_id)->get();
        // dd($eligibilities);
        return $eligibilities;
    }

    public function getMyProfileWorkExperiences(string $employee_id)
    {
        $work_experiences = WorkExperience::where('employee_id', $employee_id)->get();
        return $work_experiences;
    }

    public function employee()
    {
        // employee table
        $user = Auth::user();
        $employee = Employee::where('id', $user->employee_id)->first();
        return $employee;
    }

    public function getEmployeeSpecialSkills()
    {
        $user = Auth::user();
        $employee = Employee::where('id', $user->employee_id)->first();
        $specialSkills = OtherInfoSpecialSkills::where('employee_id', $employee->id)->get();
        return $specialSkills;
    }

    public function getEmployeeNonAcademicDistinctions()
    {
        $user = Auth::user();
        $employee = Employee::where('id', $user->employee_id)->first();
        $nonAcademicDistinctions = OtherInfoNonAcademicDistinction::where('employee_id', $employee->id)->get();
        return $nonAcademicDistinctions;
    }

    public function getEmployeeOtherInfoOrganizations()
    {
        $user = Auth::user();
        $employee = Employee::where('id', $user->employee_id)->first();
        $memberships = OtherInfoOrganization::where('employee_id', $employee->id)->get();
        return $memberships;
    }

    public function getEmployeeAdditionalInformation()
    {
        $user = Auth::user();
        $employee = Employee::where('id', $user->employee_id)->first();
        $employeeAdditionalInformation = EmployeeAdditionalInformation::where('employee_id', $employee->id)->first();

        return $employeeAdditionalInformation;
    }

    public function getEmployeeReferences()
    {
        $user = Auth::user();
        $employee = Employee::where('id', $user->employee_id)->first();
        $employeeReferences = EmployeeReference::with('employee')
            ->where('employee_id', $employee->id)->get();
        return $employeeReferences;
    }

    public function updateEmployeePersonalInformation(array $request, string $employee_id)
    {
        $personalInformation = PersonalInformation::where('employee_id', $employee_id)->firstOrFail();

        $personalInformation->update($request);
    }

    // public function updateEmployeeSignature($file, string $employee_id)
    // {
    //     $personalInformation = PersonalInformation::where('employee_id', $employee_id)
    //         ->firstOrFail();

    //     // Delete old signature if exists
    //     if (
    //         $personalInformation->e_signature_path &&
    //         Storage::disk('public')->exists($personalInformation->e_signature_path)
    //     ) {
    //         Storage::disk('public')->delete($personalInformation->e_signature_path);
    //     }

    //     // Store new file
    //     $path = $file->store('signatures', 'public');

    //     // Save new path
    //     $personalInformation->update([
    //         'e_signature_path' => $path,
    //     ]);
    // }

    public function updateEmployeeSignature($file, string $employee_id)
    {
        $personalInformation = PersonalInformation::where('employee_id', $employee_id)
            ->firstOrFail();

        // Delete old signature if exists
        if (
            $personalInformation->e_signature_path &&
            Storage::disk('public')->exists($personalInformation->e_signature_path)
        ) {
            Storage::disk('public')->delete($personalInformation->e_signature_path);
        }

        // 1. Store original file
        $originalPath = $file->store('signatures/original', 'public');
        $input = storage_path('app/public/' . $originalPath);

        // 2. Prepare processed file path
        $filename = 'processed_' . basename($originalPath);
        $processedRelativePath = 'signatures/processed/' . $filename;
        $output = storage_path('app/public/' . $processedRelativePath);

        // Make sure directory exists
        if (!file_exists(dirname($output))) {
            mkdir(dirname($output), 0755, true);
        }

        // 3. Run ImageMagick
        $command = "convert \"$input\" -alpha set -fuzz 12% -transparent white -trim +repage -strip \"$output\"";
        exec($command, $outputLog, $status);

        // 4. Fallback if processing fails
        if ($status !== 0 || !file_exists($output)) {
            // Use original if magick fails
            $processedRelativePath = $originalPath;
        } else {
            // Optional: delete original after success
            Storage::disk('public')->delete($originalPath);
        }

        // 5. Save path (processed or fallback)
        $personalInformation->update([
            'e_signature_path' => $processedRelativePath,
        ]);
    }

    public function updateEmployeePersonalInformationFromPsa(array $request, string $employee_id)
    {
        $personalInformation = PersonalInformation::where('employee_id', $employee_id)
            ->firstOrFail();

        // If email already exists, remove email from the update payload
        if (!empty($personalInformation->email)) {
            unset($request['email']);
        }

        $personalInformation->update($request);
    }

    public function updateEmployeeFamilyBackground(array $data, string $employee_id)
    {
        // dd($data, $employee_id);
        $employee = Employee::findOrFail($employee_id);

        $familyBackground = $employee->familyBackground()->updateOrCreate(
            ['employee_id' => $employee->id],
            [
                'father_lastname' => $data['father_lastname'] ?? null,
                'father_firstname' => $data['father_firstname'] ?? null,
                'father_middlename' => $data['father_middlename'] ?? null,
                'father_suffix' => $data['father_suffix'] ?? null,
                'mother_lastname' => $data['mother_lastname'] ?? null,
                'mother_firstname' => $data['mother_firstname'] ?? null,
                'mother_middlename' => $data['mother_middlename'] ?? null,
            ]
        );

        if ($data['spouse_lastname'] != null) {
            $spouse = $employee->spouse()->updateOrCreate(
                ['employee_id' => $employee->id],
                [
                    'spouse_lastname' => $data['spouse_lastname'] ?? null,
                    'spouse_firstname' => $data['spouse_firstname'] ?? null,
                    'spouse_middlename' => $data['spouse_middlename'] ?? null,
                    'spouse_suffix' => $data['spouse_suffix'] ?? null,
                    'occupation' => $data['occupation'] ?? null,
                    'employer_business_name' => $data['employer_business_name'] ?? null,
                    'business_address' => $data['business_address'] ?? null,
                    'telephone_no' => $data['telephone_no'] ?? null,
                ]
            );
        }

        if ($data['children'] != null) {
            foreach ($data['children'] as $child) {
                $employee->children()->updateOrCreate(
                    [
                        'employee_id' => $employee_id,
                        'id' => $child['id'] ?? null, // condition (if child has an id, update)
                    ],
                    [
                        'fullname' => $child['fullname'],
                        'date_of_birth' => $child['date_of_birth'],
                    ]
                );
            }
        }
    }

    public function updateEmployeeSpecialSkills(array $request, string $employee_id)
    {

        // Get all submitted skill IDs to determine which existing skills to keep
        $submittedIds = collect($request['specialSkills'])
            ->filter(function ($skill) {
                return isset($skill['id']) && $skill['id'] !== null;
            })
            ->pluck('id')
            ->toArray();

        // Delete existing skills that are not in the submitted data
        OtherInfoSpecialSkills::where('employee_id', $employee_id)
            ->whereNotIn('id', $submittedIds)
            ->delete();

        // Process each submitted skill
        foreach ($request['specialSkills'] as $skill) {
            if (isset($skill['id']) && $skill['id'] !== null) {
                // Update existing skill
                OtherInfoSpecialSkills::where('id', $skill['id'])
                    ->update(['special_skill' => $skill['skill']]);
            } else {
                // Create new skill
                OtherInfoSpecialSkills::create([
                    'employee_id' => $employee_id,
                    'special_skill' => $skill['skill'],
                ]);
            }
        }

        // Return updated skills
        return OtherInfoSpecialSkills::where('employee_id', $employee_id)->get();
    }

    public function updateEmployeeNonAcademicDistinctions(array $request, string $employee_id)
    {
        // Get all submitted distinction IDs to determine which existing distinctions to keep
        $submittedIds = collect($request['nonAcademicDistinctions'])
            ->filter(function ($distinction) {
                return isset($distinction['id']) && $distinction['id'] !== null;
            })
            ->pluck('id')
            ->toArray();

        // Delete existing distinctions that are not in the submitted data
        OtherInfoNonAcademicDistinction::where('employee_id', $employee_id)
            ->whereNotIn('id', $submittedIds)
            ->delete();

        // Process each submitted distinction
        foreach ($request['nonAcademicDistinctions'] as $distinction) {
            if (isset($distinction['id']) && $distinction['id'] !== null) {
                // Update existing distinction
                OtherInfoNonAcademicDistinction::where('id', $distinction['id'])
                    ->update(['distinction' => $distinction['distinction']]);
            } else {
                // Create new distinction
                OtherInfoNonAcademicDistinction::create([
                    'employee_id' => $employee_id,
                    'distinction' => $distinction['distinction'],
                ]);
            }
        }

        // Return updated distinctions
        return OtherInfoNonAcademicDistinction::where('employee_id', $employee_id)->get();
    }

    public function updateEmployeeOtherInfoOrganizations(array $request, string $employee_id)
    {
        // Get all submitted distinction IDs to determine which existing distinctions to keep
        $submittedIds = collect($request['memberships'])
            ->filter(function ($distinction) {
                return isset($distinction['id']) && $distinction['id'] !== null;
            })
            ->pluck('id')
            ->toArray();

        // Delete existing distinctions that are not in the submitted data
        OtherInfoOrganization::where('employee_id', $employee_id)
            ->whereNotIn('id', $submittedIds)
            ->delete();

        // Process each submitted distinction
        foreach ($request['memberships'] as $distinction) {
            if (isset($distinction['id']) && $distinction['id'] !== null) {
                // Update existing distinction
                OtherInfoOrganization::where('id', $distinction['id'])
                    ->update(['organization_name' => $distinction['organization_name']]);
            } else {
                // Create new distinction
                OtherInfoOrganization::create([
                    'employee_id' => $employee_id,
                    'organization_name' => $distinction['organization_name'],
                ]);
            }
        }

        // Return updated distinctions
        return OtherInfoOrganization::where('employee_id', $employee_id)->get();
    }



    public function deleteEmployeeSpecialSkills(string $id)
    {
        $specialSkills = OtherInfoSpecialSkills::where('id', $id)->firstOrFail();
        $specialSkills->delete();
        return $specialSkills;
    }

    public function deleteEmployeeNonAcademicDistinctions(string $id)
    {
        $nonAcademicDistinctions = OtherInfoNonAcademicDistinction::where('id', $id)->firstOrFail();
        $nonAcademicDistinctions->delete();
        return $nonAcademicDistinctions;
    }

    public function deleteEmployeeOtherInfoOrganizations(string $id)
    {
        $organization = OtherInfoOrganization::where('id', $id)->firstOrFail();
        $organization->delete();
        return $organization;
    }

    /**
     * Delete a single employee reference
     */
    public function deleteEmployeeReference(string $id)
    {
        $reference = EmployeeReference::where('id', $id)->firstOrFail();
        $reference->delete();
        return $reference;
    }

    /**
     * Delete all references for an employee
     */
    public function deleteAllEmployeeReferences(string $employee_id)
    {
        return EmployeeReference::where('employee_id', $employee_id)->delete();
    }
}
