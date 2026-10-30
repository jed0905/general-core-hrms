# Master Plan: Global Payroll Module

Assignment: design, implement, test and document a Global Payroll Core + Country Localizations,
with the Philippines as the first localization. Source of truth for design:
`docs/development/payroll/ARCHITECTURE.md`; phase breakdown: `docs/development/payroll/IMPLEMENTATION_PLAN.md`.

## Session start checklist
1. Read this file, `PROGRESS.md`, `DECISIONS.md`, `BLOCKERS.md`.
2. `git status` (pre-existing uncommitted changes must be preserved; nothing is committed by the agent).
3. Continue with the first phase in `PROGRESS.md` that is not marked complete.

## Phases
0. Audit & architecture
1. Foundation (schema, models, configuration, compensation, permissions, navigation)
2. Global calculation engine
3. Attendance & leave integration
4. Philippines localization
5. Payroll processing & approval
6. Payslips & disbursement
7. Reports & compliance
8. Hardening & documentation

## Ground rules (from the assignment + CLAUDE.md)
- No destructive DB commands; tests only on in-memory SQLite; no `.env` changes; no commits.
- No statutory value enters the system without an official source and human verification.
- No country branches or statutory values in the core.
