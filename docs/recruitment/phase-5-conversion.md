# Recruitment Phase 5: Hiring and Applicant Conversion

Converting a candidate whose job offer was **accepted** into a Core HR employee.
Onboarding, payroll, attendance, leave and the careers portal are out of scope.

## Workflow

Applicant → Application → Evaluation → Selection → Offer → **Accepted** → **Conversion** → Employee (+ Hiring movement, optional login)

- Only an application with an offer in status `accepted` can be converted. Draft, pending, approved, issued,
  rejected, candidate-rejected, expired and withdrawn offers are refused, and so is a selected application without an accepted offer.
- Selection and the vacancy opening (`vacancies.filled_count`) are not touched: the opening was reserved at selection.
- The application keeps its stage (Evaluation) and status. The offer stays `accepted`. A `converted` offer event
  records the conversion. No stage history is rewritten.

## Architecture

| Layer | Piece |
|---|---|
| Route | `POST recruitment/applications/{application}/convert` → `recruitment.applications.convert`, `can:convert,application` |
| Request | `ConvertApplicationRequest`: only what recruitment doesn't know (see below) |
| Policy | `ApplicationPolicy::convert` / `viewConversion` |
| Service | `ApplicationConversionService` (orchestration only) |
| Reused | `EmployeeService::createEmployee` (employee, number, education, work experience), `EmployeeMovementService::createMovement` (Hiring), `EmployeeDocumentService::copyFromStorage` (private copies), `UserService::createUser` (optional login), `OfferApprovalService::event` (offer audit) |
| UI | `components/Recruitment/ConversionPanel.vue` on the application page; the offer page shows the conversion |

## Database

- `application_conversions`: one immutable row per converted application. It holds the application, applicant,
  accepted offer, employee, employee number issued, hiring movement, start date, user-account result, copy counts,
  `notices` (what couldn't be copied, and why), converter and timestamp.
  - Unique on `application_id` and on `job_offer_id`.
  - CHECK constraints: `conversion_type` ∈ {new_employee, existing_employee}, `user_account` ∈ {created, existing, none}, and a new employee always has a movement.
- `application_conversion_documents`: provenance from each copied employee document to the applicant document it came from.
- `employee_documents.source = 'recruitment'` marks copied documents. This is a constant; the column already existed.
- `applicants.converted_employee_id` (from Phase 2) is set on conversion.
- Permissions: `recruitment.conversion.view` and `recruitment.conversion.create` for superadmin, hr_director, hr_manager and hr_staff.

There are no pending or failed rows. Conversion is a single transaction, so a failure leaves nothing behind
and is logged (`Recruitment conversion failed; everything was rolled back.`).

## Authorization

Converting needs `recruitment.conversion.create` **and** the Core HR capabilities it exercises: `employee.create` and
`employee_movement.create`. Creating a login also needs `user.create`. This is checked in the policy and again in the
service. Hiring managers see the conversion section of their own vacancy (read-only). Supervisors, employees and payroll cannot convert.

## Field mapping (external candidate)

| Employee | Source |
|---|---|
| `emp_first_name`, `emp_middle_name`, `emp_last_name`, `emp_suffix` | applicant |
| `emp_sex` | **conversion form**. Core HR requires it and recruitment never collects it |
| `other_email` | applicant email (personal; `work_email` is left for the company address) |
| `mobile_no` | applicant phone |
| `street1` | applicant address, if it fits (191 characters) |
| `joined_date` | offer `proposed_start_date` |
| `job_title_id`, `department_id`, `employment_status_id`, `location_id` | accepted offer |
| `supervisor_id` | conversion form (optional, prefilled with the vacancy's hiring manager) |
| `status` | `active` (the normal record status for a hired employee) |
| `employee_number` | issued by `EmployeeService` from the existing `employee_number` sequence |

- **Not mapped:** `preferred_name` and `alternate_phone` have no matching employee column.
- **No compensation is written anywhere.** The offer keeps its salary as the recruitment record.

**Education:** `level`, `institute` and dates are copied. `major_specialization` is set to "degree, major" (major alone if both don't fit).
Applicant education notes have no employee column, so they stay in recruitment and a notice records this.

**Work experience:** `company`, `job_title`, `from`, `to` and `notes` are copied.
- A role without an end date isn't copied, because Core HR requires `to`.
- Notes longer than 191 characters aren't copied.

Both cases are recorded in `notices`. The originals stay in the applicant record, and nothing is silently truncated.

## Documents

Only documents **submitted with this application** whose applicant document type maps to an employee document type
(`applicant_document_types.employee_document_type_id`) can be copied, and only those HR ticks (all ticked by default).
- Copies go to the private `local` disk under `employee-documents/{employee}/` with random names. The original filename is kept as metadata.
- The applicant's file and record are untouched.
- Copied files are deleted if the transaction fails.
- Downloads go through the existing authorized employee document routes.

## Internal candidates

If the applicant is linked to an employee (`applicants.employee_id`), or the same person was already converted through
another application (`converted_employee_id`), conversion **links** that employee:
- no employee, employee number, hiring movement, education, work experience, document or login is created;
- the employee's assignment is not changed;
- the conversion row is `existing_employee`, and the UI tells HR to record any promotion or transfer as an Employee Movement;
- a terminated or archived linked employee is refused (rehiring isn't part of recruitment conversion).

## User account

The existing password-reset flow depends on a legacy model that no longer exists, so there is no invitation mechanism to reuse.
Conversion therefore reuses User Management's `UserService::createUser`:
- it's optional and off by default;
- HR enters a username and an initial password, with the same rules as User Management;
- it requires `user.create`;
- only the standard `employee` role is assigned, never an HR or admin role;
- it runs inside the conversion transaction.

An internal candidate's existing login is recorded (`existing`) and never duplicated.

## Transactions, concurrency, rollback

Everything runs in one `DB::transaction`. Rows are locked in this order, before any plain read: application → accepted
offer → applicant → (existing employee). On MySQL/MariaDB, a plain read made before the locks would fix the
transaction's snapshot too early.

The employee-number sequence row is updated inside the same transaction, so a rollback returns the number.
`EmployeeMovementService` locks the new employee row as usual. Backstops:
- the unique indexes on `application_conversions` (application and offer)
- the unique `users.username`

A duplicate surfaces as "This candidate has already been converted." and any other failure as a generic
"nothing was saved" message, with details in the log.

## Tests

- `tests/Feature/Recruitment/ApplicationConversionTest.php` (14 tests): eligibility for every offer state,
  authorization, mapping, education, work experience, documents (chosen, private, provenance, originals kept), the Hiring movement,
  the conversion record, no re-conversion, rollback (a missing file midway and a missing Hiring type), internal candidates,
  a person converted twice, a terminated link, and the user account.
- `tests/Concurrency/RecruitmentPhase5ConcurrencyTest.php` (MariaDB, 6 tests):
  - 6 simultaneous conversions of one offer give one conversion, employee, number, hiring movement and login, and no duplicate history
  - 5 candidates converted at once get unique, gap-free numbers
  - a midway failure leaves nothing and consumes no number
  - an internal-candidate race links once
  - timestamp stability, and no `ON UPDATE` columns

## Known limitations

- Fields Core HR has but recruitment doesn't collect (birthday, civil status, nationality, address parts) stay
  empty, to be completed in the employee record.
- No invitation or password-reset email: the existing reset flow is broken (outside recruitment). Logins get an initial password chosen by HR.
- Rehiring a former (terminated) employee is not supported by conversion.
- An internal candidate's new position is not applied automatically; HR records the appropriate Employee Movement.
