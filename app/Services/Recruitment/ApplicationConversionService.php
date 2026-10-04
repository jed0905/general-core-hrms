<?php

namespace App\Services\Recruitment;

use App\Models\Applicant;
use App\Models\ApplicantDocument;
use App\Models\Application;
use App\Models\ApplicationConversion;
use App\Models\ApplicationConversionDocument;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\EmployeeMovementType;
use App\Models\JobOffer;
use App\Models\JobOfferEvent;
use App\Models\User;
use App\Services\EmployeeDocumentService;
use App\Services\EmployeeMovementService;
use App\Services\EmployeeService;
use App\Services\UserService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Throwable;

/**
 * Recruitment → Core HR hand-off for an application whose offer was ACCEPTED.
 *
 * External candidate: one transaction creates the employee through
 * EmployeeService (which issues the employee number from the existing
 * sequence and copies education and work experience), records the Hiring
 * movement through EmployeeMovementService, copies the chosen documents through
 * EmployeeDocumentService (private disk, random names), optionally creates a
 * login through UserService, and writes the immutable conversion record.
 *
 * Internal candidate (applicant linked to an employee, or a person already
 * converted through another application): nothing is created in Core HR. The
 * conversion links the existing employee; any change of assignment is a normal
 * Employee Movement recorded by HR.
 *
 * Locks, in this order and before any plain read: application → accepted offer
 * → applicant (→ existing employee). Unique indexes on application and offer
 * back this up. Any failure rolls everything back (the employee number too,
 * since the sequence row is updated in the same transaction) and removes files
 * already copied. Vacancy openings are untouched: selection reserved them.
 */
class ApplicationConversionService
{
    private const INACTIVE_EMPLOYEE = ['archived', 'terminated'];

    /** Core HR column limits (employees / work_experiences / educations strings). */
    private const MAX = 191;

    public function __construct(
        protected EmployeeService $employees,
        protected EmployeeMovementService $movements,
        protected EmployeeDocumentService $documents,
        protected UserService $users,
        protected OfferApprovalService $offerEvents
    ) {}

    /**
     * What the conversion page needs to know (read-only).
     *
     * @return array{offer: ?JobOffer, problem: ?string, existingEmployee: ?Employee, documents: Collection}
     */
    public function eligibility(Application $application): array
    {
        $offer = JobOffer::where('application_id', $application->id)->where('status', JobOffer::STATUS_ACCEPTED)->first();
        $applicant = Applicant::find($application->applicant_id);
        $existing = $this->existingEmployee($applicant);

        $problem = match (true) {
            ApplicationConversion::where('application_id', $application->id)->exists() => 'This candidate has already been converted.',
            ! $offer => 'Only candidates with an accepted offer can be converted.',
            $application->isTerminal() => "This application is {$application->status}.",
            $existing && in_array($existing->status, self::INACTIVE_EMPLOYEE, true) => "The linked employee record is {$existing->status}; rehiring is not handled by recruitment conversion.",
            default => null,
        };

        return [
            'offer' => $offer,
            'problem' => $problem,
            'existingEmployee' => $existing,
            'documents' => $existing ? collect() : $this->transferableDocuments($application),
        ];
    }

