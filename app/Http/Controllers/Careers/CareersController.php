<?php

namespace App\Http\Controllers\Careers;

use App\Http\Controllers\Controller;
use App\Models\ApplicantDocumentType;
use App\Services\Careers\PublicVacancyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public job list and job detail: only published, open, external vacancies,
 * and only their public fields.
 */
class CareersController extends Controller
{
    public function __construct(protected PublicVacancyService $vacancies) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'department', 'employment_type', 'location']);

        return Inertia::render('careers/Index', [
            'jobs' => $this->vacancies->paginate($filters),
            'filters' => $filters,
            'options' => $this->vacancies->filterOptions(),
        ]);
    }

    public function show(string $slug): Response
    {
        $vacancy = $this->vacancies->findVisible($slug);
        $account = Auth::guard('applicant')->user();

        return Inertia::render('careers/Job', [
            'job' => $this->vacancies->present($vacancy),
            'alreadyApplied' => $account?->isUsable()
                && $vacancy->applications()->where('applicant_id', $account->applicant_id)->exists(),
            'requiredDocuments' => ApplicantDocumentType::where('is_active', true)->where('required_online', true)->orderBy('name')->pluck('name'),
        ]);
    }
}
