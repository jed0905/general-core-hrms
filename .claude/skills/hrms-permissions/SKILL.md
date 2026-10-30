---
name: hrms-permissions
description: Use when adding or changing a permission, role default, policy, route authorization, FormRequest authorize(), sidebar menu visibility, or per-record "can" flags in the General Core HRMS (spatie/laravel-permission 6) - or when a user sees a 403, a menu item is missing, or a role has too much access.
---

# HRMS Permissions

Authorization has three layers. Every feature needs all three:

1. **Permission** (spatie): can this user do this kind of thing at all?
2. **Policy**: may they do it to *this* record, in *this* state?
3. **UI flag**: the server computes `can` and the client only hides what isn't allowed.

**Never check role names** (`hasRole('hr_manager')`) in application code.
Roles are templates that companies customize. Check permissions and policies.

## Naming

`<entity>.<action>` or `<module>.<entity>.<action>`, with snake_case segments.
Self-service variants end in `_own`.

| Pattern | Examples in the seeder |
|---|---|
| CRUD + lifecycle | `employee.view`, `employee.archive`, `employee.restore` |
| Sub-areas | `employee.compensation.view`, `employee.documents.delete` |
| Module-scoped | `recruitment.requisition.approve`, `leave.balance.adjust` |
| Self-service | `employee.view_own`, `leave.cancel_own`, `attendance.export_own` |
| Reports | `report.leave`, `report.recruitment` |

Before inventing a name, grep `database/seeders/RolesAndPermissionsSeeder.php` and
`database/migrations/*permission*`. The seeder already has a **commented-out
reserved set** for payroll (`payroll.view/create/update/calculate/review/approve/reject/finalize/export`,
`compensation.view/create/update/approve`). Use those names, and seed them as below.

## Payroll and salary access (international)

Salary data is the most sensitive data in the HRMS, so keep its permissions granular.

- **Separate abilities**, never one `payroll.manage`: view vs edit compensation
  (`employee.compensation.view/update`, `compensation.*`), and run steps
  (`payroll.calculate`, `payroll.review`, `payroll.approve`, `payroll.finalize`, `payroll.export`)
  held by different people. Policies enforce separation of duties: the user who calculated a run
  can't approve or finalize it.
- **Statutory rules** need their own abilities, distinct from running payroll (proposed:
  `payroll.statutory_rule.view`, `.manage`, `.verify`). Whoever drafts a rule version can't verify it.
- **Self-service**: own payslips only (proposed `payslip.view_own`) + `employee_id` ownership.
- **Scope by legal entity / country.** spatie `teams` is disabled (`config/permission.php`), so a
  global permission alone would expose every country's payroll. Scope through a policy that checks
  an explicit assignment of the user to the legal entities or pay groups they administer
  (an assignment table, as Recruitment uses assignments). Never scope by role name or nationality.
- Index queries apply the same scope in the service (`whereIn('legal_entity_id', $scope)`), and
  so do exports and consolidated reports. Consolidated cross-country views need their own permission.
- The proposed names above are not seeded yet. Confirm them with the user and add them through seeder + migration.

## Adding a permission: both places, every time

The seeder's `seedDefaultPermissions()` grants defaults **only when a role is first
created**, so editing the seeder alone never reaches deployed databases.

1. **Seeder** (`RolesAndPermissionsSeeder`): add the name to `$permissions` and to the
   default arrays of the right roles (superadmin, hr_director, hr_manager, hr_staff,
   payroll, supervisor, employee). Keeping it here makes fresh installs and tests correct.
2. **Migration** for deployed databases. Copy
   `2026_10_09_000003_add_onboarding_permissions.php` /
   `2026_09_30_000004_rename_and_grant_employee_self_service_permissions.php`:
   - `forgetCachedPermissions()` before and after.
   - `Permission::firstOrCreate(['name' => ..., 'guard_name' => 'web'])`.
   - Look up each role by name, `continue` if it is missing, then `givePermissionTo()`.
   - Grant only the **new** permissions. Never `syncPermissions()`, which
     wipes a company's customizations.
   - `down()` revokes the grants and deletes only the permissions it created.
   - Rename in place (update `name`) instead of create-and-delete, so holders keep access.

## Route authorization

Every route gets middleware, without exception:

```php
// Permission-only (Leave, Time, Administration style)
->middleware('can:leave.balance.adjust')
// Policy (Recruitment style, preferred for record-level rules)
->middleware('can:viewAny,'.JobRequisition::class)   // class-level
->middleware('can:approve,requisition')              // bound {requisition}
```

## Policies

- Copy `app/Policies/JobRequisitionPolicy.php`. Start with a docblock stating who
  sees what. Each ability combines **permission AND state AND relationship**:
  ```php
  return $requisition->isPending()
      && $user->can('recruitment.requisition.approve')
      && $requisition->currentApproval()?->approver_id === $user->employee_id;
  ```
- Register the policy in `AuthServiceProvider::$policies`. Every ability that a route,
  controller, or test calls must exist on the policy.
- Self-service: `_own` permission **and** `$record->employee_id === $user->employee_id`.
  `users.employee_id` is nullable, so handle `null`.
- Separation of duties: requesters can't approve their own records. Exclude them
  (as `ApprovalChainResolver` does with `$excludedApproverIds`).
- `LeaveApplicationPolicy` is legacy. Rewrite it; don't extend it.
- Gates are only for cross-cutting access (`accessRecruitment`).

## FormRequest authorize()

A real check: `$this->user()->can('update', $this->route('requisition'))` or
`$this->user()->can('<permission>')`. Never a generated `return false;` or a
blanket `return true;` on a sensitive action.

## Frontend

- Page actions: `can` array props computed in the controller (`'can' => ['create' => $user->can('create', X::class)]`).
- Generic checks: the `resources/js/mixins/permissions.js` mixin `can(permission)`
  reads `$page.props.auth.permissions`.
- Menu: `resources/js/layouts/SidebarLayout.vue` `modules()` entries take a
  `permission` (or `access` for recruitment-style assignment-based access).
- UI hiding is cosmetic. The server must always re-check.

## Tests

Seed `RolesAndPermissionsSeeder`, then assert allow **and** deny per role, as in
`tests/Feature/Leave/LeavePermissionMatrixTest.php`. Include: no permission → 403,
wrong owner → 403, wrong state → 403, and an approver who isn't on the current step → 403.
For payroll, also cover: a user scoped to entity A gets 403 on entity B's run or payslip,
and the calculator of a run can't approve or finalize it.
