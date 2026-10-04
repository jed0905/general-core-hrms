<?php

namespace App\Http\Controllers\Web\People;

use App\Http\Controllers\Controller;
use App\Http\Requests\People\OnboardingTemplateRequest;
use App\Models\Employee;
use App\Models\EmployeeDocumentType;
use App\Models\EmploymentStatus;
use App\Models\OnboardingTemplate;
use App\Services\Onboarding\OnboardingTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingTemplateController extends Controller
{
    public function __construct(protected OnboardingTemplateService $templates) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'active']);

        return Inertia::render('app/People/OnboardingTemplates/Index', [
            'templates' => $this->templates->getPaginatedTemplates($filters),
            'filters' => $filters,
            'can' => [
                'create' => $request->user()->can('create', OnboardingTemplate::class),
                'update' => $request->user()->can('onboarding.template.update'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('app/People/OnboardingTemplates/Form', ['template' => null] + $this->options());
    }

    public function store(OnboardingTemplateRequest $request): RedirectResponse
    {
        $template = $this->templates->createTemplate($request->validated(), $request->user());

        return redirect()->route('people.onboarding-templates.edit', $template)->with('success', 'Template created.');
    }

    public function edit(OnboardingTemplate $onboardingTemplate): Response
    {
        return Inertia::render('app/People/OnboardingTemplates/Form', ['template' => $onboardingTemplate->load('tasks')] + $this->options());
    }

    public function update(OnboardingTemplateRequest $request, OnboardingTemplate $onboardingTemplate): RedirectResponse
    {
        $this->templates->updateTemplate($onboardingTemplate, $request->validated(), $request->user());

        return back()->with('success', 'Template saved. Onboardings already started keep their own tasks.');
    }

    protected function options(): array
    {
        return [
            'employmentStatuses' => EmploymentStatus::orderBy('name')->get(['id', 'name']),
            'documentTypes' => EmployeeDocumentType::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'employees' => Employee::whereNotIn('status', ['archived', 'terminated'])->orderBy('emp_last_name')
                ->get(['id', 'employee_number', 'emp_first_name', 'emp_last_name']),
        ];
    }
}
