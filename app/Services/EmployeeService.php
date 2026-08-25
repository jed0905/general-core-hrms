<?php

namespace App\Services;

use App\Http\Filters\EmployeeFilter;
use App\Http\Resources\EmployeeResource;
use App\Models\Child;
use App\Models\CivilServiceEligibility;
use App\Models\College;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Elementary;
use App\Models\Employee;
use App\Models\EmployeeAdditionalInformation;
use App\Models\EmployeeDesignation;
use App\Models\EmployeeMovement;
use App\Models\EmployeeReference;
use App\Models\FamilyBackground;
use App\Models\GraduateStudy;
use App\Models\JobStatus;
use App\Models\LearningDevelopment;
use App\Models\OperatingUnit;
use App\Models\OtherInfoNonAcademicDistinction;
use App\Models\OtherInfoOrganization;
use App\Models\OtherInfoSpecialSkills;
use App\Models\PersonalInformation;
use App\Models\Position;
use App\Models\SalarySchedule;
use App\Models\SalaryStep;
use App\Models\Secondary;
use App\Models\Shift;
use App\Models\Spouse;
use App\Models\User;
use App\Models\Vocational;
use App\Models\VoluntaryWork;
use App\Models\WorkExperience;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Auth;

class EmployeeService
{
    // Index page logic
    public function index()
    {

        $direction = 'ASC';
        if (request('direction') && request('direction') === 'Descending') {
            $direction = 'DESC';
        }

        $user = Auth::user();
        $operating_unit_id_of_employee = Employee::where('id', $user->employee_id)->pluck('operating_unit_id')->first();

        // Base query with relationships
        $query = Employee::with([
            'personalInformation',
            'department',
            'position',
            'jobStatus',
            'salaryGrade',
            'salaryStep',
            'employeeMovements',
        ]);
        // Apply EmployeeFilter
        $filter = new EmployeeFilter(request()->all());
        $query->whereNull('date_separated');
        $query = $filter->apply($query);

        // Restrict if not superadmin/hr_director
        if (! ($user->hasRole('superadmin') || $user->hasRole('hr_director'))) {
            $query->where(function ($q) use ($operating_unit_id_of_employee) {
                $q->where('operating_unit_id', $operating_unit_id_of_employee)
                    ->orWhere('detailed_at', $operating_unit_id_of_employee);
            });
        }

        $query->orderBy(
            PersonalInformation::select('lastname')
                ->whereColumn('employee_id', 'employees.id')
                ->limit(1),
            $direction
        );

        // $size = request()->input('size', 10);

        // Paginate + edit_link
        $employees = $query->paginate(request('size', 10))->through(function ($employee) use ($user) {
            $employee->edit_link = $employee->id === $user->employee_id
                ? route('self-service.my-profile.index')
                : URL::signedRoute('hrmanagement.employee.edit', ['id' => $employee->id]);

            return $employee;
        })->withQueryString();

        return EmployeeResource::collection($employees);
    }

    public function getEmployeeNameByEmployeeId(string $employee_id)
    {
        $employee = PersonalInformation::where('employee_id', $employee_id)->first();

        return $employee->lastname.' '.$employee->suffix ?? ''.', '.$employee->firstname.' '.$employee->middlename ?? '';
    }

    public function getOperatingUnits()
    {
        if (Auth::user()->hasRole('superadmin') || Auth::user()->hasRole('hr_director')) {
            $operatingUnits = OperatingUnit::get();
        } else {
            $operatingUnits = OperatingUnit::where('id', Auth::user()->employee->operating_unit_id)->get();
        }

        return $operatingUnits;
    }

    public function getDepartments()
    {
        if (Auth::user()->hasRole('superadmin') || Auth::user()->hasRole('hr_director')) {
            $departments = Department::get();
        } else {
            $departments = Department::where('operating_unit_id', Auth::user()->employee->operating_unit_id)->get();
        }

        return $departments;
    }

    public function getDepartmentsByOperatingUnit(string $operating_unit_id)
    {
        $departments = Department::where('operating_unit_id', $operating_unit_id)->get();

        return $departments;
    }

    public function createEmployeePage(array $request)
    {
        $user = Auth::user();
        $operating_unit_id_of_employee = Employee::where('id', $user->employee_id)->pluck('operating_unit_id')->first();
        $operatingUnits = null;

        if ($user->hasRole('superadmin') || $user->hasRole('hr_director')) {
            $operatingUnits = OperatingUnit::all();
        } else {
            $operatingUnits = OperatingUnit::where('id', $operating_unit_id_of_employee)->get();
        }

        return $operatingUnits;
    }