    /**
     * @param  array{emp_sex?: string, supervisor_id?: ?int, document_ids?: array<int>, create_user_account?: bool, username?: ?string, password?: ?string}  $data
     */
    public function convert(Application $application, array $data, User $actor): ApplicationConversion
    {
        $copiedFiles = [];

        try {
            return DB::transaction(function () use ($application, $data, $actor, &$copiedFiles) {
                $application = Application::whereKey($application->id)->lockForUpdate()->firstOrFail();
                $offer = JobOffer::where('application_id', $application->id)->where('status', JobOffer::STATUS_ACCEPTED)->lockForUpdate()->first();
                $applicant = Applicant::whereKey($application->applicant_id)->lockForUpdate()->firstOrFail();
                $existing = $this->existingEmployee($applicant, lock: true);

                $this->authorize($actor, ! $existing && ! empty($data['create_user_account']));

                if (ApplicationConversion::where('application_id', $application->id)->exists()) {
                    throw ValidationException::withMessages(['conversion' => ['This candidate has already been converted.']]);
                }
                if (! $offer) {
                    throw ValidationException::withMessages(['conversion' => ['Only accepted offers can be converted.']]);
                }
                if ($application->isTerminal()) {
                    throw ValidationException::withMessages(['conversion' => ["This application is {$application->status}."]]);
                }

                $conversion = $existing
                    ? $this->linkExisting($application, $offer, $applicant, $existing, $actor)
                    : $this->createEmployee($application, $offer, $applicant, $data, $actor, $copiedFiles);

                $this->offerEvents->event($offer, JobOfferEvent::CONVERTED, JobOffer::STATUS_ACCEPTED, JobOffer::STATUS_ACCEPTED, $actor, $this->summary($conversion));
                $application->update(['last_activity_at' => now()]);

                return $conversion;
            });
        } catch (ValidationException|AuthorizationException $e) {
            $this->removeFiles($copiedFiles);
            throw $e;
        } catch (UniqueConstraintViolationException $e) {
            $this->removeFiles($copiedFiles);
            if (str_contains($e->getMessage(), 'application_conversions')) {
                throw ValidationException::withMessages(['conversion' => ['This candidate has already been converted.']]);
            }
            if (str_contains($e->getMessage(), 'username')) {
                throw ValidationException::withMessages(['username' => ['This username is already taken.']]);
            }
            throw $this->failure($application, $e);
        } catch (Throwable $e) {
            $this->removeFiles($copiedFiles);
            throw $this->failure($application, $e);
        }
    }

    // ----------------------------------------------------------------- paths

    protected function createEmployee(Application $application, JobOffer $offer, Applicant $applicant, array $data, User $actor, array &$copiedFiles): ApplicationConversion
    {
        $notices = [];
        $hiring = EmployeeMovementType::where('code', 'hiring')->where('is_active', true)->first();
        if (! $hiring) {
            throw ValidationException::withMessages(['conversion' => ['The "Hiring" employee movement type is missing or inactive. No conversion was saved.']]);
        }

        $employee = $this->employees->createEmployee($this->employeeData($applicant, $offer, $data, $notices) + [
            'education' => $this->education($applicant, $notices),
            'work_experience' => $this->workExperience($applicant, $notices),
        ]);

        $movement = $this->movements->createMovement([
            'employee_id' => $employee->id,
            'movement_type_id' => $hiring->id,
            'effective_date' => $offer->proposed_start_date->toDateString(),
            'reference_number' => $offer->offer_number,
            'reason' => 'Hired through recruitment',
            'remarks' => "Application {$application->application_number}, accepted offer {$offer->offer_number}.",
            'changed_fields' => [],
        ], $actor);

        $documents = $this->copyDocuments($application, $employee, $data['document_ids'] ?? [], $actor, $notices, $copiedFiles);

        $user = null;
        if (! empty($data['create_user_account'])) {
            $user = $this->users->createUser([
                'username' => $data['username'],
                'password' => $data['password'],
                'employee_id' => $employee->id,
                // Only the standard employee role, never HR or admin roles.
                'roles' => Role::where('name', 'employee')->where('guard_name', 'web')->exists() ? ['employee'] : [],
            ]);
        }

        $applicant->update(['converted_employee_id' => $employee->id]);

        $conversion = ApplicationConversion::create([
            'application_id' => $application->id,
            'applicant_id' => $applicant->id,
            'job_offer_id' => $offer->id,
            'employee_id' => $employee->id,
            'conversion_type' => ApplicationConversion::TYPE_NEW_EMPLOYEE,
            'employee_number' => $employee->employee_number,
            'employee_movement_id' => $movement->id,
            'start_date' => $offer->proposed_start_date->toDateString(),
            'user_account' => $user ? ApplicationConversion::ACCOUNT_CREATED : ApplicationConversion::ACCOUNT_NONE,
            'user_id' => $user?->id,
            'education_copied' => $employee->education()->count(),
            'work_experience_copied' => $employee->workExperience()->count(),
            'documents_copied' => count($documents),
            'notices' => $notices ?: null,
            'converted_by' => $actor->id,
            'converted_at' => now(),
        ]);

        foreach ($documents as $applicantDocumentId => $employeeDocument) {
            ApplicationConversionDocument::create([
                'application_conversion_id' => $conversion->id,
                'applicant_document_id' => $applicantDocumentId,
                'employee_document_id' => $employeeDocument->id,
            ]);
        }

        return $conversion;
    }

