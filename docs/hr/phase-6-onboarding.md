# Phase 6: Employee Onboarding

## 1. Purpose
Give HR a structured, auditable checklist to prepare a newly hired (or re-onboarded) **employee** to start work.
Onboarding begins after the employee exists in Core HR. It never creates employees, employee numbers or movements.

## 2. Architecture
People module: routes in `routes/People/web.php`, controllers in `app/Http/Controllers/Web/People`, services in `app/Services/Onboarding`.

| Layer | Pieces |
|---|---|
| Controllers | `OnboardingController` (HR cases), `OnboardingTaskController` (task actions), `OnboardingTemplateController`, `MyOnboardingController` (self-service) |
| Requests | `StartOnboardingRequest`, `OnboardingTemplateRequest`, `OnboardingTaskRequest`, `CompleteOnboardingTaskRequest`, `OnboardingReasonRequest`, `OnboardingNoteRequest` |
| Policies | `OnboardingPolicy`, `OnboardingTaskPolicy`, `OnboardingTemplatePolicy` |
| Services | `OnboardingService` (start, complete, cancel, extra tasks, notes, progress, dashboard), `OnboardingTaskService` (start, complete, verify, skip), `OnboardingTemplateService`, `OnboardingEventRecorder` |
| Reused | `EmployeeDocumentService::upload` for document tasks (private disk, random names) |
| UI | `People/Onboarding/{Index,Create,Show}`, `People/OnboardingTemplates/{Index,Form}`, `People/MyOnboarding/Index` |

## 3. Database design
Migrations `2026_10_09_000001` to `000003`.

- `onboarding_templates`: name (unique), description, optional `employment_status_id` (a suggestion only), active flag.
- `onboarding_template_tasks`: title, instructions, category, order, assignee type (+ designated employee), due offset and its reference date, required, employee-visible, needs-verification, optional required employee document type, active.
- `onboardings`: employee, template (+ name snapshot), optional `application_conversion_id` (provenance), status, start / target dates, notes, created / completed / cancelled actors, timestamps and reason.
  - **One active case per employee**: unique index on a generated column (`employee_id` while pending or in progress).
- `onboarding_tasks`: snapshot of each template task, with the assignee and due date resolved and stored, plus the lifecycle columns (started, completed, verified, skipped, cancelled, the satisfying `employee_document_id`).
  - **Unique (onboarding, template task)**: a template can't be applied twice to one case.
- `onboarding_events`: immutable audit trail.
- `onboarding_notes`: HR-only notes.
- **CHECK constraints (MySQL/MariaDB)**: every status, category, assignee type, offset range; completed rows have `completed_at`; a required task can never be `skipped`.
- **No `ON UPDATE` timestamps**: every lifecycle timestamp is nullable and written once.
- Permissions migration: see section 13.

## 4. Template system
Templates are data, not code; no checklist is seeded. Saving a template replaces its task list: edited rows keep their id, removed rows are deleted, and onboarding tasks only keep a nullable reference to them.
Deactivating a template or a task hides it from new onboardings. Templates are never deleted.

## 5. Task lifecycle
`pending → in_progress → completed`, `pending → completed`, `pending/in_progress → skipped` (optional tasks only, with a reason),
`pending/in_progress → cancelled` (only when the onboarding is cancelled). Completed, skipped and cancelled are final.
Verification is separate: HR records `verified_at` / `verified_by`, and the verifier must differ from the completer. The completer is never overwritten.
A task's first activity moves the onboarding from `pending` to `in_progress`.
Overdue = open and past its due date; it is computed, never stored, and needs no scheduled job.

## 6. Assignment model
Resolved once when the onboarding starts and stored on the task (`assignee_type` + `assignee_employee_id`):
- **employee**: the onboarding employee.
- **hr**: the HR pool (anyone with `onboarding.task.update`).
- **supervisor**: the employee's supervisor *at that moment*. A later supervisor change does not move the task.
- **specific_employee**: the template's designated employee.

If the supervisor or designated employee is missing or no longer current, the task goes to HR and the event says why.

## 7. Due-date model
`due_offset_days` (−365 to 365) counted from the onboarding's start date or from the day it was created. It is resolved into `due_date` at creation; template edits never move it.

## 8. Employee self-service
`people/my-onboarding` (`onboarding.view_own`). The employee is always `users.employee_id`, never a request parameter. The page shows:
- the employee's current (or latest) onboarding, with **employee-visible** tasks only, progress and due dates;
- no HR notes and no audit trail.

The employee can complete only their own employee-assigned tasks, uploading the required document where a task needs one.
They cannot complete HR, supervisor or designated tasks, skip, verify, change assignment, complete the onboarding or see anyone else's.

