<?php

namespace Tests\Feature\Recruitment;

use App\Models\RecruitmentSource;
use App\Models\RecruitmentStage;
use App\Models\RejectionReason;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Phase 2 permissions, their rollout, recruitment security boundaries and settings.
 */
class RecruitmentPhase2AccessTest extends RecruitmentTestCase
{
    private const PHASE2 = [
        'recruitment.applicant.view', 'recruitment.applicant.create', 'recruitment.applicant.update',
        'recruitment.application.view', 'recruitment.application.create', 'recruitment.application.move_stage',
        'recruitment.application.reject', 'recruitment.application.withdraw',
        'recruitment.screening.view', 'recruitment.screening.create', 'recruitment.screening.update',
        'recruitment.shortlist.create', 'recruitment.config.manage',
    ];

    private function phase2Of(string $role): array
    {
        return Role::findByName($role)->permissions->pluck('name')->intersect(self::PHASE2)->sort()->values()->all();
    }

    #[Test]
    public function roles_receive_the_intended_phase2_permissions(): void
    {
        $all = collect(self::PHASE2)->sort()->values()->all();

        foreach (['superadmin', 'hr_director', 'hr_manager'] as $role) {
            $this->assertSame($all, $this->phase2Of($role), $role);
        }
        $this->assertSame(array_values(array_diff($all, ['recruitment.config.manage'])), $this->phase2Of('hr_staff'));
        foreach (['supervisor', 'employee', 'payroll'] as $role) {
            $this->assertSame([], $this->phase2Of($role), $role);
        }

        // Post-phase-5 permissions (onboarding, careers) don't exist yet; conversion arrived in phase 5 (recruitment.conversion.*).
        $this->assertSame(0, Permission::where(fn ($q) => $q->where('name', 'like', 'recruitment.hire%')
            ->orWhere('name', 'like', 'recruitment.onboarding%')->orWhere('name', 'like', 'recruitment.careers%'))->count());
    }

    #[Test]
    public function the_migration_and_seeder_are_idempotent_and_never_revoke(): void
    {
        Role::findByName('hr_staff')->revokePermissionTo('recruitment.screening.update'); // a company customization
        $before = Permission::count();
        $migration = require database_path('migrations/2026_10_05_000005_add_recruitment_phase2_permissions.php');

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertSame($before, Permission::count());
        $this->assertFalse(Role::findByName('hr_staff')->hasPermissionTo('recruitment.screening.update'), 'seeder keeps customizations');

        $unrelated = Role::all()->mapWithKeys(fn ($r) => [$r->name => $r->permissions->pluck('name')->diff(self::PHASE2)->sort()->values()->all()]);
        $migration->up();
        $migration->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->assertSame($before, Permission::count());
        $this->assertEquals($unrelated, Role::all()->mapWithKeys(fn ($r) => [$r->name => $r->fresh()->permissions->pluck('name')->diff(self::PHASE2)->sort()->values()->all()]));
    }

    #[Test]
    public function employees_and_supervisors_cannot_reach_recruitment_administration(): void
    {
        $app = $this->applyTo($this->openVacancy());
        $routes = fn () => [
            route('recruitment.applicants.index'), route('recruitment.applicants.create'),
            route('recruitment.applicants.show', $app->applicant_id), route('recruitment.applications.index'),
            route('recruitment.applications.show', $app), route('recruitment.settings.index'),
        ];

        foreach ([$this->userWithRole('employee'), $this->userWithRole('payroll'), $this->maya, $this->ramon] as $user) {
            foreach ($routes() as $url) {
                $this->actingAs($user)->get($url)->assertForbidden();
            }
        }

        $flags = $this->actingAs($this->maya)->get(route('dashboard.index'))->viewData('page')['props']['recruitmentAccess'];
        $this->assertSame([false, false, false], [$flags['applicants'], $flags['applications'], $flags['settings']]);
        $flags = $this->actingAs($this->hrStaff)->get(route('dashboard.index'))->viewData('page')['props']['recruitmentAccess'];
        $this->assertSame([true, true, false], [$flags['applicants'], $flags['applications'], $flags['settings']]);
    }

    #[Test]
    public function settings_manage_sources_reasons_and_stage_names(): void
    {
        $this->actingAs($this->hrStaff)->get(route('recruitment.settings.index'))->assertForbidden();
        $this->actingAs($this->hrStaff)->post(route('recruitment.settings.reasons.store'), ['name' => 'x'])->assertForbidden();

        $this->actingAs($this->dina)->get(route('recruitment.settings.index'))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component('app/Recruitment/Settings/Index', false)->has('stages', 6)->has('reasons', 6)->has('sources', 8));

        $this->actingAs($this->dina)->post(route('recruitment.settings.reasons.store'), ['name' => 'Salary expectations'])->assertSessionHasNoErrors();
        $this->actingAs($this->dina)->post(route('recruitment.settings.sources.store'), ['name' => 'Job Fair'])->assertSessionHasNoErrors();
        $this->assertSame('salary_expectations', RejectionReason::where('name', 'Salary expectations')->value('code'));

        $reason = RejectionReason::where('code', 'other')->firstOrFail();
        $this->actingAs($this->dina)->put(route('recruitment.settings.reasons.update', $reason), ['name' => 'Other reason', 'is_active' => false])->assertSessionHasNoErrors();
        $this->assertSame(['Other reason', false, 'other'], [$reason->fresh()->name, $reason->fresh()->is_active, $reason->fresh()->code]);

        $source = RecruitmentSource::where('code', 'walk_in')->firstOrFail();
        $this->actingAs($this->dina)->put(route('recruitment.settings.sources.update', $source), ['name' => 'Walk-in / Office'])->assertSessionHasNoErrors();

        $stage = RecruitmentStage::where('code', 'screening')->firstOrFail();
        $this->actingAs($this->dina)->put(route('recruitment.settings.stages.update', $stage), ['name' => 'Initial screening', 'stage_type' => 'offer'])->assertSessionHasNoErrors();
        $this->assertSame(['Initial screening', 'screening'], [$stage->fresh()->name, $stage->fresh()->stage_type], 'only the name changes');
    }
}
