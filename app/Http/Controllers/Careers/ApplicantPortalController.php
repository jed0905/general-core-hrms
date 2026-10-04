<?php

namespace App\Http\Controllers\Careers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Careers\PublicApplicantProfileRequest;
use App\Http\Requests\Careers\PublicApplicationDocumentRequest;
use App\Http\Requests\Careers\WithdrawPublicApplicationRequest;
use App\Models\ApplicantDocument;
use App\Models\ApplicantDocumentType;
use App\Services\Careers\CareersApplicationService;
use App\Services\Recruitment\ApplicantDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The signed-in candidate's own applications, profile and documents. Every
 * lookup is scoped to Auth::guard('applicant')->user()->applicant_id.
 */
class ApplicantPortalController extends Controller
{
    public function __construct(
        protected CareersApplicationService $careers,
        protected ApplicantDocumentService $documents
    ) {}

    public function index(): Response
    {
        return Inertia::render('careers/MyApplications', ['applications' => $this->careers->listFor($this->account())]);
    }

    public function show(string $number): Response
    {
        return Inertia::render('careers/MyApplication', [
            'application' => $this->careers->presentApplication($this->careers->own($this->account(), $number)),
        ]);
    }

    public function withdraw(WithdrawPublicApplicationRequest $request, string $number): RedirectResponse
    {
        $this->careers->withdraw($this->account(), $number, $request->validated('reason'));

        return back()->with('success', 'Your application was withdrawn.');
    }

    public function profile(): Response
    {
        $applicant = $this->account()->applicant()->with(['education', 'workExperience'])->firstOrFail();

        return Inertia::render('careers/Profile', [
            'profile' => [
                'name' => trim("{$applicant->first_name} {$applicant->middle_name} {$applicant->last_name} {$applicant->suffix}"),
                'email' => $applicant->email,
                'preferred_name' => $applicant->preferred_name,
                'phone' => $applicant->phone,
                'alternate_phone' => $applicant->alternate_phone,
                'address' => $applicant->address,
                'education' => $applicant->education->map->only(['level', 'institute', 'degree', 'major_specialization', 'start_date', 'end_date', 'is_completed'])->values(),
                'work_experience' => $applicant->workExperience->map->only(['company', 'job_title', 'from', 'to', 'notes'])->values(),
            ],
            'documents' => ApplicantDocument::where('applicant_id', $applicant->id)->with('type:id,name')->withCount('applications')->latest('id')
                ->get(['id', 'applicant_document_type_id', 'original_name', 'file_size', 'uploaded_at'])
                ->map(fn ($d) => ['id' => $d->id, 'name' => $d->original_name, 'type' => $d->type?->name, 'size' => $d->file_size, 'uploaded_at' => $d->uploaded_at?->toDateString(), 'submitted' => $d->applications_count > 0]),
            'documentTypes' => ApplicantDocumentType::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function updateProfile(PublicApplicantProfileRequest $request): RedirectResponse
    {
        $this->careers->updateProfile($this->account(), $request->validated());

        return back()->with('success', 'Profile saved.');
    }

    public function uploadDocument(PublicApplicationDocumentRequest $request): RedirectResponse
    {
        $this->careers->uploadDocument($this->account(), ['applicant_document_type_id' => $request->integer('applicant_document_type_id'), 'file' => $request->file('file')]);

        return back()->with('success', 'Document uploaded.');
    }

    public function deleteDocument(int $document): RedirectResponse
    {
        $this->careers->deleteDocument($this->account(), $document);

        return back()->with('success', 'Document deleted.');
    }

    public function downloadDocument(int $document): StreamedResponse
    {
        return $this->documents->download($this->careers->ownDocument($this->account(), $document));
    }

    protected function account()
    {
        return Auth::guard('applicant')->user();
    }
}
