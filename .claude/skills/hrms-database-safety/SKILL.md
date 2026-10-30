---
name: hrms-database-safety
description: Use when about to run any artisan migrate/db/seed command, write or change a migration, seed or backfill data, query the database, or run tests that touch a database in the General Core HRMS - and whenever a command could drop, truncate, wipe, overwrite, or bulk-update tables holding employee, leave, recruitment, payroll, or statutory rule data.
---

# HRMS Database Safety

This database holds real employee, leave, and (soon) payroll records. Treat
every database the user has not explicitly called disposable as production.

## Prohibited unless the user explicitly instructs it for a named database

| Never run | Why |
|---|---|
| `migrate:fresh`, `migrate:reset`, `migrate:refresh`, `migrate:rollback` | Drops or reverses tables holding real data |
| `db:wipe` | Drops everything |
| `db:seed` / `--seed` against a shared DB | Seeders `firstOrCreate` but can still insert demo rows. `LeaveSeeder` and `GovernmentPositionsSeeder` are legacy. |
| Raw `DROP`, `TRUNCATE`, `DELETE` without a narrow `WHERE`, `ALTER ... DROP COLUMN` | Irreversible data loss |
| `Model::query()->delete()` / `->update([...])` over a whole table in tinker | Same |
| Editing an existing migration file | Already ran on deployed databases, so edits never apply there and fork the schema |
| Changing `.env`, `.env.*`, `config/database.php`, or `phpunit.xml` DB settings | Can point tests or tooling at real data |
| Removing the test-safety guards (below) | They are the last line of defence |

"The user said fix the database" is **not** authorization to wipe it. If a task
seems to need any of the above, stop and ask, naming the database
(`php artisan config:show database.default`, and the database name **without**
printing credentials).

## Safe by default

- Read the schema with the Boost `database-schema` tool, and read data with
  `database-query` (read-only). Prefer them over tinker.
- `php artisan migrate:status` and `php artisan migrate --pretend` are read-only.
- Run plain `php artisan migrate` only on the local dev database, and only after
  the user agrees.

## Writing migrations

Always a **new** migration (`php artisan make:migration ... --no-interaction`). Copy
the style of `2026_10_07_000002_create_job_offers_tables.php`:

- Docblock at the top explaining the invariants the table enforces.
- `foreignId()->constrained()` with an explicit `restrictOnDelete()` for
  financial, audit, or history data, `nullOnDelete()` for optional actors, and
  `cascadeOnDelete()` only for pure children.
- Explicit short index names (MySQL has a 64-character limit):
  `$table->index(['job_offer_id', 'id'], 'job_offer_events_offer_idx');`
- Money and quantities: `decimal(p, s)`, never `float`/`double`/`string`. Existing
  non-payroll money uses `decimal(12, 2)`. Payroll amounts use the precision in
  `payroll-calculation-engine` plus a currency column. `pay_grade_currencies` still
  uses `double`; flag it, and don't copy it.
- CHECK constraints go inside a driver guard so in-memory SQLite tests still migrate:
  ```php
  if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
      DB::statement("ALTER TABLE x ADD CONSTRAINT x_status_check CHECK (status IN ('draft', 'posted'))");
  }
  ```
- Reference data the feature needs (number sequence keys, default workflows,
  permissions) is inserted idempotently in the migration (`insertOrIgnore`,
  `exists()` check) and removed in `down()`.
- `down()` must work and must only undo this migration.
- Altering a column: restate **all** of its existing attributes, or they are dropped.
- Never rename or drop a column other code reads. Add a new one and keep both,
  then ask before removing the old one.

## Data changes and backfills

- Put them in a migration or a dedicated command, inside `DB::transaction()`, and
  make them idempotent (re-running changes nothing).
- Scope every `UPDATE`/`DELETE` with a `WHERE` you have tested by running the
  same query as a `SELECT` (count rows first).
- Rows that change balances or money: `lockForUpdate()` inside the transaction.
- Never change finalized business records (approved leave, issued offers,
  posted payroll). Reverse them with a new compensating record.

## Payroll records and statutory tables (all countries)

- **Finalized payroll runs, lines, payslips, filings, and their snapshots** (inputs,
  rule version IDs, exchange rates, currency precision) are never updated or deleted,
  by code, tinker, a backfill, or a migration. Corrections are reversal, adjustment, or
  supplemental runs (`payroll-calculation-engine`).
- **Statutory rule versions** (any country) that are `verified`, or that a finalized line
  references, are read-only and never deleted. A change in law is a new version.
  Close the previous `effective_to` in a transaction.
- Retention: payroll data is kept for the longest period any applicable country requires.
  Purging needs an explicit, per-country retention rule plus user instruction. It is never a cleanup task.
- Payroll migrations never seed real statutory values. Rules are entered with their
  official source and verified through the app (`payroll-compliance-localization`).
- Money columns are `decimal` with an ISO 4217 `char(3)` currency beside them. Don't assume a currency.
- Payroll data is sensitive personal data. Don't select, dump, or log salaries, tax IDs, or bank
  accounts while debugging. Use counts and IDs.

## Test-safety guards that must stay in place

- `phpunit.xml` forces `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:` with `force="true"`.
- `Tests\Feature\Leave\LeaveTestCase::beforeRefreshingDatabase()` throws unless
  the connection is in-memory SQLite. Base new `RefreshDatabase` test cases on it,
  or copy the guard.
- `tests/Concurrency/*` run real MySQL only when `HRMS_CONCURRENCY_DB` ends in
  `_concurrency_test`. The workers refuse anything else. Never loosen that regex.
- If tests fail with a MySQL connection, run `php artisan config:clear`. A cached
  config bypasses `phpunit.xml`. **Never "fix" it by pointing tests at MySQL.**

## Red flags: stop

- "It's just the dev DB" when you haven't confirmed it is.
- "I'll edit the old migration since it's simpler."
- "`fresh` will be quicker to get a clean state."
- A command whose output would print `DB_PASSWORD` or a connection URL.