    protected function linkExisting(Application $application, JobOffer $offer, Applicant $applicant, Employee $employee, User $actor): ApplicationConversion
    {
        if (in_array($employee->status, self::INACTIVE_EMPLOYEE, true)) {
            throw ValidationException::withMessages(['conversion' => ["The linked employee record is {$employee->status}; rehiring is not handled by recruitment conversion."]]);
        }

        if ($applicant->converted_employee_id === null && $applicant->employee_id === null) {
            $applicant->update(['converted_employee_id' => $employee->id]);
        }

        $user = User::where('employee_id', $employee->id)->orderBy('id')->first();

        return ApplicationConversion::create([
            'application_id' => $application->id,
            'applicant_id' => $applicant->id,
            'job_offer_id' => $offer->id,
            'employee_id' => $employee->id,
            'conversion_type' => ApplicationConversion::TYPE_EXISTING_EMPLOYEE,
            'employee_number' => $employee->employee_number,
            'employee_movement_id' => null,
            'start_date' => $offer->proposed_start_date->toDateString(),
            'user_account' => $user ? ApplicationConversion::ACCOUNT_EXISTING : ApplicationConversion::ACCOUNT_NONE,
            'user_id' => $user?->id,
            'notices' => ['Existing employee: nothing was created or changed in Core HR. Record any change of position, department or status as an Employee Movement.'],
            'converted_by' => $actor->id,
            'converted_at' => now(),
        ]);
    }

    // ----------------------------------------------------------------- mapping

    /**
     * Personal data from the applicant, employment data from the accepted offer.
     */
    protected function employeeData(Applicant $applicant, JobOffer $offer, array $data, array &$notices): array
    {
        return [
            'employee_number' => null, // issued by EmployeeService from the employee-number sequence
            'emp_first_name' => $applicant->first_name,
            'emp_middle_name' => $applicant->middle_name,
            'emp_last_name' => $applicant->last_name,
            'emp_suffix' => $applicant->suffix,
            'emp_sex' => $data['emp_sex'],
            'other_email' => $applicant->email,
            'mobile_no' => $applicant->phone,
            'street1' => $this->fits($applicant->address, 'Address', $notices),
            'joined_date' => $offer->proposed_start_date->toDateString(),
            'job_title_id' => $offer->job_title_id,
            'department_id' => $offer->department_id,
            'location_id' => $offer->location_id,
            'employment_status_id' => $offer->employment_status_id,
            'supervisor_id' => $data['supervisor_id'] ?? null,
            'status' => 'active',
        ];
    }

    protected function education(Applicant $applicant, array &$notices): array
    {
        return $applicant->education()->orderBy('id')->get()->map(function ($row) use (&$notices) {
            $field = collect([$row->degree, $row->major_specialization])->filter()->implode(', ');
            if (mb_strlen($field) > self::MAX) {
                $field = $row->major_specialization ?: $row->degree;
                $notices[] = "Education at {$row->institute}: degree and major didn't both fit; kept \"{$field}\".";
            }
            if ($row->notes) {
                $notices[] = "Education notes ({$row->institute}) are kept in the applicant record only.";
            }

            return [
                'level' => $row->level,
                'institute' => $row->institute,
                'major_specialization' => $field ?: null,
                'start_date' => $row->start_date,
                'end_date' => $row->end_date,
            ];
        })->all();
    }

    /**
     * Core HR requires an end date; a role with none (the applicant's current job) is not copied.
     */
    protected function workExperience(Applicant $applicant, array &$notices): array
    {
        return $applicant->workExperience()->orderBy('id')->get()->map(function ($row) use (&$notices) {
            if (! $row->to) {
                $notices[] = "Work experience at {$row->company} has no end date (Core HR requires one) and was not copied.";

                return null;
            }

            return [
                'company' => $row->company,
                'job_title' => $row->job_title,
                'from' => $row->from,
                'to' => $row->to,
                'notes' => $this->fits($row->notes, "Work experience notes ({$row->company})", $notices),
            ];
        })->filter()->values()->all();
    }

