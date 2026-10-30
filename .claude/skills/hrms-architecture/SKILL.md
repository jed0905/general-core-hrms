---
name: hrms-architecture
description: Use when starting any backend or full-stack change in the General Core HRMS (Laravel 12 with Laravel 10 structure, Inertia, Vue) - adding a route, controller, form request, service, model or module; deciding where code belongs; reusing approval workflows or number sequences; or when unsure whether existing code is live or legacy.
---

# HRMS Architecture

Where code goes in this HRMS and which existing code to copy. Generic Laravel
advice lives in `laravel-best-practices`; this skill gives only the
project-specific map. CLAUDE.md remains the authority. Where this skill and
CLAUDE.md disagree, verify against the code and tell the user.

## First move: find out what is live

```bash
php artisan route:list --except-vendor --path=<module>   # live routes
php artisan route:list --except-vendor --name=<module>.
```

A controller or service that is not reachable from a live route, or that
imports a model missing from `app/Models`, is legacy. **Do not extend it, import
it, or delete it.** Reimplement what you need in the module structure below and
tell the user what looked dead.

## Module map (routes loaded in `RouteServiceProvider`)

| Module | Routes | State | Copy from it? |
|---|---|---|---|
| Recruitment | `routes/Recruitment/web.php` | Modern: policy per route, snapshots, concurrency tests | **Best exemplar** |
| Onboarding | under People/Recruitment, `app/Services/Onboarding/` | Modern | Yes |
| Leave | `routes/Leave/web.php`, `app/Services/Leave/` | Modern core; see CLAUDE.md known gaps | Yes, for balance/approval logic |
| Careers | `routes/Careers/web.php` | Separate `applicant` guard | Only for applicant-portal work |
| Reports | `routes/Reports/web.php`, `app/Services/Reports/` (`ReportRegistry`) | Modern | Yes, for reports |
| People / Time / Administration | own `web.php` | Mixed (some `<script setup>`, older patterns) | Check per file |
| **Payroll** | `routes/Payroll/web.php` | **Legacy and broken**: services import missing models (`PayrollCalendar`, `PayrollDeduction`, `OperatingUnit`, `JobStatus`, `PersonalInformation`), and its routes have no `can:` middleware | **No**. Start with `hrms-payroll` |

## Payroll is international: core vs localization

New payroll is split into a country-neutral **Global Payroll Core**
(`app/Services/Payroll/`) and one **Country Localization** per ISO 3166-1 alpha-2
code (`app/Services/Payroll/Localizations/<CC>/`; the Philippines is first).

- Core code never contains country codes in conditionals, agency names, statutory
  rates, or currency literals. It calls localizations through a contract resolved by country code.
- A localization never imports another country's classes. Generic statutory math
  (bracket lookup, floor/ceiling) lives once in the core and is reused by method name.
- Shared HRMS modules (Time, Leave, People) supply **inputs** (hours, days, paid/unpaid,
  assignments). They never compute pay. Their country assumptions (Leave's 8-hour day, the
  single `holidays` list, the `Asia/Manila` app timezone) must not leak into payroll.
- Details: `global-payroll-architecture`, `payroll-calculation-engine`, `payroll-compliance-localization`.

## Request flow (copy `JobRequisitionController` + `RequisitionService`)

```
routes/<Module>/web.php     ->middleware('can:<ability>,<model>') or 'can:<permission>'
Controller (thin)           app/Http/Controllers/Web/<Module>/<Entity>Controller.php
FormRequest                 app/Http/Requests/<Module>/{Store,Update,<Action>}<Entity>Request.php
Service                     app/Services/<Module>/<Entity>Service.php  (DB::transaction, lockForUpdate)
Model                       app/Models/<Entity>.php  (status constants, isDraft()/isPending() helpers)
Inertia::render('app/<Module>/<Entity>/Index', [..., 'can' => [...]])
```

Concrete conventions observed in modern modules:

- Constructor injection with promotion, several services allowed:
  `public function __construct(protected RequisitionService $requisitionService, protected RecruitmentFormOptions $options) {}`
- Index: `$filters = $request->only([...])`. Pass `filters`, the paginator, option
  lists, and a `can` array computed with `$request->user()->can(...)`.
- Mutations: `$this->service->doThing($request->validated(), $request->user())`,
  then `redirect()->route(...)->with('success', '...')`.
- Group a module's subfolders: `app/Http/Requests/Recruitment/`, `app/Services/Recruitment/`,
  `app/Services/Leave/`. **New modules get their own subfolder.** The flat
  `app/Services/*.php` files are mostly older code.
- Status values are model constants (`JobRequisition::STATUSES`, `STATUS_PENDING`,
  `CANCELLABLE`) and are mirrored in a `resources/js/utils/<module>.js` label/color map.
- Business-rule failures: `throw ValidationException::withMessages([...])`.

## Shared building blocks: reuse, don't rebuild

| Need | Use | Notes |
|---|---|---|
| Approval chain for a request | `App\Services\ApprovalChainResolver` + `approval_workflows` (keyed by `module`) | Resolves approvers but never writes. The caller **snapshots** approvers into its own `<entity>_approvals` table at submission. Seed the module's default workflow in the migration that creates the entity (see `create_job_offers_tables`). |
| Human-readable numbers (REQ-2026-00001) | `App\Services\NumberSequenceService::next()` | Call it **inside** the saving transaction. Register the key with `insertOrIgnore` into `number_sequences` in the migration. |
| Leave balance movement | `App\Services\Leave\LeaveBalanceMovementService` | The only place leave counters change. |
| Working days / schedules | `EmployeeWorkSchedule` -> `WorkScheduleDay` -> `Shift`, plus `Holiday` | One service only. Never duplicate day math. |
| Record access + menu | Policies in `AuthServiceProvider::$policies` | See `hrms-permissions`. |

## Snapshot and immutability pattern (applies to every workflow module)

Once a record leaves draft, freeze the data it depends on: copy approver
employees, names of positions/locations, rates, and table versions onto the
record or its child rows. Configuration edits affect only records created
afterwards. Leave, job offers, and requisitions all do this, and new modules
(payroll especially) must too. Payroll also snapshots statutory rule versions,
exchange rates, and currency precision per line.

## Red flags: stop and re-check

- You are about to `use App\Models\X` and `app/Models/X.php` doesn't exist.
- You are editing a file under `HrManagement/`, `Maintenance/`, legacy `Payroll*Service`, or `app/Helpers/`.
- A new route has no `can:` middleware.
- A multi-table write has no `DB::transaction()`.
- You are touching a second module "while you're there". Only change the module the task is about.

## Related skills

`hrms-database-safety` (migrations, data), `hrms-permissions`, `hrms-frontend`,
`hrms-testing`, `hrms-payroll` (payroll entry point), `global-payroll-architecture`,
`payroll-calculation-engine`, `payroll-compliance-localization`, `payroll-country-<country>`.
Generic Laravel patterns: `laravel-best-practices`. Planning: `superpowers:writing-plans`.
