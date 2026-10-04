<?php

namespace App\Http\Controllers\Web\Recruitment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\StoreApplicantDocumentRequest;
use App\Models\Applicant;
use App\Models\ApplicantDocument;
use App\Models\Application;
use App\Services\Recruitment\ApplicantDocumentService;
use App\Services\Recruitment\ApplicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Applicant files on the private disk; routes are scoped to the applicant.
 */
class ApplicantDocumentController extends Controller
{
    public function __construct(
        protected ApplicantDocumentService $documentService,
        protected ApplicationService $applicationService
    ) {}

    public function store(StoreApplicantDocumentRequest $request, Applicant $applicant): RedirectResponse
    {
        $application = $request->filled('application_id') ? Application::findOrFail($request->integer('application_id')) : null;
        if ($application) {
            $this->authorize('attachDocuments', $application);
        }

        DB::transaction(function () use ($request, $applicant, $application) {
            $document = $this->documentService->upload($applicant, $request->file('file'), $request->safe()->only(['applicant_document_type_id', 'description']), $request->user());

            if ($application) {
                $this->applicationService->attachDocuments($application, [$document->id], $request->user());
            }
        });

        return back()->with('success', 'Document uploaded.');
    }

    public function destroy(Applicant $applicant, ApplicantDocument $document): RedirectResponse
    {
        $this->authorize('delete', $document);
        $this->documentService->delete($document);

        return back()->with('success', 'Document deleted.');
    }

    public function download(Applicant $applicant, ApplicantDocument $document): StreamedResponse
    {
        $this->authorize('view', $document);

        return $this->documentService->download($document);
    }

    public function view(Applicant $applicant, ApplicantDocument $document): StreamedResponse
    {
        $this->authorize('view', $document);

        return $this->documentService->inline($document);
    }
}