    public function getEmployeeJobDetails(string $id)
    {
        $user = Auth::user();
        $employee = Employee::with('personalInformation', 'subordinates.position.government_position', 'biometricIds', 'employeeMovements')->where('id', $id)->first();

        // Set Operating units based on authenticated user
        // Get all operating units if the logged in user is superadmin or hr director
        // Set the operating unit of the logged in user if not superadmin or hr director
        $operating_units = $user->hasRole('superadmin') || $user->hasRole('hr_director') ? OperatingUnit::all()->toArray() : OperatingUnit::where('id', $employee->operating_unit_id)->get()->toArray();
        $departments = Department::all();
        $detailed_at = OperatingUnit::all()->toArray();

        $positions = Position::with('operating_unit')->get();
        $job_statuses = JobStatus::all();
        $shifts = Shift::all();
        // $salary_steps = SalaryStep::all();
        $today = now();

        $schedule = SalarySchedule::where('is_active', true)
            ->where('effective_from', '<=', $today)
            ->where(function ($query) use ($today) {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $today);
            })
            ->orderByDesc('effective_from')
            ->first()
            ?? SalarySchedule::where('is_active', true)
                ->orderByDesc('effective_from')
                ->first();

        $salary_steps = SalaryStep::with([
            'salaryMatrix' => function ($query) use ($schedule) {
                $query->where('salary_schedule_id', $schedule->id);
            },
        ])
            ->orderBy('salary_grade_id')
            ->orderBy('salary_step_no')
            ->get();

        $designations = Designation::with('operatingUnit')
            ->get();


