# CLAUDE.md — General Core HRMS

HRMS for private companies. It is a Laravel + Inertia + Vue app that grew out of an older
government/university HRIS, and a lot of that legacy code is still in the repo
(see "Legacy code" below). **Inspect existing code before creating anything new.**

## Stack

- PHP 8.3 (composer allows ^8.1). Laravel 12, **still using the Laravel 10 structure**:
  `app/Http/Kernel.php`, `app/Console/Kernel.php`, `RouteServiceProvider`,
  `EventServiceProvider`, `AuthServiceProvider`. Register things there, not in `bootstrap/app.php`.
- inertia-laravel 2.x on the server, `@inertiajs/vue3` 1.0 on the client. Ziggy provides `route()`.
- Vue 3.5 (Options API), Vuetify 3.4, Vite 4, `@mdi/font` icons, vue-toastification, chart.js.
- MySQL. spatie/laravel-permission 6, Reverb and laravel-echo, Sanctum, Socialite (Google),
  dompdf/snappy, maatwebsite/excel.
- **Don't add packages** unless the task can't reasonably be done with what's already installed. Ask first.

## Commands

- `php artisan route:list --except-vendor`: this is how to find out what is actually live.
- `npm run dev` / `npm run build`
- `vendor/bin/pint --dirty`: format only the PHP files you changed.
- `php artisan test`. Read the Testing section first; the suite is not safe to run as-is.

## Hard rules

- Only change the module the task is about. Don't touch other modules.
- Don't delete existing functionality, files, routes, or migrations unless told to explicitly.
  If something looks dead, say so and ask.
- Preserve backward compatibility where practical: route names, Inertia prop names, and
  column names used by other code.
- Wrap multi-table business operations in `DB::transaction()`.
- Don't modify `.env`, `.env.*`, or production config values in `config/*`. Never print,
  log, or commit credentials or secrets.
- Git: never run `reset --hard`, `rebase`, `push --force`, `branch -D`/delete, or
  `clean -f` unless told to explicitly. Commit only when asked.
  Don't commit `.env`, `vendor/`, `node_modules/`, or `_ide_helper.php` changes.
- Follow existing naming conventions (below). Don't invent new ones.

## Architecture: request flow

`routes/<Module>/web.php` → `Controller` (thin) → `FormRequest` (validation) → `Service`
(business logic, transactions) → Eloquent models → `Inertia::render('app/<Module>/<Page>/Index', props)`.

### Routes

- Module route files are `routes/{Administration,People,Time,Leave,Payroll}/web.php`, loaded
  in `RouteServiceProvider` with `['auth', 'web']`. `routes/web.php` holds auth, account,
  and notification routes.
- Style: `Route::middleware(['auth','verified'])->prefix('<module>')->name('<module>.')->group(...)`.
  Nest `prefix()->name()` groups per resource.
- Authorize every route with permission middleware: `->middleware('can:<permission>')`.
- Route names use dots (`leave.config.types.index`, `time.work-schedules.store`).
  URL segments use kebab-case. Route parameters are camelCase model-bound
  (`{leaveApplication}`, `{workSchedule}`).
- Put static segments (`/create`, `/export`) **before** `/{model}` routes.

### Controllers

- Location: `app/Http/Controllers/Web/<Module>/<Entity>Controller.php`.
- Inject the service through constructor promotion: `public function __construct(protected XService $xService) {}`.
- Keep them thin. Use `$request->only([...filters])` for index filters and `$request->validated()`
  for writes, then delegate to the service.
- Return type is `Inertia\Response` for pages and `RedirectResponse` for mutations
  (`redirect()->route(...)` or `back()`), followed by `->with('success', '...')`.
  **Known gap:** `HandleInertiaRequests::share()` currently shares only `flash.status` and
  `flash.error`, so `success` flashes don't reach the client. Fix this deliberately if it
  matters to the task; don't work around it page by page.
- Mutations use Inertia forms. Don't add JSON API endpoints for pages unless there's a reason.

### Form Requests

- Location: currently flat in `app/Http/Requests/`, named `Store<Entity>Request` /
  `Update<Entity>Request`. Newer leave requests live in `app/Http/Requests/Leave/`.
  For new Leave work, use the `Leave/` subfolder.
- `authorize()` must do a real check (`$this->user()->can('<permission>')` or a policy).
  Never leave a generated `return false;` or a blanket `return true;` on a sensitive action.
