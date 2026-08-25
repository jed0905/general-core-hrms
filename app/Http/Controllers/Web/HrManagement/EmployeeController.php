<?php

namespace App\Http\Controllers\Web\HrManagement;

use App\Http\Requests\UpdateEmploymentStatusFormRequest;
use Exception;
use Inertia\Inertia;
use App\Models\Shift;
use App\Models\SubUnit;
use App\Models\Employee;
use App\Models\Position;
use Nette\Schema\Expect;
use App\Models\JobStatus;
use App\Models\Department;
use App\Models\SalaryStep;
use App\Models\Designation;
use App\Models\SalaryGrade;
use Illuminate\Http\Request;
use App\Models\OperatingUnit;
use App\Services\EmployeeService;
use Spatie\Permission\Models\Role;
use App\Models\EmployeeDesignation;
use Illuminate\Support\Facades\URL;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\EmployeeReportExport;
use App\Services\EmployeeReportService;
use App\Http\Resources\PositionsResource;
use App\Http\Resources\EmployeeChildrenResource;
use App\Http\Resources\EmployeeReferenceResource;
use App\Http\Resources\EmployeeJobDetailsResource;
use App\Http\Resources\EmployeeVoluntaryWorkResource;
use App\Http\Resources\EmployeeWorkExperienceResource;
use App\Http\Resources\EmployeeFamilyBackgroundResource;
use App\Http\Resources\EmployeeSpouseInformationResource;
use App\Http\Requests\UpdateEmployeeJobDetailsFormRequest;
use App\Http\Resources\EmployeePersonalInformationResource;
use App\Http\Requests\StoreInitialEmployeeInformationRequest;
use App\Http\Requests\UpdateEmployeeVoluntaryWorkFormRequest;
use App\Http\Resources\EmployeeEducationalBackgroundResource;
use App\Http\Requests\UpdateEmployeeWorkExperienceFormRequest;
use App\Http\Resources\EmployeeLearningAndDevelopmentResource;
use App\Http\Resources\EmployeeCivilServiceEligibilityResource;
use App\Http\Requests\UpdateEmployeeFamilyBackgroundFormRequest;
use App\Http\Requests\UpdateEmployeePersonalInformationFormRequest;
use App\Http\Requests\UpdateEmployeeAdditionalInformationFormRequest;
use App\Http\Requests\UpdateEmployeeEducationalBackgroundFormRequest;
use App\Http\Requests\UpdateEmployeeLearningAndDevelopmentFormRequest;
use App\Http\Requests\UpdateEmployeeCivilServiceEligibilityFormRequest;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(EmployeeService $employeeService)
    {
        $employees = $employeeService->index();
        $roles = Role::where('name', '!=', 'superadmin')->get();
        $jobStatuses = JobStatus::get();
        $operatingUnits = $employeeService->getOperatingUnits();

        $operating_unit = request()->input('operating_unit');
        $departments = $operating_unit ?
            $employeeService->getDepartmentsByOperatingUnit($operating_unit)
            : [];

        $filter = fn() => request()->only('search', 'department', 'employment_status', 'employee_type', 'size', 'direction', 'operating_unit');

        return Inertia::render('app/HrManagement/Employee/Index', [
            'employees' => $employees,
            'roles' => $roles,
            'jobStatuses' => $jobStatuses,
            'operatingUnits' => $operatingUnits,
            'departments' => $departments,
            'filter' => $filter,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request, EmployeeService $employeeService)
    {
        $operatingUnits = $employeeService->createEmployeePage($request->all());

        return Inertia::render('app/HrManagement/Employee/Create', [
            'operating_units' => isset($operatingUnits) ? $operatingUnits : null,
        ]);
    }

    public function store(StoreInitialEmployeeInformationRequest $request, EmployeeService $employeeService)
    {
        $validated = $request->validated();

        $personal_information = [
            'firstname' => $validated['firstName'],
            'middlename' => $validated['middleName'],
            'lastname' => $validated['lastName'],
            'suffix' => $validated['suffix'],
            'email' => $validated['email'],
            'date_of_birth' => $validated['birthdate'],
            'date_hired' => $validated['date_hired'],
        ];

        $operating_unit_id = $validated['operating_unit_id'];

        $employee = $employeeService->storeInitialEmployeeDetails(
            $personal_information,  // personal info fields
            $operating_unit_id               // operating unit id
        );

        // Generate a signed edit URL
        $signedEditLink = URL::signedRoute('hrmanagement.employee.edit', [
            'id' => $employee->id
        ]);
        return redirect($signedEditLink);
    }

    public function storePsaVerificationResponse(Request $request, EmployeeService $employeeService)
    {
        // dd($request->all());

        $validated = $request->validate([
            'firstName' => 'required|string|max:255',
            'middleName' => 'nullable|string|max:255',
            'lastName' => 'required|string|max:255',
            'suffix' => 'nullable|string|max:50',
            'email' => 'nullable',
            'date_hired' => 'required|date',
            'birthdate' => 'required|date',
            'place_of_birth' => 'nullable|string|max:255',
            'blood_type' => 'nullable|string|max:3',
            'sex' => 'nullable|string|max:10',
            'civil_status' => 'nullable|string|max:50',
            'mobile_number' => 'nullable|string|max:20',
            'barangay' => 'nullable|string|max:255',
            'municipality' => 'nullable|string|max:255',
            'province' => 'nullable|string|max:255',
            'zip_code' => 'nullable|string|max:20',
            'operating_unit_id' => 'required|exists:operating_units,id',
        ]);

        $personal_information = [
            'firstname' => $validated['firstName'],
            'middlename' => $validated['middleName'],
            'lastname' => $validated['lastName'],
            'suffix' => $validated['suffix'],
            'email' => $validated['email'],
            'date_hired' => $validated['date_hired'],
            'date_of_birth' => $validated['birthdate'],
            'place_of_birth' => $validated['place_of_birth'],
            'blood_type' => $validated['blood_type'],
            'sex' => $validated['sex'],
            'civil_status' => $validated['civil_status'],
            'mobile_no' => $validated['mobile_number'] == strtoupper("n/a") ? null : $validated['mobile_number'],
            'residential_barangay' => $validated['barangay'],
            'residential_city_municipality' => $validated['municipality'],
            'residential_province' => $validated['province'],
            'residential_zip_code' => $validated['zip_code']
        ];

        // dd($personal_information);

        $operating_unit_id = $validated['operating_unit_id'];

        $employee = $employeeService->storePsaVerificationResponse(
            $personal_information,  // personal info fields
            $operating_unit_id               // operating unit id
        );

        // Generate a signed edit URL
        $signedEditLink = URL::signedRoute('hrmanagement.employee.edit', [
            'id' => $employee->id
        ]);
        return redirect($signedEditLink);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id, EmployeeService $employeeService)
    {
        // For Employee Name
        $employeeName = $employeeService->getEmployeeNameByEmployeeId($id);

        // For Job Form
        $jobDetails = $employeeService->getEmployeeJobDetails($id);
        // For Personal Information Form
        $employeePersonalInformation = $employeeService->getEmployeePersonalInformation($id);
        // For Family background form
        $employeeFamilyBackground = $employeeService->getEmployeeFamilyBackground($id);
        $employeeChildren = $employeeService->getEmployeeChildren($id);
        // For Civil Service Eligibility Form
        $employeeCivilServiceEligibilities = $employeeService->getCivilServiceEligibilities($id);
        $employeeEducationalBackground = $employeeService->getEducationalBackground($id);
        // For Work Experience Form
        $employeeWorkExperiences = $employeeService->getWorkExperiences($id);
        // For Voluntary Work Form
        $employeeVoluntaryWorks = $employeeService->getVoluntaryWorks($id);
        // For Learning and Development Form
        $employeeLearningAndDevelopment = $employeeService->getLearningAndDevelopment($id);

        $employeeAdditionalInformation = $employeeService->getEmployeeAdditionalInformation($id);
        $employeeReferences = $employeeService->getEmployeeReferences($id);

        // dd($jobDetails['employee_designations']);

        return Inertia::render('app/HrManagement/Employee/Edit', [
            'employeeName' => $employeeName,

            // Job Form
            'employeeJobDetails' => new EmployeeJobDetailsResource($jobDetails['employee_job_details']),
            'operatingUnits' => $jobDetails['operating_units'] ?? null,
            'detailed_at' => $jobDetails['detailed_at'] ?? null,
            'departments' => $jobDetails['departments'],
            'positions' => PositionsResource::collection($jobDetails['positions']),
            'jobStatuses' => $jobDetails['job_statuses'],
            'shifts' => $jobDetails['shifts'],
            'salarySteps' => $jobDetails['salary_steps'],
            'designations' => $jobDetails['designations'],
            'employeeDesignations' => $jobDetails['employee_designations'],

            'employeePersonalInformation' => new EmployeePersonalInformationResource($employeePersonalInformation),
            'employeeFamilyBackground' => $employeeFamilyBackground['familyBackground'] != null ? new EmployeeFamilyBackgroundResource($employeeFamilyBackground['familyBackground']) : null,
            'employeeSpouseInformation' => $employeeFamilyBackground['spouse'] != null ? new EmployeeSpouseInformationResource($employeeFamilyBackground['spouse']) : null,
            'employeeChildren' => EmployeeChildrenResource::collection($employeeChildren),
            'employeeEducationalBackground' => new EmployeeEducationalBackgroundResource($employeeEducationalBackground),
            'employeeCivilServiceEligibilities' => EmployeeCivilServiceEligibilityResource::collection($employeeCivilServiceEligibilities),
            'employeeWorkExperiences' => EmployeeWorkExperienceResource::collection($employeeWorkExperiences),
            'employeeVoluntaryWorks' => EmployeeVoluntaryWorkResource::collection($employeeVoluntaryWorks),
            'employeeLearningAndDevelopment' => EmployeeLearningAndDevelopmentResource::collection($employeeLearningAndDevelopment),
            'employeeAdditionalInformation' => $employeeAdditionalInformation,
            'employeeReferences' => EmployeeReferenceResource::collection($employeeReferences),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function updateJobDetails(UpdateEmployeeJobDetailsFormRequest $request, string $id, EmployeeService $employeeService)
    {
        try {
            $employeeService->updateEmployeeJobDetails($request->validated(), $id);

            return redirect()->back();
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update employee details: ' . $e->getMessage()]);
        }
    }

    public function uploadPicture(Request $request, EmployeeService $employeeService)
    {
        $request->validate([
            'profile_picture' => 'required|image|mimes:jpg,jpeg,png|max:10240',
            'employee_id' => 'required|exists:employees,id',
        ]);

        try {
            $employeeService->uploadPicture($request);

            // dd($employee);

            return redirect()->back();
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to upload picture: ' . $e->getMessage()]);
        }
    }

    public function updatePersonalInformation(UpdateEmployeePersonalInformationFormRequest $request, string $id, EmployeeService $employeeService)
    {
        try {
            // Update the employee's personal information
            $employeeService->updateEmployeePersonalInformation($request->validated(), $id);

            return redirect()->back()->with('success', 'Personal information updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update personal information: ' . $e->getMessage()]);
        }
    }

    public function updateFamilyBackground(UpdateEmployeeFamilyBackgroundFormRequest $request, string $id, EmployeeService $employeeService)
    {
        // dd($request->all());
        try {
            // Update the employee's family background
            $employeeService->updateEmployeeFamilyBackground($request->validated(), $id);

            return redirect()->back()->with('success', 'Family background updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update family background: ' . $e->getMessage()]);
        }
    }

    public function updateEmployeeEducationalBackground(UpdateEmployeeEducationalBackgroundFormRequest $request, string $id, EmployeeService $employeeService)
    {
        try {
            $employeeService->updateEmployeeEducationalBackground($request->validated(), $id);

            return redirect()->back();
        } catch (Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update educational background: ' . $e->getMessage()]);
        }
    }

    public function updateCivilServiceEligibility(UpdateEmployeeCivilServiceEligibilityFormRequest $request, string $id, EmployeeService $employeeService)
    {
        // dd($request->validated());
        try {
            $employeeService->updateEmployeeCivilServiceEligibility($request->validated(), $id);

            return redirect()->back();
        } catch (Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update eligibility: ' . $e->getMessage()]);
        }
    }

    public function updateEmployeeWorkExperience(UpdateEmployeeWorkExperienceFormRequest $request, string $id, EmployeeService $employeeService)
    {
        // dd($request);
        try {
            $employeeService->updateEmployeeWorkExperience($request->validated(), $id);
            return redirect()->back();
        } catch (Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update work experience: ' . $e->getMessage()]);
        }
    }

    public function updateEmployeeVoluntaryWork(UpdateEmployeeVoluntaryWorkFormRequest $request, string $id, EmployeeService $employeeService)
    {
        try {
            $employeeService->updateEmployeeVoluntaryWork($request->validated(), $id);
            return redirect()->back();
        } catch (Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update voluntary work: ' . $e->getMessage()]);
        }
    }

    public function updateEmployeeLearningAndDevelopment(UpdateEmployeeLearningAndDevelopmentFormRequest $request, string $id, EmployeeService $employeeService)
    {
        try {
            $employeeService->updateEmployeeLearningAndDevelopment($request->validated(), $id);
            return redirect()->back();
        } catch (Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update learning and development: ' . $e->getMessage()]);
        }
    }

    public function deleteEmployeeDesignation(string $id, EmployeeService $employeeService)
    {
        try {
            $employeeService->deleteEmployeeDesignation($id);

            return redirect()->back();
        } catch (Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to delete employee designation: ' . $e->getMessage()]);
        }
    }

    public function deleteEmployeeChild(string $id, EmployeeService $employeeService)
    {
        try {
            $employeeService->deleteEmployeeChild($id);

            return redirect()->back();
        } catch (Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to delete child: ' . $e->getMessage()]);
        }
    }

    public function deleteEmployeeEducationalBackground(string $id, string $level, EmployeeService $employeeService)
    {
        try {
            $employeeService->deleteEmployeeEducationalBackground($id, $level);
            return redirect()->back();
        } catch (Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to delete school: ' . $e->getMessage()]);
        }
    }

    public function deleteCivilServiceEligibility(string $id, EmployeeService $employeeService)
    {
        try {
            $employeeService->deleteCivilServiceEligibility($id);

            return redirect()->back();
        } catch (Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to delete eligibility: ' . $e->getMessage()]);
        }
    }

    public function deleteEmployeeWorkExperience(string $id, EmployeeService $employeeService)
    {
        try {
            $employeeService->deleteEmployeeWorkExperience($id);

            return redirect()->back();
        } catch (Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to delete eligibility: ' . $e->getMessage()]);
        }
    }

    public function deleteEmployeeVoluntaryWork(string $id, EmployeeService $employeeService)
    {
        try {
            $employeeService->deleteEmployeeVoluntaryWork($id);

            return redirect()->back();
        } catch (Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to delete voluntary work: ' . $e->getMessage()]);
        }
    }

    public function deleteEmployeeLearningAndDevelopment(string $id, EmployeeService $employeeService)
    {
        try {
            $employeeService->deleteEmployeeLearningAndDevelopment($id);

            return redirect()->back();
        } catch (Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to delete learning and development: ' . $e->getMessage()]);
        }
    }

    public function updateAdditionalInformation(UpdateEmployeeAdditionalInformationFormRequest $request, string $id, EmployeeService $employeeService)
    {
        try {
            $employeeService->updateEmployeeAdditionalInformation($request->validated(), $id);
            return redirect()->back()->with('success', 'Additional information updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update additional information: ' . $e->getMessage()]);
        }
    }

    public function report(Request $request, EmployeeReportService $employeeReportService)
    {
        $data = $employeeReportService->index($request);
        // dd($data['departments']);

        // dd($data['operating_units']);

        return Inertia::render('app/HrManagement/Employee/Report', [
            'operatingUnits' => $data['operatingUnits'],
            'departments' => $data['departments'],
            'jobStatus' => $data['jobStatus'],
        ]);
    }

    public function generateReport(Request $request)
    {
        $fileName = 'employee_report_' . now()->format('Ymd_His') . '.xlsx';
        return Excel::download(new EmployeeReportExport($request), $fileName);
    }

    // FOR API CONSUMPTION
    public function getEmployeeByEmployeeNumber(string $employee_number, EmployeeService $employeeService)
    {
        return $employeeService->getEmployeeByEmployeeNumber($employee_number);
    }

    public function updateEmployementStatus(UpdateEmploymentStatusFormRequest $request, EmployeeService $employeeService)
    {

        try{
            $validated = $request->validated();

            $terminate = $employeeService->updateEmploymentStatus($validated);
            if($terminate){
                return redirect()->back()->with('success', 'Employment status updated successfully');
            }
        }catch(Exception $e){
            return redirect()->back()->withErrors(['error' => 'Failed to update employment status: ' . $e->getMessage()]);
        }


    }
}
