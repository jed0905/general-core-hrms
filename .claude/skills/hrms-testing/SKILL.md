---
name: hrms-testing
description: Use when writing, running, or fixing PHPUnit tests in the General Core HRMS - choosing a base test case, creating users with roles, factories, freezing time, testing permission matrices, balances, approvals or payroll math, running the MySQL concurrency suite, or when tests try to connect to MySQL.
---

# HRMS Testing

Project mechanics only. For *what* to test and how to design it, use
`testing-best-practices`. For red-green discipline, use `superpowers:test-driven-development`.

## Environment (already safe, keep it that way)

- `phpunit.xml` forces in-memory SQLite (`force="true"`). All migrations are
  SQLite-compatible because MySQL-only `CHECK` constraints are driver-guarded.
  New migrations must stay compatible (see `hrms-database-safety`).
- CLAUDE.md's warning that tests use `.env.testing` MySQL is outdated. The forced
  SQLite override is the current truth. If a run hits MySQL, run `php artisan config:clear`.
- `tests/Feature/MaintenanceLeavesControllerTest.php` is legacy and targets
  missing code. Don't fix or delete it without asking. Exclude it from your runs by
  pointing at the specific files or folders.

## Running

```bash
php artisan test --compact tests/Feature/Leave                 # a folder
php artisan test --compact --filter=submitting_reserves_pending_days
vendor/bin/phpunit tests/Feature/Onboarding/OnboardingTaskTest.php
```

Run the narrowest set that covers your change. Before claiming done, run the
whole folder of the module you touched, and quote the real pass/fail output.

## Base test cases: extend, don't re-create

| Base | Gives you |
|---|---|
| `Tests\Feature\Leave\LeaveTestCase` | `RefreshDatabase` + the **SQLite-only guard** (`beforeRefreshingDatabase`), `withoutVite()`, seeded `RolesAndPermissionsSeeder`, `userWithRole($role, $employeeAttrs)`, `employeeOf()`, `balanceFor()`, leave type/policy/rule fixtures |
| `Tests\Feature\Onboarding\OnboardingTestCase` | Extends the above. Named cast of users (HR, manager, supervisor, employees) and `Carbon::setTestNow` |
| `tests/Feature/{Recruitment,People,Reports,EmployeeMovement,SelfService,Time,Dashboard,Careers}` | Check for a module `*TestCase.php` first |

A new module gets `tests/Feature/<Module>/<Module>TestCase.php` extending
`LeaveTestCase` (or copying its guard + `userWithRole`), and starts with a docblock
describing the fixture cast.

## Conventions seen in the suite

- PHPUnit 11 attributes: `use PHPUnit\Framework\Attributes\Test;` then `#[Test]` on
  `public function snake_case_behaviour_sentence(): void`.
- A class docblock states the invariant under test
  (`available = balance - pending; approval moves pending -> used ...`).
- Drive behaviour through HTTP with named routes:
  `$this->actingAs($user)->post(route('leave.applications.store'), $payload)->assertSessionHasNoErrors();`
  Assert forbidden paths with `->assertForbidden()`, and assert validation with `->assertSessionHasErrors('field')`.
- Freeze time: `Carbon::setTestNow('2026-10-10 09:00:00')` in `setUp`, and reset it in `tearDown`.
  This is mandatory for effective-dated logic (policies, schedules, payroll tables).
- Factories available: `Employee`, `User`, `EmployeeLeaveBalance`, `LeaveApplication`,
  `LeaveApprovalWorkflow(Step)`, `LeavePolicy(Rule)`, `LeaveType`. (`LeaveFactory` is legacy.)
  Add factories for new models. Otherwise create data through the module's service,
  as `OnboardingTestCase::template()` does, so fixtures obey business rules.

## Required coverage for HRMS business logic

- **Permission matrix**: each seeded role is allowed or denied per route
  (model: `LeavePermissionMatrixTest`), plus ownership, state, and wrong-approver denials.
- **State machine**: every allowed transition, and terminal states can't be re-acted on.
- **Balances and money**: exact before and after values for every counter. Money is asserted as
  strings with `assertSame` (see `payroll-calculation-engine`).
- **Snapshots**: changing configuration after submission leaves existing records unchanged.

## Payroll test layout (international)

```
tests/Unit/Payroll/                          core math (Decimal, rounding, proration)
tests/Feature/Payroll/                       core runs, lifecycle, permissions, entity scoping
tests/{Unit,Feature}/Payroll/Localizations/<CC>/   one independent suite per country
```

- **Core tests use a fictional localization** (user-assigned ISO code such as `XX`, with fixture
  rules). They must pass without any real country registered. That is the proof the core is country-neutral.
- **Each country suite runs on its own**
  (`php artisan test --compact tests/Unit/Payroll/Localizations/PH`) and doesn't depend on another
  country's suite or fixtures.
- Statutory fixture values are **fictional and labelled as such**. Never paste real rates into tests.
- Required: currencies with different minor units, each rounding mode, employer contributions
  not affecting net, effective-date boundaries, a period spanning two rule versions,
  historical reproducibility after a newer rule is verified, and immutability of finalized runs.
- Freeze time (`Carbon::setTestNow`) and pass explicit legal-entity timezones. Don't rely on the
  app's `Asia/Manila` default.

## Concurrency suite (real MySQL)

`tests/Concurrency/*` covers races (double approvals, duplicate numbers, one active
case per employee). They aren't in a phpunit testsuite and they skip unless:

```bash
HRMS_CONCURRENCY_DB=hrms_concurrency_test vendor/bin/phpunit tests/Concurrency/OnboardingConcurrencyTest.php
```

The workers create and drop that scratch database and refuse any name that doesn't
end in `_concurrency_test`. Ask before running, because a MySQL server must be available.
New lock-sensitive features (payroll finalization, number issuance, balance moves)
should add a test and worker following `OnboardingConcurrencyTest` + `onboarding_worker.php`.