        $employee_designations = EmployeeDesignation::with([
            'designation',
            'employee.personalInformation',
        ])
            ->where('employee_id', '!=', $employee->id)
            ->whereDate('assumption_date', '<=', now()->toDateString())
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', now()->toDateString());
            })
            ->get();
        return [
            'operating_units' => $operating_units,
            'detailed_at' => $detailed_at,
            'departments' => $departments,
            'positions' => $positions,
            'job_statuses' => $job_statuses,
            'shifts' => $shifts,
            'salary_steps' => $salary_steps,
            'designations' => $designations,
            'employee_designations' => $employee_designations,
            'employee_job_details' => $employee,
        ];
    }

    public function getEmployeePersonalInformation(string $employee_id)
    {
        $employeePersonalInformation = PersonalInformation::where('employee_id', $employee_id)->firstOrFail();

        return $employeePersonalInformation;
    }

    public function getEmployeeFamilyBackground(string $employee_id)
    {
        $employeeFamilyBackground = FamilyBackground::where('employee_id', $employee_id)->first();
        $employeeSpouseInformation = Spouse::where('employee_id', $employee_id)->first();

        // dd($employeeSpouseInformation .''. $employeeSpouseInformation);
        return [
            'familyBackground' => $employeeFamilyBackground ?? null,
            'spouse' => $employeeSpouseInformation ?? null,
        ];
    }

    public function getEmployeeChildren(string $employee_id)
    {
        $children = Child::where('employee_id', $employee_id)->get();

        return $children;
    }

    public function getEducationalBackground(string $employee_id)
    {
        $elementaries = Elementary::where('employee_id', $employee_id)
            ->orderBy('period_to', 'desc')
            ->get();
        $secondaries = Secondary::where('employee_id', $employee_id)
            ->orderBy('period_to', 'desc')
            ->get();
        $vocationals = Vocational::where('employee_id', $employee_id)
            ->orderBy('period_to', 'desc')
            ->get();
        $colleges = College::where('employee_id', $employee_id)
            ->orderBy('period_to', 'desc')
            ->get();
        $graduate_studies = GraduateStudy::where('employee_id', $employee_id)
            ->orderBy('period_to', 'desc')
            ->get();

        return [
            'elementaries' => $elementaries,
            'secondaries' => $secondaries,
            'vocationals' => $vocationals,
            'colleges' => $colleges,
            'graduate_studies' => $graduate_studies,
        ];
    }

    public function getCivilServiceEligibilities(string $employee_id)
    {
        $eligibilities = CivilServiceEligibility::where('employee_id', $employee_id)->get();

        // dd($eligibilities);
        return $eligibilities;
    }

    public function getWorkExperiences(string $employee_id)
    {
        $work_experiences = WorkExperience::where('employee_id', $employee_id)
            ->orderByRaw("CASE WHEN `to` = 'PRESENT' THEN 1 ELSE 0 END DESC")
            ->orderBy('to', 'desc')
            ->get();

        return $work_experiences;
    }

    public function getVoluntaryWorks(string $employee_id)
    {
        $voluntary_works = VoluntaryWork::where('employee_id', $employee_id)
            ->orderBy('to', 'desc')
            ->get();

        return $voluntary_works;
    }

    public function getLearningAndDevelopment(string $employee_id)
    {
        $learning_and_development = LearningDevelopment::where('employee_id', $employee_id)
            ->orderBy('to', 'desc')
            ->get();

        return $learning_and_development;
    }

    public function getEmployeeSpecialSkills(string $employee_id)
    {
        $specialSkills = OtherInfoSpecialSkills::where('employee_id', $employee_id)->get();

        return $specialSkills;
    }

    public function getOtherInformation(string $employee_id)
    {
        $specialSkills = OtherInfoSpecialSkills::where('employee_id', $employee_id)->get();
        $nonAcademicDistinctions = OtherInfoNonAcademicDistinction::where('employee_id', $employee_id)->get();
        $memberships = OtherInfoOrganization::where('employee_id', $employee_id)->get();

        return [
            'special_skills' => $specialSkills,
            'non_academic_distinctions' => $nonAcademicDistinctions,
            'memberships' => $memberships,
        ];
    }

    public function getEmployeeAdditionalInformation(string $employee_id)
    {
        $employeeAdditionalInformation = EmployeeAdditionalInformation::where('employee_id', $employee_id)->first();

        return $employeeAdditionalInformation;
    }

    public function getEmployeeReferences(string $employee_id)
    {
        $employeeReferences = EmployeeReference::where('employee_id', $employee_id)->get();

        return $employeeReferences;
    }

    // public function storeInitialEmployeeDetails(array $personalInfoData, string $operating_unit_id)
    // {
    //     // dd($personalInfoData, $operating_unit_id);
    //     return DB::transaction(function () use ($personalInfoData, $operating_unit_id) {
    //         // 1. Generate employee_number safely with row lock
    //         $operatingUnitPrefix = OperatingUnit::where('id', $operating_unit_id)
    //             ->pluck('prefix_id')
    //             ->first();

    //         $yearPrefix = Carbon::parse($personalInfoData['date_hired'])->format('y');

    //         $lastSequence = DB::table('employees')
    //             ->where('employee_number', 'like', "{$operatingUnitPrefix}{$yearPrefix}%")
    //             ->lockForUpdate()
    //             ->orderBy('employee_number', 'desc')
    //             ->value('employee_number');

    //         $lastSeqNumber = $lastSequence ? intval(substr($lastSequence, -3)) : 0;
    //         $newSequence = str_pad($lastSeqNumber + 1, 3, '0', STR_PAD_LEFT);

    //         $employeeNumber = "{$operatingUnitPrefix}{$yearPrefix}{$newSequence}";

    //         // 2. Create employee record
    //         $employee = Employee::create([
    //             'operating_unit_id' => $operating_unit_id,
    //             'employee_number' => $employeeNumber,
    //             'date_hired' => $personalInfoData['date_hired'],
    //         ]);

    //         // 3. Create personal_information record first
    //         PersonalInformation::create([
    //             'employee_id' => $employee->id,
    //             'firstname' => $personalInfoData['firstname'],
    //             'middlename' => $personalInfoData['middlename'],
    //             'lastname' => $personalInfoData['lastname'],
    //             'suffix' => $personalInfoData['suffix'],
    //             'email' => $personalInfoData['email'],
    //             'date_of_birth' => $personalInfoData['date_of_birth'] ?? null,
    //         ]);

    //         return $employee;
    //     });
    // }

    public function storeInitialEmployeeDetails(array $personalInfoData, string $operating_unit_id)
    {
        return DB::transaction(function () use ($personalInfoData, $operating_unit_id) {

            // Generate employee number
            $employeeNumber = $this->generateEmployeeNumber(
                $operating_unit_id,
                $personalInfoData['date_hired']
            );

            // Create employee record
            $employee = Employee::create([
                'operating_unit_id' => $operating_unit_id,
                'employee_number' => $employeeNumber,
                'date_hired' => $personalInfoData['date_hired'],
            ]);

            // Create personal information record
            PersonalInformation::create([
                'employee_id' => $employee->id,
                'firstname' => $personalInfoData['firstname'],
                'middlename' => $personalInfoData['middlename'],
                'lastname' => $personalInfoData['lastname'],
                'suffix' => $personalInfoData['suffix'],
                'email' => $personalInfoData['email'],
                'date_of_birth' => $personalInfoData['date_of_birth'] ?? null,
            ]);

            return $employee;
        });
    }

    private function generateEmployeeNumber(string $operatingUnitId, string $date): string
    {
        $operatingUnitPrefix = OperatingUnit::where('id', $operatingUnitId)
            ->value('prefix_id');

        $yearPrefix = Carbon::parse($date)->format('y');

        $lastSequence = DB::table('employees')
            ->where('employee_number', 'like', "{$operatingUnitPrefix}{$yearPrefix}%")
            ->lockForUpdate()
            ->orderByDesc('employee_number')
            ->value('employee_number');

        $lastSeqNumber = $lastSequence
            ? intval(substr($lastSequence, -3))
            : 0;

        $newSequence = str_pad($lastSeqNumber + 1, 3, '0', STR_PAD_LEFT);

        return "{$operatingUnitPrefix}{$yearPrefix}{$newSequence}";
    }

    public function storePsaVerificationResponse(array $personalInfoData, string $operating_unit_id)
    {
        // dd($personalInfoData, $operating_unit_id);
        return DB::transaction(function () use ($personalInfoData, $operating_unit_id) {
            // 1. Generate employee_number safely with row lock
            $operatingUnitPrefix = OperatingUnit::where('id', $operating_unit_id)
                ->pluck('prefix_id')
                ->first();

            $yearPrefix = Carbon::parse($personalInfoData['date_hired'])->format('y');

            $lastSequence = DB::table('employees')
                ->where('employee_number', 'like', "{$operatingUnitPrefix}{$yearPrefix}%")
                ->lockForUpdate()
                ->orderBy('employee_number', 'desc')
                ->value('employee_number');

            $lastSeqNumber = $lastSequence ? intval(substr($lastSequence, -3)) : 0;
            $newSequence = str_pad($lastSeqNumber + 1, 3, '0', STR_PAD_LEFT);

            $employeeNumber = "{$operatingUnitPrefix}{$yearPrefix}{$newSequence}";

            // 2. Create employee record
            $employee = Employee::create([
                'operating_unit_id' => $operating_unit_id,
                'employee_number' => $employeeNumber,
                'date_hired' => $personalInfoData['date_hired'],
            ]);

            // 3. Create personal_information record first
            PersonalInformation::create([
                'employee_id' => $employee->id,
                'firstname' => $personalInfoData['firstname'],
                'middlename' => $personalInfoData['middlename'],
                'lastname' => $personalInfoData['lastname'],
                'suffix' => $personalInfoData['suffix'],
                'email' => $personalInfoData['email'],
                'date_of_birth' => $personalInfoData['date_of_birth'],
                'place_of_birth' => $personalInfoData['place_of_birth'],
                'blood_type' => $personalInfoData['blood_type'],
                'sex' => $personalInfoData['sex'],
                'civil_status' => $personalInfoData['civil_status'],
                'mobile_no' => $personalInfoData['mobile_no'],
                'residential_barangay' => $personalInfoData['residential_barangay'],
                'residential_city_municipality' => $personalInfoData['residential_city_municipality'],
                'residential_province' => $personalInfoData['residential_province'],
                'residential_zip_code' => $personalInfoData['residential_zip_code'],
                'psa_verified' => true,
            ]);

            return $employee;
        });
    }

    public function employeeEditDetails(string $id)
    {
        $employee = Employee::with('personalInformation', 'employeeDesignations')->get();

        return $employee;
    }

    public function uploadPicture($request)
    {
        // Get the employee by ID
        $employee = Employee::findOrFail($request->input('employee_id'));

        // Get the extension of the uploaded file
        $extension = $request->file('profile_picture')->getClientOriginalExtension();

        // Add timestamp to the filename -> employee_number_timestamp.extension
        $filename = $employee->employee_number.'.'.$extension;

        // dd($filename);

        // Store the image with custom filename in storage/app/public/profile_pictures
        $path = $request->file('profile_picture')->storeAs(
            'profile_pictures',
            $filename,
            'public'
        );

        // Save path to the employee's photo column
        $employee->photo = $path;
        $employee->save();
    }

    public function updateEmployeeJobDetails(array $data, string $id)
    {
        // dd($data, $id);
        $employee = Employee::with('user')->findOrFail($id);
        // dd($data);

        $employee->update([
            'date_hired' => $data['date_hired'],
            'biometrics_id' => $data['biometrics_id'],
            'detailed_at' => $data['detailed_at'],
            'operating_unit_id' => $data['operating_unit_id'],
            'department_id' => $data['department_id'],
            'employee_type' => $data['employee_type'],
            'position_id' => $data['position_id'],
            'job_status_id' => $data['job_status_id'],
            'salary_step_id' => $data['salary_step_id'],
            'custom_hourly_rate' => $data['custom_hourly_rate'],
            'custom_daily_rate' => $data['custom_daily_rate'],
            'parenthetical_title' => $data['parenthetical_title'],
            'immediate_supervisor_id' => $data['immediate_supervisor_id'],
            'immediate_supervisor_designation' => $data['immediate_supervisor_designation'],
            'higher_supervisor_id' => $data['higher_supervisor_id'],
            'higher_supervisor_designation' => $data['higher_supervisor_designation'],
        ]);

        // Handle biometrics updates
        if (! empty($data['biometrics_to_delete']) && is_array($data['biometrics_to_delete'])) {
            // Delete specified biometric IDs
            $employee->biometricIds()->whereIn('id', $data['biometrics_to_delete'])->delete();
        }

        if (! empty($data['biometrics_to_add']) && is_array($data['biometrics_to_add'])) {
            // Add new biometric IDs (filter out duplicates and empty values)
            $uniqueBiometricIds = collect($data['biometrics_to_add'])
                ->filter(fn ($id) => ! empty($id))
                ->unique()
                ->map(function ($biometricId) use ($employee) {
                    return [
                        'employee_id' => $employee->id,
                        'biometric_id' => $biometricId,
                    ];
                })
                ->values();

            // Only create if they don't already exist
            foreach ($uniqueBiometricIds as $biometricData) {
                $employee->biometricIds()->firstOrCreate(
                    ['biometric_id' => $biometricData['biometric_id']],
                    $biometricData
                );
            }
        }

        // dd($data['designation']);

        // Handle designation updates
        if (! empty($data['designation']) && is_array($data['designation'])) {
            // Clear old ones
            $employee->employeeDesignations()->delete();

            // Insert only unique new ones
            $uniqueDesignations = collect($data['designation'])
                ->unique(function ($designation) {
                    return implode('|', [
                        $designation['designation_id'],
                        $designation['assumption_date'] ?? '',
                        $designation['end_date'] ?? '',
                    ]);
                })
                ->map(function ($designation) use ($employee) {
                    return [
                        'employee_id' => $employee->id,
                        'designation_id' => $designation['designation_id'],
                        'assumption_date' => $designation['assumption_date'] ?? null,
                        'end_date' => $designation['end_date'] ?? null,
                    ];
                })
                ->values();

            $employee->employeeDesignations()->createMany($uniqueDesignations);

            // Step 1: reset employees supervised by this employee
            Employee::where('immediate_supervisor_id', $employee->id)->update([
                'immediate_supervisor_id' => null,
            ]);

            Employee::where('higher_supervisor_id', $employee->id)->update([
                'higher_supervisor_id' => null,
            ]);

            // Step 2: reassign based on new designations
            foreach ($employee->employeeDesignations as $designation) {
                Employee::where('immediate_supervisor_designation', $designation->designation_id)
                    ->update(['immediate_supervisor_id' => $employee->id]);

                Employee::where('higher_supervisor_designation', $designation->designation_id)
                    ->update(['higher_supervisor_id' => $employee->id]);
            }

            // ✅ If there is at least one designation, give permission
            foreach ($employee->user as $user) {
                $user->givePermissionTo(['leave.recommend', 'leave.view']);
            }

            // ✅ Check for special designations → give leave.approve
            $specialDesignations = ['Chancellor', 'Executive Director', 'University President'];

            $hasSpecial = $employee->employeeDesignations()
                ->whereHas('designation', function ($q) use ($specialDesignations) {
                    $q->whereIn('name', $specialDesignations);
                })
                ->exists();

            if ($hasSpecial) {
                foreach ($employee->user as $user) {
                    $user->givePermissionTo('leave.approve');
                }
            } else {
                foreach ($employee->user as $user) {
                    if ($user->hasPermissionTo('leave.approve')) {
                        $user->revokePermissionTo('leave.approve');
                    }
                }
            }
        } else {
            // If designations is empty, delete all
            $employee->employeeDesignations()->delete();
            // ❌ Remove permission if no designation
            if ($employee->user) {
                foreach ($employee->user as $user) {
                    if ($user->hasAnyPermission(['leave.recommend', 'leave.view'])) {
                        $user->revokePermissionTo(['leave.recommend', 'leave.view']);
                    }
                }
            }
        }

        return $employee;
    }

    public function updateEmployeePersonalInformation(array $data, string $id)
    {
        // Find the employee by ID
        $personalInformation = PersonalInformation::where('employee_id', $id)->firstOrFail();

        // Update the personal information
        $personalInformation->update($data);
    }

    public function updatePsaVerificationResponse(array $personalInfoData, string $id)
    {
        // Update the personal information
        $personalInformation = PersonalInformation::where('employee_id', $id)->firstOrFail();
        // dd($personalInformation);
        $personalInformation->update([
            'firstname' => $personalInfoData['first_name'],
            'middlename' => $personalInfoData['middle_name'],
            'lastname' => $personalInfoData['last_name'],
            'suffix' => $personalInfoData['suffix'],
            'email' => $personalInfoData['email'],
            'date_of_birth' => $personalInfoData['birth_date'],
            'place_of_birth' => $personalInfoData['place_of_birth'],
            'sex' => $personalInfoData['sex'],
            'blood_type' => $personalInfoData['blood_type'],
            'civil_status' => $personalInfoData['marital_status'],
            'residential_barangay' => $personalInfoData['barangay'],
            'residential_city_municipality' => $personalInfoData['municipality'],
            'residential_province' => $personalInfoData['province'],
            'residential_zip_code' => $personalInfoData['postal_code'],
            'mobile_no' => $personalInfoData['mobile_number'],
        ]);
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

        if (! empty($data['children'] && $data['children'][0]['fullname'] != null)) {
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

    public function updateEmployeeEducationalBackground(array $data, string $employee_id)
    {
        $employee = Employee::findOrFail($employee_id);

        $levels = [
            'elementary' => Elementary::class,
            'secondary' => Secondary::class,
            'vocational' => Vocational::class,
            'college' => College::class,
            'graduate_studies' => GraduateStudy::class,
        ];

        foreach ($levels as $level => $model) {
            if (! isset($data[$level]) || ! is_array($data[$level])) {
                continue;
            }

            foreach ($data[$level] as $school) {
                // Remove empty values
                $filtered = array_filter($school, fn ($v) => ! is_null($v) && $v !== '');

                if (! empty($filtered)) {
                    $model::updateOrCreate(
                        ['id' => $school['id'] ?? null, 'employee_id' => $employee_id],
                        [
                            'employee_id' => $employee_id,
                            'name_of_school' => $school['name_of_school'],
                            'degree_course' => $school['degree_course'],
                            'period_from' => $school['period_from'],
                            'period_to' => $school['period_to'],
                            'highest_level' => $school['highest_level'],
                            'year_graduated' => $school['year_graduated'],
                            'academic_award' => $school['academic_award'],
                        ]
                    );
                }
            }
        }
    }

    public function updateEmployeeCivilServiceEligibility(array $data, string $employee_id)
    {
        $employee = Employee::findOrFail($employee_id);

        foreach ($data['eligibilities'] as $eligibility) {
            // Skip if eligibility field is blank or null
            if (empty($eligibility['eligibility'])) {
                continue;
            }

            $employee->civilServiceEligibility()->updateOrCreate(
                [
                    // Only use 'id' if it exists, otherwise rely on employee_id + eligibility
                    'id' => $eligibility['id'] ?? null,
                ],
                [
                    'employee_id' => $employee->id,
                    'eligibility' => $eligibility['eligibility'],
                    'rating' => $eligibility['rating'] ?? null,
                    'date_of_examination' => $eligibility['date_of_examination'] ?? null,
                    'place_of_examination' => $eligibility['place_of_examination'] ?? null,
                    'license_no' => $eligibility['license_no'] ?? null,
                    'date_of_validity' => $eligibility['date_of_validity'] ?? null,
                ]
            );
        }
    }

    public function updateEmployeeWorkExperience(array $data, string $employee_id)
    {
        $employee = Employee::findOrFail($employee_id);

        // dd($data);

        foreach ($data['workExperiences'] as $experience) {
            // Skip if eligibility field is blank or null
            if (empty($experience['from'])) {
                continue;
            }

            $employee->workExperience()->updateOrCreate(
                [
                    'id' => $experience['id'],
                ],
                [
                    'employee_id' => $employee_id,
                    'from' => $experience['from'] ?? null,
                    'to' => $experience['to'] ?? null,
                    'position_title' => $experience['position_title'] ?? null,
                    'department_agency' => $experience['department_agency'] ?? null,
                    'monthly_salary' => $experience['monthly_salary'] ?? null,
                    'salary_grade' => $experience['salary_grade'] ?? null,
                    'status_of_appointment' => $experience['status_of_appointment'] ?? null,
                    'government_service' => $experience['government_service'] ?? null,
                ]
            );
        }
    }

    public function updateEmployeeVoluntaryWork(array $data, string $employee_id)
    {

        $employee = Employee::findOrFail($employee_id);

        foreach ($data['voluntaryWorks'] as $voluntary) {
            // Skip if eligibility field is blank or null
            if (empty($voluntary['from'])) {
                continue;
            }

            $employee->voluntaryWork()->updateOrCreate(
                [
                    'id' => $voluntary['id'],
                ],
                [
                    'employee_id' => $employee_id,
                    'name_of_organization' => $voluntary['name_of_organization'] ?? null,
                    'from' => $voluntary['from'] ?? null,
                    'to' => $voluntary['to'] ?? null,
                    'hours' => $voluntary['hours'] ?? null,
                    'position' => $voluntary['position'] ?? null,
                ]
            );
        }
    }

    public function updateEmployeeLearningAndDevelopment(array $data, string $employee_id)
    {
        $employee = Employee::findOrFail($employee_id);

        foreach ($data['learningDevelopment'] as $ld) {
            // Skip if eligibility field is blank or null
            if (empty($ld['title'])) {
                continue;
            }

            $employee->learningAndDevelopment()->updateOrCreate(
                [
                    'id' => $ld['id'],
                ],
                [
                    'employee_id' => $employee_id,
                    'title' => $ld['title'] ?? null,
                    'from' => $ld['from'] ?? null,
                    'to' => $ld['to'] ?? null,
                    'training_hours' => $ld['training_hours'] ?? null,
                    'type_of_ld' => $ld['type_of_ld'] ?? null,
                    'conducted_by' => $ld['conducted_by' ?? null],
                ]
            );
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

    public function updateEmployeeAdditionalInformation(array $data, string $employee_id)
    {

        $employee = Employee::findOrFail($employee_id);

        // Use updateOrCreate for additional information
        $employee->additionalInformation()->updateOrCreate(
            ['employee_id' => $employee->id],
            [
                'consanguinity_third_degree_yes' => $data['consanguinityThirdDegree']['yes'],
                'consanguinity_third_degree_no' => $data['consanguinityThirdDegree']['no'],
                'consanguinity_fourth_degree_yes' => $data['consanguinityFourthDegree']['yes'],
                'consanguinity_fourth_degree_no' => $data['consanguinityFourthDegree']['no'],
                'consanguinity_details' => $data['consanguinityDetails'],
                'administrative_offense_yes' => $data['administrativeOffense']['yes'],
                'administrative_offense_no' => $data['administrativeOffense']['no'],
                'administrative_offense_details' => $data['administrativeOffenseDetails'],
                'criminal_charge_yes' => $data['criminalCharge']['yes'],
                'criminal_charge_no' => $data['criminalCharge']['no'],
                'criminal_charge_date_filed' => $data['criminalChargeDateFiled'],
                'criminal_charge_status' => $data['criminalChargeStatus'],
                'conviction_yes' => $data['conviction']['yes'],
                'conviction_no' => $data['conviction']['no'],
                'conviction_details' => $data['convictionDetails'],
                'separation_from_service_yes' => $data['separationFromService']['yes'],
                'separation_from_service_no' => $data['separationFromService']['no'],
                'separation_from_service_details' => $data['separationFromServiceDetails'],
                'election_candidate_yes' => $data['electionCandidate']['yes'],
                'election_candidate_no' => $data['electionCandidate']['no'],
                'election_candidate_details' => $data['electionCandidateDetails'],
                'resigned_for_election_yes' => $data['resignedForElection']['yes'],
                'resigned_for_election_no' => $data['resignedForElection']['no'],
                'resigned_for_election_details' => $data['resignedForElectionDetails'],
                'immigrant_status_yes' => $data['immigrantStatus']['yes'],
                'immigrant_status_no' => $data['immigrantStatus']['no'],
                'immigrant_status_country' => $data['immigrantStatusCountry'],
                'indigenous_group_yes' => $data['indigenousGroup']['yes'],
                'indigenous_group_no' => $data['indigenousGroup']['no'],
                'indigenous_group_specify' => $data['indigenousGroupSpecify'],
                'person_with_disability_yes' => $data['personWithDisability']['yes'],
                'person_with_disability_no' => $data['personWithDisability']['no'],
                'person_with_disability_id_no' => $data['personWithDisabilityIdNo'],
                'solo_parent_yes' => $data['soloParent']['yes'],
                'solo_parent_no' => $data['soloParent']['no'],
                'solo_parent_specify' => $data['soloParentSpecify'],
            ],
        );

        // $employee->additionalInformation()->updateOrCreate($data);

        // Update employee government ID information
        $employee->update([
            'gov_issued_id' => $data['govIdType'],
            'gov_id_number' => $data['govIdNumber'],
            'date_issue' => $data['govIdDateIssued'],
            'place_issue' => $data['govIdPlaceIssued'],
        ]);

        // Delete existing references and create new ones to avoid duplicates
        $employee->references()->delete();

        $employeeReferences = collect($data['references'])->map(function ($reference) use ($employee) {
            return EmployeeReference::create([
                'employee_id' => $employee->id,
                'name' => $reference['name'],
                'address' => $reference['address'],
                'tel_no' => $reference['telNo'],
            ]);
        });

        return [
            'additionalInformation' => $employee->additionalInformation,
            'employeeReferences' => $employeeReferences,
            'employee' => true, // Employee update was successful
        ];
    }

    /* These are the delete functions for the different
        tabs inside the Employee Edit Mode
    */
    public function deleteEmployeeDesignation(string $id)
    {
        $employeeDesignation = EmployeeDesignation::findOrFail($id);

        $employeeDesignation->delete();
    }

    public function deleteEmployeeChild(string $id)
    {
        $child = Child::findOrFail($id);
        $child->delete();
    }

    public function deleteEmployeeEducationalBackground(string $id, string $level)
    {
        $levels = [
            'elementary' => Elementary::class,
            'secondary' => Secondary::class,
            'vocational' => Vocational::class,
            'college' => College::class,
            'graduate_studies' => GraduateStudy::class,
        ];

        if (! isset($levels[$level])) {
            abort(400, 'Invalid level provided.');
        }

        $model = $levels[$level];
        $school = $model::findOrFail($id);
        $school->delete();
    }

    public function deleteCivilServiceEligibility(string $id)
    {
        $eligibility = CivilServiceEligibility::findOrFail($id);

        $eligibility->delete();
    }

    public function deleteEmployeeWorkExperience(string $id)
    {
        $work_experience = WorkExperience::findOrFail($id);

        $work_experience->delete();
    }

    public function deleteEmployeeVoluntaryWork(string $id)
    {
        $voluntary_work = VoluntaryWork::findOrFail($id);

        $voluntary_work->delete();
    }

    public function deleteEmployeeLearningAndDevelopment(string $id)
    {
        $learning_and_development = LearningDevelopment::findOrFail($id);

        $learning_and_development->delete();
    }

    // For API consumer
    public function getEmployeeByEmployeeNumber(string $employee_number)
    {
        $employee = Employee::with(
            'personalInformation',
            'position.government_position',
            'operatingUnit',
            'department'
        )
            ->where('employee_number', $employee_number)
            ->first();

        $data = [
            'employee_id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'first_name' => $employee->personalInformation->firstname,
            'middle_name' => $employee->personalInformation->middlename,
            'last_name' => $employee->personalInformation->lastname,
            'suffix' => $employee->personalInformation->suffix,
            'email' => $employee->personalInformation->email,
            'designations' => $employee->employeeDesignations->map(function ($designation) {
                return [
                    'designation_id' => $designation->designation_id,
                    'designation_name' => $designation->designation ? $designation->designation->name : null,
                    'assumption_date' => $designation->assumption_date,
                ];
            }),
            'position' => $employee->position ? $employee->position->government_position->name : null,
            'operating_unit' => $employee->operatingUnit ? $employee->operatingUnit->name : null,
            'department' => $employee->department ? $employee->department->name : null,

        ];

        return $data;
    }

    public function getEmployeeByEmail(string $email)
    {
        $employee = PersonalInformation::with('employee.position.government_position', 'employee.operatingUnit', 'employee.department')
            ->where('email', $email)
            ->firstOrFail()
            ->employee;

        $data = [
            'employee_id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'first_name' => $employee->personalInformation->firstname,
            'middle_name' => $employee->personalInformation->middlename,
            'last_name' => $employee->personalInformation->lastname,
            'suffix' => $employee->personalInformation->suffix,
            'email' => $employee->personalInformation->email,
            'designations' => $employee->employeeDesignations->map(function ($designation) {
                return [
                    'designation_id' => $designation->designation_id,
                    'designation_name' => $designation->designation ? $designation->designation->name : null,
                    'assumption_date' => $designation->assumption_date,
                ];
            }),
            'position' => $employee->position ? $employee->position->government_position->name : null,
            'operating_unit' => $employee->operatingUnit ? $employee->operatingUnit->name : null,
            'department' => $employee->department ? $employee->department->name : null,

        ];

        return $data;
    }

    public function updateEmploymentStatus(array $validated)
    {
        // dd($validated);
        return DB::transaction(function () use ($validated) {

            $employee = Employee::findOrFail($validated['employee_id']);
            $type = $validated['movement_type'];

            $movementData = [
                'employee_id' => $employee->id,
                'movement_type' => $type,
                'effective_date' => $validated['effective_date'],
            ];

            if ($type === 'termination') {
                $movementData['reason'] = $validated['reason'] ?? null;
            }

            if ($type === 'transfer') {

                $newEmployeeNumber = $this->generateEmployeeNumber(
                    $validated['new_operating_unit_id'],
                    $validated['effective_date']
                );

                $movementData['old_employee_number'] = $employee->employee_number;
                $movementData['new_employee_number'] = $newEmployeeNumber;

                $movementData['previous_operating_unit_id'] = $employee->operating_unit_id;
                $movementData['new_operating_unit_id'] = $validated['new_operating_unit_id'];

                $movementData['previous_department_id'] = $employee->department_id;
                $movementData['new_department_id'] = null;

                if (! empty($employee->position_id)) {
                    $movementData['previous_position_id'] = $employee->position_id;

                    if (! empty($validated['transfer_position'])) {
                        $movementData['new_position_id'] = $employee->position_id;
                    } else {
                        $movementData['new_position_id'] = null;
                    }
                }
            }

            $movement = EmployeeMovement::create($movementData);

            if ($validated['effective_date'] <= now()->toDateString()) {

                if ($type === 'transfer') {

                    $newPositionId = $employee->position_id;

                    if (! empty($employee->position_id)) {

                        if (! empty($validated['transfer_position'])) {
                            DB::table('positions')
                                ->where('id', $employee->position_id)
                                ->update([
                                    'operating_unit_id' => $validated['new_operating_unit_id'],
                                ]);
                        } else {
                            $newPositionId = null;
                        }
                    }

                    $employee->update([
                        'operating_unit_id' => $validated['new_operating_unit_id'],
                        'employee_number' => $movement->new_employee_number,
                        'department_id' => null,
                        'position_id' => $newPositionId,
                    ]);
                }

                if (in_array($type, ['resignation', 'retirement', 'termination'])) {
                    User::where('employee_id', $employee->id)
                        ->update(['status' => false]);

                    $employee->update([
                        'date_separated' => $validated['effective_date'],
                        'separation_reason' => $type,
                    ]);
                }
            }

            return $employee->fresh();
        });
    }
}