    /**
     * Copy the chosen documents submitted with this application whose type maps
     * to an employee document type. The applicant's file stays where it is.
     *
     * @return array<int, EmployeeDocument> keyed by applicant document id
     */
    protected function copyDocuments(Application $application, Employee $employee, array $documentIds, User $actor, array &$notices, array &$copiedFiles): array
    {
        $chosen = collect($documentIds)->map(fn ($id) => (int) $id)->unique();
        if ($chosen->isEmpty()) {
            return [];
        }

        $documents = $this->transferableDocuments($application)->whereIn('id', $chosen->all());
        if ($documents->count() !== $chosen->count()) {
            throw ValidationException::withMessages(['document_ids' => ['Only documents submitted with this application, of a type that maps to an employee document type, can be copied.']]);
        }

        $copied = [];
        foreach ($documents as $document) {
            $employeeDocument = $this->documents->copyFromStorage(
                $employee,
                $document->disk,
                $document->file_path,
                $document->original_name,
                [
                    'employee_document_type_id' => $document->type->employee_document_type_id,
                    'description' => "From recruitment application {$application->application_number}.",
                ],
                $actor,
                EmployeeDocument::SOURCE_RECRUITMENT
            );
            $copiedFiles[] = $employeeDocument->file_path;
            $copied[$document->id] = $employeeDocument;
        }

        return $copied;
    }

    /**
     * Documents submitted with the application whose type maps to an employee document type.
     */
    public function transferableDocuments(Application $application): Collection
    {
        return ApplicantDocument::query()
            ->whereIn('id', DB::table('application_documents')->where('application_id', $application->id)->select('applicant_document_id'))
            ->whereHas('type', fn ($q) => $q->whereNotNull('employee_document_type_id'))
            ->with('type:id,name,employee_document_type_id')
            ->orderBy('id')
            ->get(['id', 'applicant_id', 'applicant_document_type_id', 'original_name', 'disk', 'file_path', 'file_size']);
    }

    // ----------------------------------------------------------------- internals

    protected function existingEmployee(?Applicant $applicant, bool $lock = false): ?Employee
    {
        $id = $applicant?->employee_id ?? $applicant?->converted_employee_id;

        return $id ? Employee::whereKey($id)->when($lock, fn ($q) => $q->lockForUpdate())->first() : null;
    }

    /**
     * Converting needs the recruitment capability plus the Core HR ones it exercises.
     */
    protected function authorize(User $actor, bool $createsAccount): void
    {
        $needed = ['recruitment.conversion.create', 'employee.create', 'employee_movement.create'];
        if ($createsAccount) {
            $needed[] = 'user.create';
        }

        foreach ($needed as $permission) {
            if (! $actor->can($permission)) {
                throw ValidationException::withMessages(['conversion' => ['You are not allowed to convert candidates'.($permission === 'user.create' ? ' with a new user account.' : '.')]]);
            }
        }
    }

    protected function fits(?string $value, string $label, array &$notices): ?string
    {
        if ($value === null || $value === '' || mb_strlen($value) <= self::MAX) {
            return $value ?: null;
        }

        $notices[] = "{$label} is longer than Core HR allows (".self::MAX.' characters) and was not copied; it remains in the applicant record.';

        return null;
    }

    protected function summary(ApplicationConversion $conversion): string
    {
        return $conversion->conversion_type === ApplicationConversion::TYPE_NEW_EMPLOYEE
            ? "Employee {$conversion->employee_number} created; hiring movement #{$conversion->employee_movement_id} effective {$conversion->start_date->toDateString()}; user account: {$conversion->user_account}."
            : "Linked to existing employee {$conversion->employee_number}; nothing created in Core HR.";
    }

    protected function removeFiles(array $paths): void
    {
        foreach ($paths as $path) {
            Storage::disk(EmployeeDocument::DISK)->delete($path);
        }
    }

    protected function failure(Application $application, Throwable $e): ValidationException
    {
        Log::error('Recruitment conversion failed; everything was rolled back.', [
            'application_id' => $application->id,
            'exception' => $e::class,
            'message' => $e->getMessage(),
        ]);

        return ValidationException::withMessages(['conversion' => ['The conversion could not be completed, so nothing was saved: no employee, employee number, movement or account was created. Please try again or contact your administrator.']]);
    }
}
