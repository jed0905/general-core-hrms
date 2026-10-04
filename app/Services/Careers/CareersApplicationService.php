<?php

namespace App\Services\Careers;

use App\Models\ApplicantAccount;
use App\Models\ApplicantDocument;
use App\Models\ApplicantDocumentType;
use App\Models\Application;
use App\Models\ApplicationConversion;
use App\Models\JobOffer;
use App\Models\RecruitmentPortalEvent;
use App\Models\RecruitmentSource;
use App\Models\Vacancy;
use App\Services\Recruitment\ApplicantDocumentService;
use App\Services\Recruitment\ApplicantService;
use App\Services\Recruitment\ApplicationPipelineService;
use App\Services\Recruitment\ApplicationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Candidate self-service on top of the existing recruitment services: apply
 * (ApplicationService, the same applications table, number series and
 * pipeline), withdraw (ApplicationPipelineService), profile and documents
 * (ApplicantService, ApplicantDocumentService). Everything is scoped to the
 * signed-in account's own applicant; ids from the browser are never trusted.
 */
class CareersApplicationService
{
    public function __construct(
        protected ApplicationService $applications,
        protected ApplicationPipelineService $pipeline,
        protected ApplicantService $applicants,
        protected ApplicantDocumentService $documents,
        protected CandidateStatusPresenter $statuses,
        protected PublicVacancyService $vacancies,
        protected PortalEventRecorder $events
    ) {}

    /**
     * Upload the new files, check the required document types, then create the
     * application through ApplicationService (which locks the vacancy and
     * re-checks that it is still public, open and before its closing date).
     * One transaction: if anything fails, nothing remains and new files are deleted.
     *
     * @param  array{uploads?: array<int, array{applicant_document_type_id: int, file: UploadedFile}>, document_ids?: array<int>, cover_note?: ?string}  $data
     */
    public function submit(ApplicantAccount $account, string $slug, array $data): Application
    {
        $stored = [];

        try {
            return DB::transaction(function () use ($account, $slug, $data, &$stored) {
                $applicant = $account->applicant()->firstOrFail();
                // Lock the vacancy first, before any row that references it is written
                // (documents, events): one lock order for every submission, so concurrent
                // submissions queue on the vacancy instead of deadlocking on S→X upgrades.
                $vacancy = Vacancy::whereKey($this->vacancies->findVisible($slug)->id)->lockForUpdate()->firstOrFail();

                $ids = collect($data['document_ids'] ?? [])->map(fn ($id) => (int) $id)->unique();
                if ($ids->isNotEmpty() && ApplicantDocument::where('applicant_id', $applicant->id)->whereIn('id', $ids)->count() !== $ids->count()) {
                    throw ValidationException::withMessages(['document_ids' => ['Choose only your own documents.']]);
                }

                foreach ($data['uploads'] ?? [] as $upload) {
                    $document = $this->documents->upload($applicant, $upload['file'], ['applicant_document_type_id' => $upload['applicant_document_type_id']], null);
                    $stored[] = $document->file_path;
                    $ids->push($document->id);
                    $this->events->record(RecruitmentPortalEvent::DOCUMENT_UPLOADED, ['applicant_account_id' => $account->id, 'applicant_id' => $applicant->id, 'vacancy_id' => $vacancy->id], $document->type?->name);
                }

                $this->assertRequiredDocuments($ids);

                $application = $this->applications->createApplication($applicant, $vacancy, [
                    'recruitment_source_id' => RecruitmentSource::where('code', 'company_website')->value('id'),
                    'document_ids' => $ids->all(),
                    'remarks' => trim('Applied through the careers portal. '.($data['cover_note'] ?? '')),
                ], null, public: true);

                $this->events->record(RecruitmentPortalEvent::APPLICATION_SUBMITTED, [
                    'applicant_account_id' => $account->id, 'applicant_id' => $applicant->id, 'application_id' => $application->id, 'vacancy_id' => $vacancy->id,
                ], $application->application_number);

                return $application;
            });
        } catch (ValidationException $e) {
            $this->removeFiles($stored);
            throw $e;
        } catch (NotFoundHttpException) {
            $this->removeFiles($stored);
            throw ValidationException::withMessages(['vacancy_id' => ['This position is no longer accepting applications.']]);
        } catch (Throwable $e) {
            $this->removeFiles($stored);
            Log::error('Careers application failed; nothing was saved.', ['account' => $account->id, 'exception' => $e::class, 'message' => $e->getMessage()]);
            throw ValidationException::withMessages(['application' => ['Your application could not be submitted. Please try again.']]);
        }
    }

    public function withdraw(ApplicantAccount $account, string $applicationNumber, string $reason): Application
    {
        $application = $this->own($account, $applicationNumber);

        // The event is written in the same transaction, after the pipeline service's locks.
        return DB::transaction(function () use ($account, $application, $reason) {
            $application = $this->pipeline->withdrawByCandidate($application, $reason);
            $this->events->record(RecruitmentPortalEvent::APPLICATION_WITHDRAWN, [
                'applicant_account_id' => $account->id, 'applicant_id' => $account->applicant_id, 'application_id' => $application->id, 'vacancy_id' => $application->vacancy_id,
            ]);

            return $application;
        });
    }

    /** The account's own application, or 404 (never "forbidden", which would confirm it exists). */
    public function own(ApplicantAccount $account, string $applicationNumber): Application
    {
        return Application::where('applicant_id', $account->applicant_id)->where('application_number', $applicationNumber)->first()
            ?? throw new NotFoundHttpException;
    }

