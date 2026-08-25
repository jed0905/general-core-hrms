<?php

namespace App\Http\Controllers\Web\SelfService;

use id;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Services\EmployeeService;
use App\Services\MyProfileService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\PositionsResource;
use App\Http\Resources\EmployeeChildrenResource;
use App\Http\Resources\EmployeeReferenceResource;
use App\Http\Resources\EmployeeJobDetailsResource;
use App\Http\Resources\EmployeeVoluntaryWorkResource;
use App\Http\Resources\EmployeeWorkExperienceResource;
use App\Http\Resources\EmployeeFamilyBackgroundResource;
use App\Http\Resources\EmployeeOtherInformationResource;
use App\Http\Requests\UpdateEmployeeOrgnanizationRequest;
use App\Http\Requests\UpdateEmployeeSpecialSkillsRequest;
use App\Http\Resources\EmployeeSpouseInformationResource;
use App\Http\Requests\StoreEmployeeOtherInformationRequest;
use App\Http\Resources\EmployeePersonalInformationResource;
use App\Http\Resources\MyProfilePersonalInformationResource;
use App\Http\Requests\UpdateEmployeeVoluntaryWorkFormRequest;
use App\Http\Resources\EmployeeEducationalBackgroundResource;
use App\Http\Requests\UpdateEmployeeWorkExperienceFormRequest;
use App\Http\Resources\EmployeeLearningAndDevelopmentResource;
use App\Http\Resources\EmployeeCivilServiceEligibilityResource;
use App\Http\Requests\UpdateEmployeeFamilyBackgroundFormRequest;
use App\Http\Requests\UpdateEmployeeNonAcademicDistinctionRequest;
use App\Http\Requests\UpdateEmployeePersonalInformationFormRequest;
use App\Http\Requests\UpdateEmployeeAdditionalInformationFormRequest;
use App\Http\Requests\UpdateEmployeeEducationalBackgroundFormRequest;
use App\Http\Requests\UpdateEmployeeLearningAndDevelopmentFormRequest;
use App\Http\Requests\UpdateEmployeeCivilServiceEligibilityFormRequest;

