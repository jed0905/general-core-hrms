<?php

namespace App\Http\Controllers\Careers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Careers\PublicApplicationRequest;
use App\Http\Requests\Careers\PublicDocumentRules;
use App\Models\ApplicantDocument;
use App\Models\ApplicantDocumentType;
use App\Services\Careers\CareersApplicationService;
use App\Services\Careers\PublicVacancyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PublicApplicationController extends Controller
{
    public function __construct(
        protected PublicVacancyService $vacancies,
        protected CareersApplicationService $careers
    ) {}

    public function create(string $slug): Response|RedirectResponse
    {
        $vacancy = $this->vacancies->findVisible($slug);
        $account = Auth::guard('applicant')->user();

        if ($vacancy->applications()->where('applicant_id', $account->applicant_id)->exists()) {
            return redirect()->route('careers.applications.index')->with('status', 'You have already applied for this position.');
        }

        return Inertia::render('careers/Apply', [
            'job' => $this->vacancies->present($vacancy, detail: false),
            'documentTypes' => ApplicantDocumentType::where('is_active', true)->orderBy('name')->get(['id', 'name', 'required_online']),
            'myDocuments' => ApplicantDocument::where('applicant_id', $account->applicant_id)->with('type:id,name')->latest('id')
                ->get(['id', 'applicant_document_type_id', 'original_name'])->map(fn ($d) => ['id' => $d->id, 'type_id' => $d->applicant_document_type_id, 'name' => $d->original_name, 'type' => $d->type?->name]),
            'limits' => ['max_kb' => PublicDocumentRules::MAX_KB, 'extensions' => PublicDocumentRules::EXTENSIONS],
        ]);
    }

    public function store(PublicApplicationRequest $request, string $slug): RedirectResponse
    {
        $data = $request->safe()->only(['document_ids', 'cover_note']);
        $data['uploads'] = collect($request->validated('uploads') ?? [])->values()
            ->map(fn ($row, $i) => ['applicant_document_type_id' => (int) $row['applicant_document_type_id'], 'file' => $request->file("uploads.{$i}.file")])
            ->all();

        $application = $this->careers->submit(Auth::guard('applicant')->user(), $slug, $data);

        return redirect()->route('careers.applications.show', $application->application_number)->with('success', 'Your application was submitted.');
    }
}
