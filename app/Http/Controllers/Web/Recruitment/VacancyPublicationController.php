<?php

namespace App\Http\Controllers\Web\Recruitment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\PublishVacancyRequest;
use App\Models\ApplicantDocumentType;
use App\Models\Vacancy;
use App\Services\Careers\PublicVacancyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * HR control of a vacancy's presence on the public careers site
 * (recruitment.vacancy.publish through VacancyPolicy::publish).
 */
class VacancyPublicationController extends Controller
{
    public function __construct(protected PublicVacancyService $vacancies) {}

    public function publish(PublishVacancyRequest $request, Vacancy $vacancy): RedirectResponse
    {
        $this->vacancies->publish($vacancy, $request->user(), $request->boolean('show_salary_publicly'));

        return back()->with('success', 'Published on the careers site.');
    }

    public function unpublish(Request $request, Vacancy $vacancy): RedirectResponse
    {
        $this->vacancies->unpublish($vacancy, $request->user());

        return back()->with('success', 'Removed from the careers site.');
    }

    /**
     * Exactly what candidates would see, for signed-in HR only; nothing is made public.
     */
    public function preview(Vacancy $vacancy): Response
    {
        $vacancy->load(['department:id,name', 'location:id,address,city', 'employmentStatus:id,name']);

        return Inertia::render('careers/Job', [
            'job' => $this->vacancies->present($vacancy),
            'alreadyApplied' => false,
            'requiredDocuments' => ApplicantDocumentType::where('is_active', true)->where('required_online', true)->orderBy('name')->pluck('name'),
            'preview' => true,
        ]);
    }
}
