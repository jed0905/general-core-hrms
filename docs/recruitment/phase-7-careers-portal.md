# Recruitment Phase 7: Careers / External Applicant Portal

## 1. Public careers architecture
`/careers` is a public Inertia area. It is defined in `routes/Careers/web.php`, loaded with the `web` group only (sessions and CSRF), never `auth`.
It feeds the **existing** recruitment domain. There are no new applicant, application or offer stores:

Public vacancy → `Applicant` → `Application` (via `ApplicationService::createApplication`) → existing pipeline, screening, interviews, selection, offers, conversion, onboarding.

| Layer | Pieces |
|---|---|
| Services (`app/Services/Careers`) | `PublicVacancyService` (listing, slug lookup, public DTO, publish/unpublish), `ApplicantAccountService` (register, complete, sign-in), `CareersApplicationService` (apply, withdraw, profile, documents), `CandidateStatusPresenter`, `PortalEventRecorder` |
| Reused services | `ApplicationService`, `ApplicationPipelineService`, `ApplicantService`, `ApplicantDocumentService`, `NumberSequenceService` (APL-), `SelectionService` |
| Controllers | `Careers\CareersController`, `ApplicantAuthController`, `PublicApplicationController`, `ApplicantPortalController`; HR: `Web\Recruitment\VacancyPublicationController` |
| Middleware | `Careers\AuthenticateApplicant`, `Careers\ShareCareersPortal` |
| UI | `layouts/CareersLayout.vue`, `pages/careers/*` (job list and detail, apply, my applications and detail, profile, register, complete, sign in, forgot and reset password) |

## 2. Public/internal security boundary
- **Separate authentication domain.** Candidates sign in with the `applicant` session guard (provider `applicant_accounts`, model `ApplicantAccount`). It is registered in `AuthServiceProvider`, so `config/auth.php` is untouched.
- **No internal access.** Accounts have no roles or permissions. The default guard stays `web`, so every internal route (`auth`, `can:`) treats a candidate as a guest.
- **Scoped lookups.** Candidate pages derive everything from the signed-in account's `applicant_id`. Another candidate's application number or document id returns 404, never 403, so nothing confirms that a record exists.
- **Public props are fixed DTOs:**
  - vacancies: `PublicVacancyService::present`
  - applications: `CareersApplicationService::presentApplication`
  - the shared `portal` prop: company name and contact, careers texts, the candidate's name
- **Never sent to the public:** ids, hiring manager, requisition, salary (unless opted in), stage names, screening, interviews, scorecards, selection, offer approvals, rejection reasons or notes.
- **Login audit listeners.** The existing HRMS listeners (`LogSuccessfulLogin` / `Logout` / `FailedLogin`) skip the `applicant` guard. The employee auth log stays employee-only; otherwise they crashed on accounts without roles.

## 3. Vacancy publication
`vacancies.visibility` (internal / external / both) already says who a vacancy is for. Publishing is an explicit HR action:
- **Columns:** `published_at`, `published_by`, a stable `public_slug`, and `show_salary_publicly` (off by default).
- **Public** = `status = open` + visibility external/both + published + `closing_date` today or later (the closing day is included). Enforced by `Vacancy::scopePubliclyVisible()` / `isPubliclyOpen()`.
- **Slugs:** `title-xxxxxx` (6 random characters), created on first publication and never regenerated, so links survive title changes and unpublish/republish. Unpublished, closed, expired and unknown slugs return a plain 404.
- **HR controls:** on the vacancy page, with the existing `recruitment.vacancy.publish` permission (no new permission):
  - Publish / Update (salary display)
  - Unpublish
  - Preview: HR only, makes nothing public

## 4. Applicant registration
The form collects only applicant fields (names, email, phone, address) plus **privacy consent**, which is required and stored in the existing `applicants.privacy_consent` / `privacy_consented_at` columns.
No password is asked at this point.

## 5. Applicant authentication
1. **Register:** the profile is stored encrypted on an unactivated `applicant_accounts` row, and a signed link (24 h) is emailed. The response is always the same generic message.
2. **Link:** proves the address. The candidate then **chooses a password**, and the account is linked:
   - to the applicant with that email, if one exists and has no account (an HR-created record, never overwritten or merged);
   - otherwise a new applicant is created through `ApplicantService`.
   - A phone shared with another applicant, or an ambiguous email, sends the candidate to HR with a message that reveals nothing.
   - **Why the password comes after the link:** choosing it only after proving the email stops anyone from registering someone else's email with a password they know and later reading that person's applications.
3. **Sign in:** only activated, active accounts. The session is regenerated, and the account is throttled per IP and per email.
4. **Forgot / reset password:** Laravel's password broker `applicant_accounts`, with its own `applicant_password_reset_tokens` table. The legacy employee reset flow is not used: it depends on the removed `PersonalInformation` model.