    /**
     * The candidate-facing list: number, job, date and a coarse status only.
     */
    public function listFor(ApplicantAccount $account): Collection
    {
        return Application::where('applicant_id', $account->applicant_id)
            ->with(['vacancy:id,title,public_slug,published_at,status,visibility,closing_date', 'currentStage:id,stage_type'])
            ->latest('applied_at')
            ->get()
            ->map(fn (Application $a) => $this->presentApplication($a, detail: false))
            ->values();
    }

    public function presentApplication(Application $application, bool $detail = true): array
    {
        $application->loadMissing(['vacancy:id,title,public_slug,published_at,status,visibility,closing_date,department_id,location_id', 'currentStage:id,stage_type']);
        $offer = JobOffer::where('application_id', $application->id)->latest('id')->first(['id', 'status']);
        $converted = ApplicationConversion::where('application_id', $application->id)->exists();
        $status = $this->statuses->present($application, $offer, $converted);
        $vacancy = $application->vacancy;

        $summary = [
            'number' => $application->application_number,
            'job_title' => $vacancy?->title,
            'job_slug' => $vacancy?->isPubliclyOpen() ? $vacancy->public_slug : null,
            'submitted_on' => $application->applied_at?->toDateString(),
            'status' => $status,
        ];

        if (! $detail) {
            return $summary;
        }

        $canWithdraw = ! $status['closed'] && ! in_array($status['key'], ['offer_available', 'offer_accepted'], true)
            && ! JobOffer::where('application_id', $application->id)->whereIn('status', [...JobOffer::OPEN, JobOffer::STATUS_ACCEPTED])->exists();

        return $summary + [
            'department' => $vacancy?->department?->name,
            'documents' => $application->documents()->with('type:id,name')->get(['applicant_documents.id', 'applicant_document_type_id', 'original_name', 'file_size'])
                ->map(fn ($d) => ['id' => $d->id, 'name' => $d->original_name, 'type' => $d->type?->name, 'size' => $d->file_size])->values(),
            'steps' => CandidateStatusPresenter::STEPS,
            'can_withdraw' => $canWithdraw,
        ];
    }

    /**
     * Contact details, education and work history. Name and email changes go
     * through HR (the email is the login and duplicate-detection key).
     */
    public function updateProfile(ApplicantAccount $account, array $data)
    {
        $applicant = $account->applicant()->firstOrFail();

        $full = Arr::only($applicant->toArray(), ApplicantService::PROFILE_FIELDS) // keeps email, names, is_internal, employee_id
            + ['education' => $data['education'] ?? [], 'work_experience' => $data['work_experience'] ?? []];
        $full = array_merge($full, Arr::only($data, ['preferred_name', 'phone', 'alternate_phone', 'address']));

        try {
            // One transaction: ApplicantService takes its locks (sequence row, then the
            // applicant) first, and the event is written while they are held.
            return DB::transaction(function () use ($account, $applicant, $full) {
                $applicant = $this->applicants->updateApplicant($applicant, $full);
                $this->events->record(RecruitmentPortalEvent::PROFILE_UPDATED, ['applicant_account_id' => $account->id, 'applicant_id' => $applicant->id]);

                return $applicant;
            });
        } catch (ValidationException $e) {
            if (array_key_exists('duplicate', $e->errors())) {
                throw ValidationException::withMessages(['phone' => ['This phone number can\'t be used. Please contact our HR team if it is yours.']]);
            }
            throw $e;
        }
    }

    public function uploadDocument(ApplicantAccount $account, array $data): ApplicantDocument
    {
        $applicant = $account->applicant()->firstOrFail();
        $document = $this->documents->upload($applicant, $data['file'], ['applicant_document_type_id' => $data['applicant_document_type_id']], null);
        $this->events->record(RecruitmentPortalEvent::DOCUMENT_UPLOADED, ['applicant_account_id' => $account->id, 'applicant_id' => $applicant->id], $document->type?->name);

        return $document;
    }

    public function deleteDocument(ApplicantAccount $account, int $documentId): void
    {
        $document = $this->ownDocument($account, $documentId);
        $this->documents->delete($document); // refuses documents already submitted with an application
        $this->events->record(RecruitmentPortalEvent::DOCUMENT_DELETED, ['applicant_account_id' => $account->id, 'applicant_id' => $account->applicant_id]);
    }

    public function ownDocument(ApplicantAccount $account, int $documentId): ApplicantDocument
    {
        return ApplicantDocument::where('applicant_id', $account->applicant_id)->whereKey($documentId)->first()
            ?? throw new NotFoundHttpException;
    }

    protected function assertRequiredDocuments(Collection $documentIds): void
    {
        $required = ApplicantDocumentType::where('is_active', true)->where('required_online', true)->get(['id', 'name']);
        if ($required->isEmpty()) {
            return;
        }

        $provided = ApplicantDocument::whereIn('id', $documentIds)->pluck('applicant_document_type_id')->unique();
        $missing = $required->reject(fn ($type) => $provided->contains($type->id));

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages(['uploads' => ['Please include: '.$missing->pluck('name')->implode(', ').'.']]);
        }
    }

    protected function removeFiles(array $paths): void
    {
        foreach ($paths as $path) {
            Storage::disk(ApplicantDocument::DISK)->delete($path);
        }
    }
}