class MyProfileController extends Controller
{
    public function index(EmployeeService $employeeService, MyProfileService $myProfileService)
    {
        $user = Auth::user();
        $employee_id = $user->employee_id;

        $jobDetails = $employeeService->getEmployeeJobDetails($employee_id);
        $personalInformation = $employeeService->getEmployeePersonalInformation($employee_id);
        $myProfileFamilyBackground = $employeeService->getEmployeeFamilyBackground($employee_id);
        $employeeChildren = $employeeService->getEmployeeChildren($employee_id);
        $myProfileEducationalBackground = $employeeService->getEducationalBackground($employee_id);
        $myProfileCivilServiceEligibilities = $employeeService->getCivilServiceEligibilities($employee_id);
        $myProfileWorkExperiences = $employeeService->getWorkExperiences($employee_id);
        $myProfileVoluntaryWork = $employeeService->getVoluntaryWorks($employee_id);
        $myProfileLearningAndDevelopment = $employeeService->getLearningAndDevelopment($employee_id);
        $myProfileOtherInformation = $employeeService->getOtherInformation($employee_id);
        $myProfileAdditionalInformation = $employeeService->getEmployeeAdditionalInformation($employee_id);
        $myProfileReferences = $employeeService->getEmployeeReferences($employee_id);
        $specialSkills = $myProfileService->getEmployeeSpecialSkills();
        $nonAcademicDistinctions = $myProfileService->getEmployeeNonAcademicDistinctions();
        $otherInfoOrganizations = $myProfileService->getEmployeeOtherInfoOrganizations();

        return Inertia::render('app/SelfService/MyProfile/Index', [
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

            'employeePersonalInformation' => new EmployeePersonalInformationResource($personalInformation),
            'employeeFamilyBackground' => $myProfileFamilyBackground['familyBackground'] != null ? new EmployeeFamilyBackgroundResource($myProfileFamilyBackground['familyBackground']) : null,
            'employeeSpouseInformation' => $myProfileFamilyBackground['spouse'] != null ? new EmployeeSpouseInformationResource($myProfileFamilyBackground['spouse']) : null,
            'employeeChildren' => EmployeeChildrenResource::collection($employeeChildren),
            'employeeEducationalBackground' => new EmployeeEducationalBackgroundResource($myProfileEducationalBackground),
            'employeeCivilServiceEligibilities' => EmployeeCivilServiceEligibilityResource::collection($myProfileCivilServiceEligibilities),
            'employeeWorkExperiences' => EmployeeWorkExperienceResource::collection($myProfileWorkExperiences),
            'employeeVoluntaryWorks' => EmployeeVoluntaryWorkResource::collection($myProfileVoluntaryWork),
            'employeeLearningAndDevelopment' => EmployeeLearningAndDevelopmentResource::collection($myProfileLearningAndDevelopment),
            'employeeOtherInformation' => new EmployeeOtherInformationResource($myProfileOtherInformation),
            'employeeAdditionalInformation' => $myProfileAdditionalInformation,
            'employeeReferences' => EmployeeReferenceResource::collection($myProfileReferences),
            'employeeSpecialSkills' => $specialSkills,
            'employeeNonAcademicDistinctions' => $nonAcademicDistinctions,
            'employeeOtherInfoOrganizations' => $otherInfoOrganizations,
            // Personal Information
        ]);
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

    public function updatePersonalInformation(UpdateEmployeePersonalInformationFormRequest $request, string $id, MyProfileService $myProfileService)
    {
        try {
            $myProfileService->updateEmployeePersonalInformation($request->validated(), $id);

            return redirect()->back();
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update personal information: ' . $e->getMessage()]);
        }
    }

    public function uploadSignature(Request $request, MyProfileService $myProfileService)
    {
        $request->validate([
            'signature' => ['required', 'file', 'mimes:png', 'max:5120'], // max size of 5MB
        ]);

        try {
            $myProfileService->updateEmployeeSignature($request->file('signature'), $request->employee_id);

            return redirect()->back();
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update signature: ' . $e->getMessage()]);
        }
    }

    public function updatePersonalInformationFromPsa(Request $request, string $id, MyProfileService $myProfileService)
    {
        // dd("TEST");
        try {
            $validated = $request->validate([
                'firstname' => 'required|string|max:255',
                'middlename' => 'nullable|string|max:255',
                'lastname' => 'required|string|max:255',
                'suffix' => 'nullable|string|max:50',
                'email' => 'nullable',
                'date_of_birth' => 'required|date',
                'place_of_birth' => 'nullable|string|max:255',
                'blood_type' => 'nullable|string|max:3',
                'sex' => 'nullable|string|max:10',
                'civil_status' => 'nullable|string|max:50',
                'mobile_no' => 'nullable|string|max:20',
                'residential_barangay' => 'nullable|string|max:255',
                'residential_city_municipality' => 'nullable|string|max:255',
                'residential_province' => 'nullable|string|max:255',
                'residential_zip_code' => 'nullable|string|max:20',
            ]);

            // Append system-controlled field
            $validated['psa_verified'] = true;

            // dd($personal_information);

            // Pass updated array to service
            $myProfileService->updateEmployeePersonalInformationFromPsa($validated, $id);

            return redirect()->back();
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([
                'error' => 'Failed to update personal information: ' . $e->getMessage()
            ]);
        }
    }

    public function updateFamilyBackground(UpdateEmployeeFamilyBackgroundFormRequest $request, string $id, EmployeeService $employeeService)
    {
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
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update educational background: ' . $e->getMessage()]);
        }
    }

    public function updateCivilServiceEligibility(UpdateEmployeeCivilServiceEligibilityFormRequest $request, string $id, EmployeeService $employeeService)
    {
        // dd($request->validated());
        try {
            $employeeService->updateEmployeeCivilServiceEligibility($request->validated(), $id);

            return redirect()->back();
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update eligibility: ' . $e->getMessage()]);
        }
    }