**Mail requirement:** registration and reset need working outgoing mail (the app's existing mailer). Without it, candidates can't activate accounts. There is no fake verification.

## 6. Application submission
`CareersApplicationService::submit` runs in one transaction:
1. lock the vacancy row first;
2. store new uploads (`ApplicantDocumentService`);
3. check own-document ids and the required document types;
4. call `ApplicationService::createApplication(..., public: true)`. Under the same lock it re-checks that the vacancy is public, open and before its closing date, enforces one application per vacancy (unique index), issues the APL number, records the first stage history, and attaches the documents;
5. write the portal event.

Any failure rolls everything back and deletes new files. The source is the existing "Company website" recruitment source. Openings are untouched: selection reserves them, not applying.

## 7. Document handling
- **Accepted files:** PDF, DOC, DOCX, JPG or PNG, up to 5 MB, at most 10 per submission.
- **Checks:** the extension, the allow-listed extension inferred from content, and the detected MIME type must all pass.
- **Storage:** the existing private disk with random names. Paths are never serialized.
- **Downloads:** through `careers.documents.download`, scoped to the candidate's own documents.
- **Deletion:** documents already submitted with an application can't be deleted (existing rule).
- **Required types:** configured per applicant document type (`required_online`, Recruitment → Settings → Careers site).

## 8. Candidate status mapping (`CandidateStatusPresenter`)
| Internal | Candidate sees |
|---|---|
| stage Applied | Application submitted |
| stage Screening / Shortlisted | Under review |
| stage Interview | Interview |
| stage Assessment | Assessment |
| stage Evaluation (including a selection or an offer not yet issued) | Evaluation |
| offer issued | Offer available |
| offer accepted or converted | Offer accepted |
| offer declined / expired, application rejected | Application closed |
| withdrawn | Application withdrawn |

A selection, and an offer still in draft or approval, stay "Evaluation" because they can still change. The candidate sees only the stage: no scores, scorecards, panelists, recommendations, approvals or offer details.

## 9. Withdrawal
`ApplicationPipelineService::withdrawByCandidate`:
- the same transition and stage history as HR's withdrawal, recorded with no HR actor and remarks starting "Withdrawn by the candidate";
- it releases a selected opening;
- it is refused once an offer is in play or accepted, or the candidate was converted. They contact HR instead.

## 10. Privacy
- Consent is required and timestamped.
- The pending registration profile is encrypted at rest and cleared on activation.
- Candidates can update contact details, education and work history; name and email changes go through HR. Applications reference the live applicant record (the Phase 2 design, unchanged).
- No automatic retention or anonymization yet.

## 11. Security
- CSRF on every form (`web` group).
- Signed, email-hash-bound activation links.
- Password policy: at least 10 characters, with letters and numbers.
- No mass assignment: public requests can't set status, stage, ids or employee links.
- Publishing requires HR permission; preview requires HR sign-in.

## 12. Rate limiting (`RouteServiceProvider`)
- `careers-browse`: 120 per minute per IP.
- `careers-auth`: 10 per minute per IP and 5 per minute per email (register, resend, complete, sign in, forgot, reset).
- `careers-apply`: 10 per minute per candidate (apply, withdraw, profile, documents).

No CAPTCHA exists in the project; it is left as a future enhancement.

## 13. Concurrency
- **Submissions:** lock the vacancy before writing anything that references it, the same order as `createApplication`, so concurrent submissions queue instead of deadlocking.
- **Duplicate applications:** blocked by the unique index.
- **Closure, unpublish and HR stage moves:** lock the same vacancy or application rows, so the outcome is deterministic.
- **Profile updates:** use `ApplicantService`'s lock order (sequence row, then applicant), and the event is written inside that transaction.

## 14. MariaDB tests (`tests/Concurrency/CareersPortalConcurrencyTest.php`)
1. 6 identical submissions → 1 application, 1 document, 1 file.
2. 6 different candidates at once → 6 applications, gap-free numbers.
3. Submissions racing a vacancy closure → only those committed before the closure exist; refused attempts leave no documents or files.
4. Submissions racing an unpublish → the same.
5. Candidate withdrawal racing HR stage moves → one withdrawal; a move only if it came first; consistent history.
6. 6 concurrent profile updates → one consistent record; education replaced, never duplicated.
7. No `ON UPDATE` timestamp columns.

Writing these tests found two deadlocks, both fixed: the submission lock order, and the profile event written outside its transaction.

## 15. Configuration
- **Careers site texts:** headline, introduction, application instructions and privacy notice (Recruitment → Settings, `recruitment.config.manage`).
- **Company name, contact and logo:** reused from Organization and Corporate Branding.

## 16. Known limitations
- Registration and password reset depend on working outgoing mail.
- No candidate-facing interview or offer details (no safe representation exists yet).
- No CAPTCHA.
- No data-retention automation.
- Candidates can't change their name or email themselves.

## 17. Future enhancements
CAPTCHA, retention and anonymization policies, candidate-facing interview scheduling and offer letters, talent pools, job-board feeds, resume parsing, reporting.
