<?php

namespace App\Http\Controllers\Web\Recruitment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\CareersSettingsRequest;
use App\Http\Requests\Recruitment\RecruitmentLookupRequest;
use App\Models\ApplicantDocumentType;
use App\Models\ApplicationEvaluation;
use App\Models\AssessmentType;
use App\Models\CareersSetting;
use App\Models\EvaluationCriterion;
use App\Models\InterviewType;
use App\Models\RecruitmentSource;
use App\Models\RecruitmentStage;
use App\Models\RejectionReason;
use App\Services\Recruitment\RecruitmentSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pipeline stage names and the recruitment lookups (recruitment.config.manage).
 */
class RecruitmentSettingsController extends Controller
{
    public function __construct(protected RecruitmentSettingsService $settings) {}

    public function index(): Response
    {
        return Inertia::render('app/Recruitment/Settings/Index', [
            'stages' => RecruitmentStage::orderBy('sort_order')->get(),
            'sources' => RecruitmentSource::orderBy('sort_order')->get(),
            'reasons' => RejectionReason::orderBy('sort_order')->get(),
            'interviewTypes' => InterviewType::orderBy('sort_order')->get(),
            'assessmentTypes' => AssessmentType::orderBy('sort_order')->get(),
            'criteria' => EvaluationCriterion::orderBy('sort_order')->get(),
            'ratingScale' => ApplicationEvaluation::RATINGS,
            'careers' => CareersSetting::current()->only(['headline', 'introduction', 'application_instructions', 'privacy_notice']),
            'documentTypes' => ApplicantDocumentType::orderBy('name')->get(['id', 'name', 'is_active', 'required_online']),
        ]);
    }

    public function updateStage(RecruitmentLookupRequest $request, RecruitmentStage $stage): RedirectResponse
    {
        $this->settings->renameStage($stage, $request->validated('name'));

        return back()->with('success', 'Stage renamed. Vacancies opened from now on use the new name.');
    }

    public function storeSource(RecruitmentLookupRequest $request): RedirectResponse
    {
        $this->settings->createLookup(RecruitmentSource::class, $request->validated());

        return back()->with('success', 'Source added.');
    }

    public function updateSource(RecruitmentLookupRequest $request, RecruitmentSource $source): RedirectResponse
    {
        $this->settings->updateLookup($source, $request->validated());

        return back()->with('success', 'Source updated.');
    }

    public function storeReason(RecruitmentLookupRequest $request): RedirectResponse
    {
        $this->settings->createLookup(RejectionReason::class, $request->validated());

        return back()->with('success', 'Rejection reason added.');
    }

    public function updateReason(RecruitmentLookupRequest $request, RejectionReason $reason): RedirectResponse
    {
        $this->settings->updateLookup($reason, $request->validated());

        return back()->with('success', 'Rejection reason updated.');
    }

    public function storeInterviewType(RecruitmentLookupRequest $request): RedirectResponse
    {
        $this->settings->createLookup(InterviewType::class, $request->validated());

        return back()->with('success', 'Interview type added.');
    }

    public function updateInterviewType(RecruitmentLookupRequest $request, InterviewType $interviewType): RedirectResponse
    {
        $this->settings->updateLookup($interviewType, $request->validated());

        return back()->with('success', 'Interview type updated.');
    }

    public function storeAssessmentType(RecruitmentLookupRequest $request): RedirectResponse
    {
        $this->settings->createLookup(AssessmentType::class, $request->validated());

        return back()->with('success', 'Assessment type added.');
    }

    public function updateAssessmentType(RecruitmentLookupRequest $request, AssessmentType $assessmentType): RedirectResponse
    {
        $this->settings->updateLookup($assessmentType, $request->validated());

        return back()->with('success', 'Assessment type updated.');
    }

    public function storeCriterion(RecruitmentLookupRequest $request): RedirectResponse
    {
        $this->settings->createLookup(EvaluationCriterion::class, $request->validated());

        return back()->with('success', 'Evaluation criterion added.');
    }

    public function updateCriterion(RecruitmentLookupRequest $request, EvaluationCriterion $criterion): RedirectResponse
    {
        $this->settings->updateLookup($criterion, $request->validated());

        return back()->with('success', 'Evaluation criterion updated.');
    }

    public function updateCareers(CareersSettingsRequest $request): RedirectResponse
    {
        CareersSetting::current()->update($request->validated() + ['updated_by' => $request->user()->id]);

        return back()->with('success', 'Careers site texts saved.');
    }

    /** Whether candidates must submit this document type when applying online. */
    public function updateDocumentType(Request $request, ApplicantDocumentType $documentType): RedirectResponse
    {
        $documentType->update($request->validate(['required_online' => ['required', 'boolean']]));

        return back()->with('success', 'Document requirement saved.');
    }
}