## 9. HR workflow
1. **Templates**: People → Onboarding Templates (`onboarding.template.*`).
2. **Start**: People → Onboarding → Start onboarding (`onboarding.create`): employee, template (a template matching the employee's employment status is marked *suggested*), start date, optional target date and notes.
3. **List**: filter by status (active by default), department, supervisor, template, start date range, overdue, and employee search. KPI cards show not started, in progress, completed this month, overdue tasks, and tasks due within 7 days.
4. **Case page**:
   - tasks grouped by timing, with assignee, due date, status and verification
   - complete HR tasks (or any task, with `onboarding.task.update`), verify, skip optional tasks
   - add extra tasks and HR notes
   - full history, then complete or cancel

## 10. Supervisor workflow
Supervisors and designated employees see "Assigned to me" on My Onboarding: only the tasks assigned to them, on active onboardings of other employees.
They can complete those tasks. They cannot open the HR list or case page, or act on any other task.

## 11. Completion rules
`onboarding.complete`, on an active case, only when **every required task is completed**, and verified where it needs verification.
Otherwise a clear message: "Onboarding cannot be completed because N required task(s) remain incomplete." Optional tasks may stay open.
A completed case is immutable (model guard); its tasks can no longer change.

## 12. Cancellation rules
`onboarding.cancel` (HR management), with a required reason. Open tasks become `cancelled`. Nothing is deleted.
A cancelled case is immutable, and a new onboarding may then be started.

## 13. Authorization
| Who | Can |
|---|---|
| superadmin, hr_director, hr_manager | everything (`onboarding.*` + self-service) |
| hr_staff | view, start, add tasks/notes, complete onboarding, act on and verify tasks, view templates. Not cancel; not create or edit templates |
| supervisor, employee, payroll | `onboarding.view_own` / `update_own` only: their own onboarding and tasks assigned to them |
| hiring managers | nothing from recruitment; onboarding access comes only from the permissions above |

Route middleware checks the policy, the Form Request checks it again, and the service re-checks permission and assignment against the locked rows.

## 14. Security
- Documents use the existing private employee-document storage. Paths are never serialized.
- HR downloads go through the existing authorized employee document routes.
- Employees can't choose existing documents, so they never see others' files.
- HR notes and the audit trail are not in self-service props.

## 15. Concurrency
- **Start**: locks the employee row before any plain read (MariaDB snapshot rule), checks for an active case, and the unique generated-column index is the backstop.
- **Task actions**: lock the onboarding, then the task, then re-check status and actor.
- **Onboarding complete/cancel**: lock the onboarding (and its open tasks), then re-check every required task.
- Task completion and onboarding completion take the same onboarding lock, so a case can never complete before its last required task.

## 16. Transaction boundaries
Each of these is one transaction, together with its events: start (case + all task snapshots + events), each task action, complete, cancel.
A document upload inside a task completion is removed again if that transaction fails.

## 17. Test coverage
- **SQLite feature tests**: `tests/Feature/Onboarding`, 21 tests:
  - templates (create, edit, sync, validation, hijack protection, access)
  - cases (snapshot, eligibility, one active case, re-onboarding, HR fallback, completion and verification, cancellation, list, filters, progress, overdue, extra tasks, notes)
  - tasks (lifecycle, skip rules, verification, documents, self-service, supervisor/designated scope, no access for payroll and unrelated employees)
  - permissions and their rollout
- **Phase 5 integration**: in `ApplicationConversionTest`.
- **MariaDB concurrency**: `tests/Concurrency/OnboardingConcurrencyTest.php`, 8 tests:
  - 6 simultaneous starts give one case and no duplicate task instances
  - 6 different employees at once
  - 6 completions of one task
  - 6 onboarding completions
  - 6 completions while a required task is open give zero successes
  - completing the last required task and the onboarding at once
  - timestamp stability, and no `ON UPDATE` columns

## 18. Known limitations
- No notifications or reminders (out of scope).
- No supervisor/designated-employee document access: their tasks are plain completions.
- Required tasks can't be waived. If one truly doesn't apply, HR completes it with a remark, or cancels and restarts with another template.
- Optional tasks left open on a completed case stay as they were.

## 19. Phase 5 integration
Conversion does **not** create onboarding: its transaction is unchanged, which avoids coupling.
After a conversion, the application page's conversion panel offers **Start onboarding**. It opens the start form with the employee pre-filled and stores `application_conversion_id` on the case, which is validated to belong to that employee.
Internal candidates or rehires are existing employees: onboarding is allowed only while they're current, and a second case after a completed one needs explicit confirmation.

## 20. Phase 6 boundary
Not implemented:
- recruitment changes (beyond the link above), conversion, employee creation, employee numbers or movements
- payroll, attendance, leave, performance, learning and development, separation
- careers portal
- email or notification campaigns
- scheduled jobs