- Array rules (`['required', 'exists:table,id']`). Use `Rule::unique(...)->ignore($id)` for updates.
- **Field names and enum values must match the migration exactly.** A known past bug:
  requests and services used `max_balance`/`annual` while the column was `maximum_balance`
  with enum value `annually`.

### Services

- Location: `app/Services/<Entity>Service.php` (flat). Newer leave services live in
  `app/Services/Leave/`. Put new leave logic there.
- Methods follow the pattern `getPaginated<Plural>(array $filters, int $perPage = 15)`,
  `create<Entity>`, `update<Entity>`, `archive<Entity>`/`delete<Entity>`.
- Pagination uses `->paginate($perPage)->withQueryString()`. Filters use `->when(...)`.
- Throw `ValidationException::withMessages([...])` for business-rule violations so Inertia
  shows them as form errors.
- **Transactions:** any operation that writes to more than one table, or to a balance, goes
  in `DB::transaction()`. For balance changes, re-read the row with `lockForUpdate()`
  **inside** the transaction and run the sufficiency check there, not before it.
- Never leave `dd()`, `dump()`, or `ray()` in committed code.
- `php artisan make:service` exists (`app/Console/Commands/MakeServiceCommand.php`).

### Policies and authorization

- The primary mechanism is spatie permissions, checked through route `can:` middleware.
  Permissions are seeded in `database/seeders/RolesAndPermissionsSeeder.php`. Add new
  permissions there and assign them to the relevant roles (superadmin, hr_director,
  hr_manager, hr_staff, payroll, supervisor, employee).
  Naming: `<entity>.<action>` or `<module>.<entity>.<action>`, for example `leave_type.view`,
  `leave.balance.adjust`, `leave.approval.approve`, `leave.view_own`.
- Use policies (registered in `AuthServiceProvider::$policies`) for record-level ownership
  and state checks. Call `$this->authorize('<ability>', $model)` in controllers.
  Every ability you call must exist on the policy.
- `app/Policies/LeaveApplicationPolicy.php` is **legacy**. It references `currentStatus`,
  `visibleTo`, designations, and campus roles, none of which exist. Rewrite it for the new
  leave model rather than extending it.

### Eloquent and models

- Models are in `app/Models`, singular PascalCase. Tables are snake_case plural
  (`employee_leave_ledger` is an exception).
- Declare `$fillable`, and keep it in sync with the migration. Newer models use a
  `casts(): array` method; older ones use `protected $casts`. Match the file you're in.
- Cast booleans, dates, and decimals (`'decimal:4'`) explicitly.
- Relationship methods are camelCase. Add return types (`BelongsTo`, `HasMany`) in new code.
- Employee columns use the `emp_` prefix (`emp_first_name`, `emp_last_name`, …).
  The hire date is `joined_date`. **There is no `date_hired`.**
- "Archive" usually means `status`/`is_active` = inactive, not soft deletes. Check the
  entity before choosing.
- Known model issues. Verify before relying on these:
  - `Employee::supervisor()` is defined as `hasOne(..., 'supervisor_id')`. That returns a
    subordinate; the correct relation is `belongsTo(Employee::class, 'supervisor_id')`.
  - `Employee::user()` is `hasMany`.
  - `EmployeeAttendanceLog::employee()` and `EmployeeMovement` reference columns and models
    that don't exist.
  - `LeaveApprovalWorkflowStep` `$fillable` doesn't match its migration.
  - `LeaveApplicationComment` fills `comment`, but the column is misspelled `commment`.
  - `LeaveStatus` has no table. The status history table is `leave_application_status_histories`.

### Migrations and MySQL

- One flat `database/migrations` folder. Earlier migrations were edited in place.
  **From now on, add new migrations** (create or alter) instead of editing existing ones,
  unless told the schema is still disposable.
- Use `foreignId()->constrained()` with an explicit `cascadeOnDelete`/`nullOnDelete`/
  `restrictOnDelete`. Give long composite unique indexes and index names an explicit short
  name (MySQL has a 64-character limit). `Schema::defaultStringLength(191)` is set.
- Money and leave quantities use `decimal`, never string or float columns.
- Provide a working `down()`.
- Never run `migrate:fresh`, `migrate:reset`, `db:wipe`, or destructive seeders against a
  database you weren't told to use.

## Frontend

### Vue (Options API)

- New components and pages use the **Options API**: `export default { name, components,
  props, data(), computed, watch, methods }`. A few People and Administration pages use
  `<script setup>`. Leave those as they are, but don't copy the pattern.
