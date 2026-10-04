<?php

namespace Tests\Feature\Recruitment;

use App\Models\Employee;
use App\Models\EmployeeMovement;
use App\Models\NumberSequence;
use App\Models\User;
use App\Services\Recruitment\OfferService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Phase 4 permissions and their rollout, navigation, and the hiring boundary.
 */
class RecruitmentPhase4AccessTest extends RecruitmentTestCase
{
    private const PHASE4 = [
        'recruitment.selection.view', 'recruitment.selection.create',
        'recruitment.offer.view', 'recruitment.offer.create', 'recruitment.offer.update', 'recruitment.offer.approve',
        'recruitment.offer.issue', 'recruitment.offer.respond', 'recruitment.offer.withdraw',
    ];

    private function phase4Of(string $role): array
    {
        return Role::findByName($role)->permissions->pluck('name')->intersect(self::PHASE4)->sort()->values()->all();
    }

    #[Test]
    public function roles_receive_the_intended_phase4_permissions(): void
    {
        $all = collect(self::PHASE4)->sort()->values()->all();

        foreach (['superadmin', 'hr_director', 'hr_manager'] as $role) {
            $this->assertSame($all, $this->phase4Of($role), $role);
        }
        $this->assertSame(array_values(array_diff($all, ['recruitment.offer.approve', 'recruitment.selection.create'])), $this->phase4Of('hr_staff'));
        foreach (['supervisor', 'employee', 'payroll'] as $role) {
            $this->assertSame([], $this->phase4Of($role), $role);
        }

        // Recruitment has no onboarding or careers permissions (conversion is recruitment.conversion.*, phase 5;
        // onboarding is its own People module, onboarding.*, phase 6).
        $this->assertSame(0, Permission::where(fn ($q) => $q->where('name', 'like', 'recruitment.hire%')
            ->orWhere('name', 'like', 'recruitment.onboarding%')->orWhere('name', 'like', '%careers%'))->count());
    }

    #[Test]
    public function the_migration_and_seeder_are_idempotent_and_never_revoke(): void
    {
        Role::findByName('hr_staff')->revokePermissionTo('recruitment.offer.issue'); // a company customization
        $before = Permission::count();
        $migration = require database_path('migrations/2026_10_07_000003_add_recruitment_phase4_permissions.php');

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->assertSame($before, Permission::count());
        $this->assertFalse(Role::findByName('hr_staff')->hasPermissionTo('recruitment.offer.issue'), 'seeder keeps customizations');

        $unrelated = Role::all()->mapWithKeys(fn ($r) => [$r->name => $r->permissions->pluck('name')->diff(self::PHASE4)->sort()->values()->all()]);
        $migration->up();
        $migration->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->assertSame($before, Permission::count());
        $this->assertEquals($unrelated, Role::all()->mapWithKeys(fn ($r) => [$r->name => $r->fresh()->permissions->pluck('name')->diff(self::PHASE4)->sort()->values()->all()]));
    }

    #[Test]
    public function navigation_and_dashboard_show_offers_to_the_right_people(): void
    {
        $manager = $this->hrApprovalChain();
        $app = $this->evaluated($this->openVacancy(['hiring_manager_id' => $this->ramon->employee_id]));
        $this->select($app);
        $offer = app(OfferService::class)->submit($this->draftOffer($app), null, $this->hrStaff);

        $this->actingAs($this->hrStaff)->get(route('recruitment.dashboard'))
            ->assertInertia(fn (AssertableInertia $p) => $p->where('recruitmentAccess.offers', true)->has('offersAwaitingMyApproval', 0));
        $this->actingAs($manager)->get(route('recruitment.dashboard'))
            ->assertInertia(fn (AssertableInertia $p) => $p->has('offersAwaitingMyApproval', 1)->where('offersAwaitingMyApproval.0.id', $offer->id));
        $this->actingAs($manager)->get(route('recruitment.offers.index', ['awaiting' => 1]))
            ->assertInertia(fn (AssertableInertia $p) => $p->has('offers.data', 1));
        $this->actingAs($this->ramon)->get(route('recruitment.dashboard'))
            ->assertInertia(fn (AssertableInertia $p) => $p->where('recruitmentAccess.offers', true));

        foreach ([$this->userWithRole('employee'), $this->userWithRole('payroll'), $this->outsider] as $user) {
            $this->actingAs($user)->get(route('recruitment.interviews.mine'))
                ->assertInertia(fn (AssertableInertia $p) => $p->where('recruitmentAccess.offers', false));
        }
    }

    #[Test]
    public function an_accepted_offer_stops_before_hiring(): void
    {
        $manager = $this->hrApprovalChain();
        $app = $this->evaluated($this->openVacancy());
        $this->select($app);
        $before = [Employee::count(), User::count(), EmployeeMovement::count(), NumberSequence::where('key', 'employee_number')->value('next_number')];

        $offer = $this->approvedOffer($app, $manager);
        app(OfferService::class)->issue($offer, null, $this->hrStaff);
        app(OfferService::class)->respond($offer, 'accepted', null, $this->hrStaff);

        $this->assertSame($before, [Employee::count(), User::count(), EmployeeMovement::count(), NumberSequence::where('key', 'employee_number')->value('next_number')]);
        $this->assertSame(['shortlisted', 'accepted'], [$app->fresh()->status, $offer->fresh()->status]);
    }
}