    public function updateEmployeeWorkExperience(UpdateEmployeeWorkExperienceFormRequest $request, string $id, EmployeeService $employeeService)
    {
        // dd($request);
        try {
            $employeeService->updateEmployeeWorkExperience($request->validated(), $id);
            return redirect()->back();
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update work experience: ' . $e->getMessage()]);
        }
    }

    public function updateEmployeeVoluntaryWork(UpdateEmployeeVoluntaryWorkFormRequest $request, string $id, EmployeeService $employeeService)
    {
        try {
            $employeeService->updateEmployeeVoluntaryWork($request->validated(), $id);
            return redirect()->back();
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update voluntary work: ' . $e->getMessage()]);
        }
    }

    public function updateEmployeeLearningAndDevelopment(UpdateEmployeeLearningAndDevelopmentFormRequest $request, string $id, EmployeeService $employeeService)
    {
        try {
            $employeeService->updateEmployeeLearningAndDevelopment($request->validated(), $id);
            return redirect()->back();
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update learning and development: ' . $e->getMessage()]);
        }
    }

    public function updateSpecialSkills(UpdateEmployeeSpecialSkillsRequest $request, string $id, MyProfileService $myProfileService)
    {
        try {
            $myProfileService->updateEmployeeSpecialSkills($request->validated(), $id);
            return redirect()->back();
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update other information: ' . $e->getMessage()]);
        }
    }

    public function updateNonAcademicDistinctions(UpdateEmployeeNonAcademicDistinctionRequest $request, string $id, MyProfileService $myProfileService)
    {
        try {
            $myProfileService->updateEmployeeNonAcademicDistinctions($request->validated(), $id);
            return redirect()->back();
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update non-academic distinctions: ' . $e->getMessage()]);
        }
    }

    public function updateMemberships(UpdateEmployeeOrgnanizationRequest $request, string $id, MyProfileService $myProfileService)
    {

        try {
            $myProfileService->updateEmployeeOtherInfoOrganizations($request->validated(), $id);
            return redirect()->back();
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update other info organizations: ' . $e->getMessage()]);
        }
    }


    public function updateAdditionalInformation(Request $request, string $id, EmployeeService $employeeService)
    {

        try {
            $employeeService->updateEmployeeAdditionalInformation($request->all(), $id);
            return redirect()->back()->with('success', 'Additional information updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update additional information: ' . $e->getMessage()]);
        }
    }

    public function deleteSpecialSkills(string $id, MyProfileService $myProfileService)
    {
        try {
            $myProfileService->deleteEmployeeSpecialSkills($id);
            return redirect()->back();
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to delete special skills: ' . $e->getMessage()]);
        }
    }

    public function deleteNonAcademicDistinctions(string $id, MyProfileService $myProfileService)
    {
        try {
            $myProfileService->deleteEmployeeNonAcademicDistinctions($id);
            return redirect()->back();
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to delete non-academic distinctions: ' . $e->getMessage()]);
        }
    }

    public function deleteMemberships(string $id, MyProfileService $myProfileService)
    {
        try {
            $myProfileService->deleteEmployeeOtherInfoOrganizations($id);
            return redirect()->back();
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to delete membership: ' . $e->getMessage()]);
        }
    }

    public function deleteReference(string $id, MyProfileService $myProfileService)
    {
        try {
            $myProfileService->deleteEmployeeReference($id);
            return redirect()->back()->with('success', 'Reference deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to delete reference: ' . $e->getMessage()]);
        }
    }

    public function deleteAllReferences(string $employee_id, MyProfileService $myProfileService)
    {
        try {
            $myProfileService->deleteAllEmployeeReferences($employee_id);
            return redirect()->back()->with('success', 'All references deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to delete references: ' . $e->getMessage()]);
        }
    }
}