- Pages live at `resources/js/pages/app/<Module>/<Entity>/Index.vue`, matching the
  `Inertia::render('app/<Module>/<Entity>/Index')` string.
  Most modules use one Index page with Vuetify dialogs for create and edit.
  Some (Time/Shifts, WorkSchedules) have separate Create and Edit pages.
- Wrap pages in `<SidebarLayout>` and set the title with `<Head title="...">`.
  Import from `@/layouts/SidebarLayout.vue`. **The folder name is lowercase `layouts`.**
  Some files import `@/Layouts/...`, which only works on case-insensitive file systems.
  Use lowercase in new code.
- Forms use `useForm()` inside `data()`, `form.post/put/delete(route('...'))`, and
  `form.errors.<field>` bound to `:error-messages`. **Use `form.put` for PUT routes**
  (or `_method: 'put'` when uploading files with `forceFormData`).
- Lists and filters use `router.get(route('...'), filters, { preserveState: true, preserveScroll: true })`.
- Use Ziggy `route()` for every URL; never hardcode paths.
- Add navigation entries in `resources/js/layouts/SidebarLayout.vue` (the `modules` object).
  Each entry has `title`, `icon`, `route`, `permission`, and `routePrefix`; the permission
  controls visibility.
- Reuse the shared components in `resources/js/components` (`Pagination`, `DeleteDialog`,
  `FilterWrapper`, `TableWrapper`, buttons) before writing new ones.

### Vuetify 3

- Use Vuetify components (`v-data-table`/`v-table`, `v-dialog`, `v-text-field`,
  `v-select`, …) and `mdi-*` icons.
- Themes (light and dark) are defined in `resources/js/app.js`. `primary` and `secondary`
  come from `corporate_brandings`. Use theme colors (`color="primary"`,
  `rgb(var(--v-theme-...))`), never hardcoded hex values, and test in both modes.

## Testing

- The current suite is effectively empty. `tests/Feature/MaintenanceLeavesControllerTest.php`
  is legacy and targets a `Leave` model and route that no longer exist.
- `.env.testing` points to a MySQL database, and tests use `RefreshDatabase`.
  **Don't run the suite until you've confirmed the test database is disposable**, or switch
  to sqlite in-memory in `phpunit.xml` with approval.
- New business logic, especially leave balance and approval logic, needs PHPUnit Feature
  and/or Unit tests (PHPUnit 11 class style; use `#[Test]` or the `test_` prefix).
  Seed `RolesAndPermissionsSeeder` when a test hits permission-gated routes.
  Only `UserFactory` is usable; add factories as needed.
- Before saying a task is done, run the relevant tests, and also `php artisan route:list`
  if you touched routes and `npm run build` if you touched Vue. Report failures honestly.

## Leave Management architecture

The live code is in `app/Http/Controllers/Web/Leave/*`, `app/Services/Leave/*`,
`app/Services/Leave{Type,Policy,PolicyRule,Balance}Service.php`, `routes/Leave/web.php`,
and `resources/js/pages/app/Leave/**`.

### Entity responsibilities (canonical)

| Table | Meaning |
|---|---|
| `leave_types` | Leave classification and identity (name, code). No behavior lives here. |
| `leave_policies` | A policy or version, effective-dated (`effective_from`/`effective_to`, `is_active`). |
| `leave_policy_rules` | Behavior of one leave type under one policy (accrual, caps, carry-forward, service requirements, half-day/hourly, attachment, approval, negative balance). Unique per (policy, type). |
| `employee_leave_balances` | An employee's entitlement state per leave type (`balance`, `used`, `pending`, `as_of_date`). |
| `employee_leave_ledger` | Intended transaction log for balance movements. Currently unused. |
| `leave_applications` | A request (employee, type, reason, totals, status, timestamps). |
| `leave_application_dates` | Requested dates and durations (`full_day`/`half_day`/`hours`, `day_fraction`, `is_paid`). Unique per (application, date). |
| `leave_application_attachments` / `_comments` / `_status_histories` | Supporting records for an application. |
| `leave_approval_workflows` | Reusable approval configuration (optionally tied to a policy). |
| `leave_approval_workflow_steps` | Ordered steps (`step_order`, `approver_type`, approver reference, `is_required`). |
| `leave_application_approvals` | The **actual approval instances** for a submitted application (`approver_id`, `approval_order`, `status`, `acted_at`, `remarks`). |

