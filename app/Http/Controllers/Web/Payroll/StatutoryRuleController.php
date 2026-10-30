<?php

namespace App\Http\Controllers\Web\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\StatutoryRuleDecisionRequest;
use App\Http\Requests\Payroll\StatutoryRuleRequest;
use App\Models\StatutoryRuleVersion;
use App\Services\Payroll\StatutoryRuleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Statutory rule versions of every country: draft entry with official source,
 * revision, verification by another user, rejection.
 */
class StatutoryRuleController extends Controller
{
    public function __construct(protected StatutoryRuleService $rules) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', StatutoryRuleVersion::class);
        $user = $request->user();
        $filters = $request->only(['country_code', 'rule_key', 'verification_status']);
        $rules = $this->rules->getPaginatedRules($filters);
        $rules->getCollection()->load(['parameters', 'brackets'])->each(fn (StatutoryRuleVersion $r) => $r->setAttribute('can', [
            'update' => $user->can('update', $r),
            'verify' => $user->can('verify', $r),
            'reject' => $user->can('reject', $r),
        ]));

        return Inertia::render('app/Payroll/StatutoryRules/Index', [
            'rules' => $rules,
            'filters' => $filters,
            'catalog' => $this->rules->catalog(),
            'statuses' => StatutoryRuleVersion::STATUSES,
            'categories' => StatutoryRuleVersion::CATEGORIES,
            'appliesBy' => StatutoryRuleVersion::APPLIES_BY,
            'can' => ['create' => $user->can('create', StatutoryRuleVersion::class)],
        ]);
    }

    public function store(StatutoryRuleRequest $request): RedirectResponse
    {
        $this->rules->createDraft($request->validated(), $request->user());

        return back()->with('success', 'Draft rule version saved. Another user must verify it before it can be used in a finalized run.');
    }

    public function update(StatutoryRuleRequest $request, StatutoryRuleVersion $statutoryRule): RedirectResponse
    {
        $this->rules->updateDraft($statutoryRule, $request->validated(), $request->user());

        return back()->with('success', 'Draft revised.');
    }

    public function verify(StatutoryRuleDecisionRequest $request, StatutoryRuleVersion $statutoryRule): RedirectResponse
    {
        $this->rules->verify($statutoryRule, $request->user(), $request->validated('notes'));

        return back()->with('success', 'Rule version verified.');
    }

    public function reject(StatutoryRuleDecisionRequest $request, StatutoryRuleVersion $statutoryRule): RedirectResponse
    {
        $this->rules->reject($statutoryRule, $request->user(), $request->validated('notes'));

        return back()->with('success', 'Rule version rejected.');
    }
}
