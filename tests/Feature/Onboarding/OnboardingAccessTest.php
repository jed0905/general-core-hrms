<?php

namespace Tests\Feature\Onboarding;

use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Onboarding permissions, their rollout and the recruitment hand-off link.
 */
class OnboardingAccessTest extends OnboardingTestCase
{
    private const HR = ['onboarding.view', 'onboarding.create', 'onboarding.update', 'onboarding.complete', 'onboarding.cancel', 'onboarding.task.update', 'onboarding.task.verify', 'onboarding.template.view', 'onboarding.template.create', 'onboarding.template.update'];

    private const SELF = ['onboarding.view_own', 'onboarding.update_own'];

    private function onboardingOf(string $role): array
    {
        return Role::findByName($role)->permissions->pluck('name')->filter(fn ($n) => str_starts_with($n, 'onboarding.'))->sort()->values()->all();
    }

    #[Test]
    public function roles_receive_the_intended_onboarding_permissions(): void
    {
        $all = collect([...self::HR, ...self::SELF])->sort()->values()->all();
        foreach (['superadmin', 'hr_director', 'hr_manager'] as $role) {
            $this->assertSame($all, $this->onboardingOf($role), $role);
        }
        $this->assertSame(collect(array_diff($all, ['onboarding.cancel', 'onboarding.template.create', 'onboarding.template.update']))->values()->all(), $this->onboardingOf('hr_staff'));
        foreach (['supervisor', 'employee', 'payroll'] as $role) {
            $this->assertSame(collect(self::SELF)->sort()->values()->all(), $this->onboardingOf($role), $role);
        }
    }

    #[Test]
    public function the_migration_and_seeder_are_idempotent_and_never_revoke(): void
    {
        Role::findByName('hr_staff')->revokePermissionTo('onboarding.complete'); // a company customization
        $before = Permission::count();
        $migration = require database_path('migrations/2026_10_09_000003_add_onboarding_permissions.php');

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->assertSame($before, Permission::count());
        $this->assertFalse(Role::findByName('hr_staff')->hasPermissionTo('onboarding.complete'));

        $migration->up();
        $migration->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->assertSame($before, Permission::count());
    }

    #[Test]
    public function hr_starts_onboarding_from_a_prefilled_form(): void
    {
        $template = $this->template();
        $this->actingAs($this->hr)->get(route('people.onboarding.create', ['employee_id' => $this->nina->employee_id]))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component('app/People/Onboarding/Create', false)
                ->where('prefill.employee_id', (string) $this->nina->employee_id)->has('templates', 1)->where('templates.0.id', $template->id));
        $this->actingAs($this->nina)->get(route('people.onboarding.create'))->assertForbidden();
    }
}