### Invariants

- **Historical approval records must not change when workflow configuration changes.**
  At submission, resolve the workflow steps into concrete approver employees and copy them
  into `leave_application_approvals`, including any snapshot data needed. Never recompute
  or mutate approvals on existing applications from current workflow configuration.
  Editing a workflow only affects applications submitted afterward.
- Policies and rules are versioned by effective dates. Don't edit a rule in a way that
  silently changes the meaning of past applications; prefer a new policy version.
- Every balance change (file, approve, reject, return, cancel, adjust, accrue) happens in
  one transaction, uses a locked balance row, and should write a ledger entry once the
  ledger is implemented. Keep `pending`, `used`, and `balance` consistent: available =
  `balance − pending`, and final approval moves pending → used and subtracts from balance.
- Cancelling or reversing an **approved** application must restore `used`/`balance`.
  Terminal states can't be re-acted on.
- Approvals run strictly in `approval_order`. Only the approver on the lowest pending step
  may act (`LeaveApprovalService::getPendingStepForApprover`).
- Status values currently in use: `pending`, `approved`, `rejected`, `returned`, `cancelled`.
  Don't introduce a new status without updating every service, the policy, and the Vue
  status maps.
- Day and hour math currently assumes 8 hours/day and ignores schedules and holidays.
  Working-day calculations belong in one service backed by `EmployeeWorkSchedule` →
  `WorkScheduleDay` → `Shift` and `Holiday`. Don't duplicate this math.

### Known gaps (as of the 2026-09-30 analysis). Verify before relying on these.

- Nothing creates `leave_application_approvals` rows yet, and the workflow tables are unused.
- There is no policy-to-employee assignment. `LeavePolicyValidator` uses the first active
  rule for the leave type.
- `LeaveApplicationPolicy` is legacy and has no `update` ability.
  `UpdateLeaveApplicationRequest::authorize()` returns false.
- `LeavePolicyRuleService::createRule` contains `dd()`, and `StoreLeavePolicyRuleRequest`
  field names and enums don't match the schema.
- The routes reference missing `LeaveApplicationController@show`, `myBalances`, and `history`.
  The config URLs contain a doubled `/leave/leave/configuration`.
- Balances have no period/year dimension and no unique key.
- `LeavePolicyValidator::validate()` runs outside the transaction.
  `LeaveApplicationService::validatePolicyRules()` is dead duplicate code.

## Legacy code: do not extend

These are unrouted or broken, and many reference models and tables that don't exist
(PersonalInformation, OperatingUnit, Designation, Position, UniversityActivity, SalaryGrade,
Leave, …):

- `app/Http/Controllers/Web/HrManagement/*`, `Web/Maintenance/*`, `EmployeeMovementController`
- `app/Services/LeaveService.php`, `LeavePdfService`, `RestoreLeaveCreditService`,
  `SpecialLeaveService`, `EmployeeLeaveService`, `DailyTimeRecordService`, `PrintDtrService`,
  `UniversityActivityService`, `SalaryService`
- `app/Console/Commands/*Leave*`, `ProcessExistingSignatures`, `app/Jobs/*`.
  The scheduled `leaves:credit` command and `CheckTardinessJob` are both broken.
- `app/Helpers/*` (`UserInformationHelper`, `SupervisorHelper`, …), most of `app/Observers`,
  and most of `app/Http/Resources` and `app/Http/Filters`
- `resources/js/pages/app/HrManagement/**`, `pages/app/RolesAndPermissions/**`, and
  legacy tab components (`LeavesTabs`, `MyLeavesTabs`, `LeaveManagementTabs`, …)
- `database/seeders/LeaveSeeder.php`, `GovernmentPositionsSeeder.php`

Don't delete these without explicit instruction. Don't import from them in new code.
If a task needs something from them, reimplement it in the new module structure.

## Attendance / DTR

There are no live routes. Only raw `employee_attendance_logs` (biometric punches keyed by
a biometric ID string) and `employee_biometric_ids` exist. The live Time module
(`routes/Time/web.php`) covers shifts, work schedules, effective-dated employee schedule
assignments, and holidays.

## Organization model

- `employees.department_id` → `departments` (tree via `parent_id`); `job_title_id`,
  `location_id`, `employment_status_id`; `supervisor_id` → `employees`.
- No designation, position, or department head exists yet.
- `users.employee_id` links a login to an employee (nullable).
