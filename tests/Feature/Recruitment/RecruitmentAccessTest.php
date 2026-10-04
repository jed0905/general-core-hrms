<?php

namespace Tests\Feature\Recruitment;

use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Recruitment permissions, their rollout (migration + seeder), the dashboard
 * gate and the navigation flags shared with every page.
 */
class RecruitmentAccessTest extends RecruitmentTestCase
{
    private const ALL = [
        'recruitment.requisition.view', 'recruitment.requisition.create', 'recruitment.requisition.update',
        'recruitment.requisition.submit', 'recruitment.requisition.approve', 'recruitment.requisition.cancel',
        'recruitment.vacancy.view', 'recruitment.vacancy.create', 'recruitment.vacancy.update',
        'recruitment.vacancy.publish', 'recruitment.vacancy.close',
    ];

    private function recruitmentPermissionsOf(string $role): array
    {
        // Phase 1 permissions only (later phases add more recruitment.* permissions).
        return Role::findByName($role)->permissions->pluck('name')->intersect(self::ALL)->sort()->values()->all();
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_10_04_000005_add_recruitment_permissions.php');
    }

    #[Test]
    public function roles_receive_the_intended_recruitment_permissions(): void
    {
        $all = collect(self::ALL)->sort()->values()->all();

        $this->assertSame($all, $this->recruitmentPermissionsOf('superadmin'));
        $this->assertSame($all, $this->recruitmentPermissionsOf('hr_director'));
        $this->assertSame($all, $this->recruitmentPermissionsOf('hr_manager'));
        $this->assertSame(array_values(array_diff($all, ['recruitment.requisition.approve', 'recruitment.vacancy.close'])), $this->recruitmentPermissionsOf('hr_staff'));
        $this->assertSame(['recruitment.requisition.approve', 'recruitment.requisition.cancel', 'recruitment.requisition.create', 'recruitment.requisition.submit', 'recruitment.requisition.update'], $this->recruitmentPermissionsOf('supervisor'));
        $this->assertSame([], $this->recruitmentPermissionsOf('employee'));
        $this->assertSame([], $this->recruitmentPermissionsOf('payroll'));

        // No onboarding/careers permissions yet (later phases added applicants, interviews, selection, offers and conversion).
        $this->assertSame(0, Permission::where('name', 'like', 'recruitment.hire%')->orWhere('name', 'like', 'recruitment.onboarding%')->orWhere('name', 'like', 'recruitment.careers%')->count());
    }

    #[Test]
    public function the_permission_migration_is_idempotent_and_never_revokes(): void
    {
        $hrStaff = Role::findByName('hr_staff');
        $hrStaff->givePermissionTo('dashboard.view');
        $before = Permission::count();
        $unrelated = Role::all()->mapWithKeys(fn ($r) => [$r->name => $r->permissions->pluck('name')->reject(fn ($p) => str_starts_with($p, 'recruitment.'))->sort()->values()->all()]);

        $this->migration()->up();
        $this->migration()->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertSame($before, Permission::count(), 'no duplicate permissions');
        $this->assertEquals($unrelated, Role::all()->mapWithKeys(fn ($r) => [$r->fresh()->name => $r->fresh()->permissions->pluck('name')->reject(fn ($p) => str_starts_with($p, 'recruitment.'))->sort()->values()->all()]));
    }

    #[Test]
    public function the_seeder_is_idempotent_and_keeps_customizations(): void
    {
        Role::findByName('hr_staff')->revokePermissionTo('recruitment.vacancy.publish'); // a company customization
        $before = Permission::count();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertSame($before, Permission::count());
        $this->assertFalse(Role::findByName('hr_staff')->hasPermissionTo('recruitment.vacancy.publish'));
        $this->assertTrue(Role::findByName('superadmin')->hasPermissionTo('recruitment.vacancy.close'));
    }

    #[Test]
    public function the_dashboard_is_open_to_anyone_with_recruitment_access(): void
    {
        $this->actingAs($this->hrStaff)->get(route('recruitment.dashboard'))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component('app/Recruitment/Dashboard/Index', false));
        $this->actingAs($this->ramon)->get(route('recruitment.dashboard'))->assertOk();
        $this->actingAs($this->userWithRole('employee'))->get(route('recruitment.dashboard'))->assertForbidden();
        $this->actingAs($this->userWithRole('payroll'))->get(route('recruitment.dashboard'))->assertForbidden();
    }

    #[Test]
    public function the_dashboard_shows_pending_approvals_and_scoped_counts(): void
    {
        $req = $this->submittedRequisition();
        $this->draftRequisition([], $this->outsider);

        $this->actingAs($this->maya)->get(route('recruitment.dashboard'))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('awaitingMyApproval.0.id', $req->id)
                ->where('requisitionCounts.pending_approval', 1)
                ->where('requisitionCounts.draft', 0)
                ->where('access.vacancies', false));

        $this->actingAs($this->hrStaff)->get(route('recruitment.dashboard'))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('requisitionCounts.draft', 1)->where('requisitionCounts.pending_approval', 1)->where('awaitingMyApproval', []));
    }

    #[Test]
    public function navigation_flags_follow_permissions_and_assignments(): void
    {
        $flags = fn ($user) => $this->actingAs($user)->get(route('dashboard.index'))->viewData('page')['props']['recruitmentAccess'];

        // Phase 2 added applicants, applications and settings to the same flag set.
        $only = fn (array $flags) => array_intersect_key($flags, array_flip(['dashboard', 'requisitions', 'vacancies']));

        $this->assertSame(['dashboard' => true, 'requisitions' => true, 'vacancies' => true], $only($flags($this->hrStaff)));
        $this->assertSame(['dashboard' => true, 'requisitions' => true, 'vacancies' => false], $only($flags($this->maya)));
        $this->assertSame(['dashboard' => false, 'requisitions' => false, 'vacancies' => false], $only($flags($this->userWithRole('employee'))));

        // Becoming a hiring manager is what opens Vacancies for a supervisor.
        $this->openVacancy(['hiring_manager_id' => $this->maya->employee_id]);
        $this->assertSame(['dashboard' => true, 'requisitions' => true, 'vacancies' => true], $only($flags($this->maya)));
    }
}
