<?php

namespace Tests\Feature\EmployeeMovement;

use App\Models\Employee;
use App\Models\EmployeeMovement;
use App\Models\EmploymentStatus;
use App\Models\User;
use App\Services\EmployeeMovementService;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Spatie\Permission\Models\Role;

class EmployeeMovementTest extends EmployeeMovementTestCase
{
    // ---------------------------------------------------------------
    // Authorization
    // ---------------------------------------------------------------

    #[Test]
    public function hr_director_can_view_movements(): void
    {
        $director = $this->userWithRole('hr_director');
        $movement = $this->makeEffectiveMovement();

        $this->actingAs($director)->get(route('people.employee-movements.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('app/People/EmployeeMovements/Index', false)
                ->where('movements.total', 1));
        $this->actingAs($director)->get(route('people.employee-movements.show', $movement))->assertOk();
        $this->actingAs($director)->get(route('people.employee-movements.create'))->assertOk();
    }

    #[Test]
    #[DataProvider('hrRoles')]
    public function hr_roles_can_create_movements(string $role): void
    {
        $employee = $this->staffMember();

        $this->record($this->userWithRole($role), $this->payload($employee, 'promotion', ['job_title_id' => $this->senior->id]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame($this->senior->id, $employee->fresh()->job_title_id);
    }

    public static function hrRoles(): array
    {
        return ['hr_director' => ['hr_director'], 'hr_manager' => ['hr_manager'], 'hr_staff' => ['hr_staff']];
    }

    #[Test]
    public function employees_and_supervisors_cannot_manage_movements(): void
    {
        $employee = $this->staffMember();
        $movement = $this->makeEffectiveMovement();

        foreach (['employee', 'supervisor', 'payroll'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->get(route('people.employee-movements.index'))->assertForbidden();
            $this->actingAs($user)->get(route('people.employee-movements.create'))->assertForbidden();
            $this->actingAs($user)->get(route('people.employee-movements.export'))->assertForbidden();
            $this->actingAs($user)->get(route('people.employee-movements.show', $movement))->assertForbidden();
            $this->record($user, $this->payload($employee, 'promotion', ['job_title_id' => $this->senior->id]))->assertForbidden();
            $this->actingAs($user)->put(route('people.employee-movements.update', $movement), ['reason' => 'x'])->assertForbidden();
            $this->actingAs($user)->post(route('people.employee-movements.cancel', $movement), ['cancellation_reason' => 'x'])->assertForbidden();
            $this->actingAs($user)->get(route('people.employee-movement-types.index'))->assertForbidden();
        }

        $this->assertSame($this->junior->id, $employee->fresh()->job_title_id);
    }

    #[Test]
    public function supervisor_can_manage_movements_only_when_explicitly_granted(): void
    {
        $supervisor = $this->userWithRole('supervisor');
        $supervisor->givePermissionTo(['employee_movement.view', 'employee_movement.create']);
        $employee = $this->staffMember();

        $this->actingAs($supervisor)->get(route('people.employee-movements.index'))->assertOk();
        $this->record($supervisor, $this->payload($employee, 'transfer', ['department_id' => $this->hr->id]))->assertSessionHasNoErrors();
        $this->assertSame($this->hr->id, $employee->fresh()->department_id);
    }

    #[Test]
    public function record_level_policy_limits_own_view_and_blocks_self_movements(): void
    {
        $employeeUser = $this->userWithRole('employee');
        $mine = $this->makeEffectiveMovement($this->employeeOf($employeeUser));
        $theirs = $this->makeEffectiveMovement();

        // view_own: own effective movement yes (without HR's internal notes), someone else's no
        $mine->update(['remarks' => 'Internal HR note']);
        $this->actingAs($employeeUser)->get(route('people.employee-movements.show', $mine))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->missing('movement.remarks')
                ->missing('movement.created_by.username')
                ->where('can.cancel', false));
        $this->actingAs($this->userWithRole('hr_staff'))->get(route('people.employee-movements.show', $mine))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('movement.remarks', 'Internal HR note'));
        $this->actingAs($employeeUser)->get(route('people.employee-movements.show', $theirs))->assertForbidden();

        // HR can't record or reverse their own employment changes
        $hrUser = $this->userWithRole('hr_manager');
        $hrEmployee = $this->employeeOf($hrUser);
        $this->record($hrUser, $this->payload($hrEmployee, 'promotion', ['job_title_id' => $this->senior->id]))->assertForbidden();

        $ownMovement = $this->makeEffectiveMovement($hrEmployee);
        $this->actingAs($hrUser)->post(route('people.employee-movements.cancel', $ownMovement), ['cancellation_reason' => 'x'])->assertForbidden();
    }

    // ---------------------------------------------------------------
    // Creation and current state
    // ---------------------------------------------------------------

    #[Test]
    public function promotion_creates_a_movement_and_updates_the_employee(): void
    {
        $hr = $this->userWithRole('hr_manager');
        $employee = $this->staffMember();

        $this->record($hr, $this->payload($employee, 'promotion', ['job_title_id' => $this->senior->id]))->assertSessionHasNoErrors();

        $movement = EmployeeMovement::sole();
        $this->assertSame(EmployeeMovement::STATUS_EFFECTIVE, $movement->status);
        $this->assertSame('promotion', $movement->type->code);
        $this->assertSame($this->junior->id, (int) $movement->from_job_title_id);
        $this->assertSame($this->senior->id, (int) $movement->to_job_title_id);
        $this->assertSame('Junior Developer', $movement->snapshot['from']['job_title']);
        $this->assertSame('Senior Developer', $movement->snapshot['to']['job_title']);
        $this->assertSame($hr->id, $movement->created_by);
        $this->assertSame($this->senior->id, $employee->fresh()->job_title_id);
        // unchanged fields are carried over as-is
        $this->assertSame($this->it->id, (int) $movement->to_department_id);
    }

    #[Test]
    public function transfer_creates_a_movement_and_updates_the_department(): void
    {
        $employee = $this->staffMember();

        $this->record($this->userWithRole('hr_staff'), $this->payload($employee, 'transfer', ['department_id' => $this->hr->id]))->assertSessionHasNoErrors();

        $movement = EmployeeMovement::sole();
        $this->assertSame('Information Technology', $movement->snapshot['from']['department']);
        $this->assertSame('Human Resources', $movement->snapshot['to']['department']);
        $this->assertSame($this->hr->id, $employee->fresh()->department_id);
    }

    #[Test]
    public function employment_status_change_updates_the_status(): void
    {
        $probationary = EmploymentStatus::create(['name' => 'Probationary']);
        $employee = $this->staffMember(['employment_status_id' => $probationary->id]);

        $this->record($this->userWithRole('hr_staff'), $this->payload($employee, 'employment_status_change', ['employment_status_id' => $this->regular->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame($this->regular->id, $employee->fresh()->employment_status_id);
        $this->assertSame('active', $employee->fresh()->status);
    }

    #[Test]
    #[DataProvider('separations')]
    public function separations_end_employment_with_the_configured_status(string $code): void
    {
        $employee = $this->staffMember();

        $this->record($this->userWithRole('hr_director'), $this->payload($employee, $code, ['employment_status_id' => $this->resigned->id], ['reason' => ucfirst($code)]))
            ->assertSessionHasNoErrors();

        $employee->refresh();
        $movement = EmployeeMovement::sole();
        $this->assertSame('terminated', $employee->status, 'record lifecycle status');
        $this->assertSame($this->resigned->id, $employee->employment_status_id, 'configured employment status');
        $this->assertSame('active', $movement->from_status);
        $this->assertSame('terminated', $movement->to_status);
        $this->assertSame($code, $movement->type->code);
        $this->assertSame(ucfirst($code), $movement->reason);
    }

    public static function separations(): array
    {
        return ['resignation' => ['resignation'], 'retirement' => ['retirement'], 'termination' => ['termination']];
    }

    #[Test]
    public function history_preserves_every_previous_state(): void
    {
        $hr = $this->userWithRole('hr_director');
        $employee = $this->staffMember();

        $this->record($hr, $this->payload($employee, 'hiring', [], ['effective_date' => '2025-01-01']))->assertSessionHasNoErrors();
        $this->record($hr, $this->payload($employee, 'promotion', ['job_title_id' => $this->senior->id], ['effective_date' => '2026-06-01']))->assertSessionHasNoErrors();
        $this->record($hr, $this->payload($employee, 'transfer', ['department_id' => $this->hr->id], ['effective_date' => '2026-07-01']))->assertSessionHasNoErrors();

        // Renaming configuration later doesn't rewrite history.
        $this->junior->update(['job_title' => 'Associate Developer']);

        $history = EmployeeMovement::where('employee_id', $employee->id)->orderBy('effective_date')->get();
        $this->assertSame(['hiring', 'promotion', 'transfer'], $history->map(fn ($m) => $m->type->code)->all());
        $this->assertSame('Junior Developer', $history[0]->snapshot['to']['job_title']);
        $this->assertSame(['Junior Developer', 'Senior Developer'], [$history[1]->snapshot['from']['job_title'], $history[1]->snapshot['to']['job_title']]);
        $this->assertSame(['Information Technology', 'Human Resources'], [$history[2]->snapshot['from']['department'], $history[2]->snapshot['to']['department']]);
        $this->assertSame('Senior Developer', $history[2]->snapshot['to']['job_title']);
    }

    // ---------------------------------------------------------------
    // Effective dates
    // ---------------------------------------------------------------

    #[Test]
    public function future_dated_movement_waits_for_its_effective_date(): void
    {
        $employee = $this->staffMember();
        $this->travelTo('2026-09-25 10:00:00');

        $this->record($this->userWithRole('hr_manager'), $this->payload($employee, 'transfer', ['department_id' => $this->hr->id], ['effective_date' => '2026-10-01']))
            ->assertSessionHasNoErrors();

        $movement = EmployeeMovement::sole();
        $this->assertSame(EmployeeMovement::STATUS_SCHEDULED, $movement->status);
        $this->assertSame('2026-10-01', $movement->effective_date->toDateString());
        $this->assertSame('2026-09-25', $movement->created_at->toDateString(), 'transaction date is kept separately');
        $this->assertSame($this->it->id, $employee->fresh()->department_id, 'not applied early');
        $this->assertSame(0, app(EmployeeMovementService::class)->applyDue());

        $this->travelTo('2026-10-01 00:05:00');
        $this->artisan('employee-movements:apply-due')->expectsOutputToContain('Applied 1')->assertSuccessful();

        $movement->refresh();
        $this->assertSame(EmployeeMovement::STATUS_EFFECTIVE, $movement->status);
        $this->assertSame($this->hr->id, $employee->fresh()->department_id);
        $this->assertSame('Information Technology', $movement->snapshot['from']['department']);
        $this->assertSame(0, app(EmployeeMovementService::class)->applyDue(), 'safe to re-run');
    }

    #[Test]
    public function due_scheduled_movements_apply_before_a_new_one_is_recorded(): void
    {
        $hr = $this->userWithRole('hr_director');
        $employee = $this->staffMember();

        $this->travelTo('2026-09-25');
        $this->record($hr, $this->payload($employee, 'transfer', ['department_id' => $this->hr->id], ['effective_date' => '2026-10-01']));

        // The daily command didn't run; recording the next movement applies the due one first.
        $this->travelTo('2026-10-05');
        $this->record($hr, $this->payload($employee, 'promotion', ['job_title_id' => $this->senior->id], ['effective_date' => '2026-10-05']))
            ->assertSessionHasNoErrors();

        $promotion = EmployeeMovement::latest('id')->first();
        $this->assertSame('Human Resources', $promotion->snapshot['from']['department']);
        $this->assertSame(2, EmployeeMovement::where('status', EmployeeMovement::STATUS_EFFECTIVE)->count());
    }

    #[Test]
    public function movements_must_be_recorded_in_date_order(): void
    {
        $hr = $this->userWithRole('hr_director');
        $employee = $this->staffMember();

        $this->record($hr, $this->payload($employee, 'promotion', ['job_title_id' => $this->senior->id], ['effective_date' => '2026-06-01']));

        $this->record($hr, $this->payload($employee, 'transfer', ['department_id' => $this->hr->id], ['effective_date' => '2026-05-01']))
            ->assertSessionHasErrors('effective_date');

        $this->assertSame($this->it->id, $employee->fresh()->department_id);
        $this->assertSame(1, EmployeeMovement::count());
    }

    // ---------------------------------------------------------------
    // Transaction safety and concurrency
    // ---------------------------------------------------------------

    #[Test]
    public function invalid_movement_changes_nothing(): void
    {
        $hr = $this->userWithRole('hr_director');
        $employee = $this->staffMember();

        // inactive type
        $this->type('promotion')->update(['is_active' => false]);
        $this->record($hr, $this->payload($employee, 'promotion', ['job_title_id' => $this->senior->id]))->assertSessionHasErrors('movement_type_id');

        // nothing to change
        $this->record($hr, $this->payload($employee, 'transfer'))->assertSessionHasErrors('changed_fields');

        // own supervisor
        $this->record($hr, $this->payload($employee, 'transfer', ['supervisor_id' => $employee->id]))->assertSessionHasErrors('to_supervisor_id');

        $this->assertSame(0, EmployeeMovement::count());
        $this->assertSame($this->junior->id, $employee->fresh()->job_title_id);
    }

    #[Test]
    public function movement_creation_is_atomic(): void
    {
        $employee = $this->staffMember();

        // Fail after the movement row exists, while the employee is being updated.
        Employee::updating(function () {
            throw new RuntimeException('simulated failure');
        });

        try {
            app(EmployeeMovementService::class)->createMovement(
                $this->payload($employee, 'promotion', ['job_title_id' => $this->senior->id]),
                $this->userWithRole('hr_director')
            );
            $this->fail('Expected the simulated failure.');
        } catch (RuntimeException $e) {
            $this->assertSame('simulated failure', $e->getMessage());
        }

        $this->assertSame(0, EmployeeMovement::count(), 'no movement');
        $this->assertSame($this->junior->id, $employee->fresh()->job_title_id, 'no employee change');
    }

    #[Test]
    public function the_before_state_comes_from_the_committed_employee_row(): void
    {
        $hrA = $this->userWithRole('hr_director');
        $hrB = $this->userWithRole('hr_manager');
        $employee = $this->staffMember();

        // HR A opened the form while the employee was in IT...
        $this->actingAs($hrA)->get(route('people.employee-movements.create', ['employee_id' => $employee->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('currentState.labels.department', 'Information Technology'));

        // ...HR B's transfer commits first...
        $this->record($hrB, $this->payload($employee, 'transfer', ['department_id' => $this->hr->id]))->assertSessionHasNoErrors();

        // ...then A saves a promotion, even sending the stale "from" values.
        $this->record($hrA, $this->payload($employee, 'promotion', ['job_title_id' => $this->senior->id], [
            'from_department_id' => $this->it->id,
            'from_job_title_id' => $this->junior->id,
        ]))->assertSessionHasNoErrors();

        $promotion = EmployeeMovement::latest('id')->first();
        $this->assertSame($this->hr->id, (int) $promotion->from_department_id, 'sees the committed transfer');
        $this->assertSame($this->hr->id, (int) $promotion->to_department_id, 'does not undo the transfer');
        $employee->refresh();
        $this->assertSame([$this->hr->id, $this->senior->id], [$employee->department_id, $employee->job_title_id]);
    }

    #[Test]
    public function the_employee_row_is_read_inside_the_transaction(): void
    {
        $employee = $this->staffMember();
        $levels = [];

        DB::listen(function ($query) use (&$levels) {
            if (str_contains($query->sql, 'from "employees"') && str_contains($query->sql, 'limit 1')) {
                $levels[] = DB::transactionLevel();
            }
        });

        $baseline = DB::transactionLevel(); // RefreshDatabase wraps each test in one
        app(EmployeeMovementService::class)->createMovement(
            $this->payload($employee, 'promotion', ['job_title_id' => $this->senior->id]),
            $this->userWithRole('hr_director')
        );

        $this->assertNotEmpty($levels);
        $this->assertGreaterThan($baseline, $levels[0], 'employee is loaded (and locked on MySQL) inside the movement transaction');
    }

    // ---------------------------------------------------------------
    // Security
    // ---------------------------------------------------------------

    #[Test]
    public function client_supplied_previous_state_is_ignored(): void
    {
        $employee = $this->staffMember();

        $this->record($this->userWithRole('hr_director'), $this->payload($employee, 'promotion', ['job_title_id' => $this->senior->id], [
            'from_job_title_id' => $this->senior->id,
            'from_department_id' => $this->hr->id,
            'from_status' => 'terminated',
            'snapshot' => ['from' => ['job_title' => 'Forged']],
            'status' => EmployeeMovement::STATUS_CANCELLED,
            'created_by' => 999,
        ]))->assertSessionHasNoErrors();

        $movement = EmployeeMovement::sole();
        $this->assertSame($this->junior->id, (int) $movement->from_job_title_id);
        $this->assertSame($this->it->id, (int) $movement->from_department_id);
        $this->assertSame('active', $movement->from_status);
        $this->assertSame('Junior Developer', $movement->snapshot['from']['job_title']);
        $this->assertSame(EmployeeMovement::STATUS_EFFECTIVE, $movement->status);
        $this->assertNotSame(999, $movement->created_by);
    }

    #[Test]
    public function employee_id_manipulation_cannot_target_an_unauthorized_employee(): void
    {
        $hrUser = $this->userWithRole('hr_staff');
        $own = $this->employeeOf($hrUser);

        // Pointing employee_id at their own record is refused by the policy.
        $this->record($hrUser, $this->payload($own, 'promotion', ['job_title_id' => $this->senior->id]))->assertForbidden();

        // A non-existent employee is a validation error, not a movement.
        $payload = $this->payload($this->staffMember(), 'promotion', ['job_title_id' => $this->senior->id]);
        $payload['employee_id'] = 999999;
        $this->record($hrUser, $payload)->assertSessionHasErrors('employee_id');

        $this->assertSame(0, EmployeeMovement::count());
    }

    #[Test]
    public function unauthorized_users_cannot_update_or_cancel(): void
    {
        $movement = $this->makeEffectiveMovement();

        // hr_staff has update but not cancel
        $staff = $this->userWithRole('hr_staff');
        $this->actingAs($staff)->put(route('people.employee-movements.update', $movement), ['reason' => 'Clarified'])->assertSessionHasNoErrors();
        $this->actingAs($staff)->post(route('people.employee-movements.cancel', $movement), ['cancellation_reason' => 'x'])->assertForbidden();
        $this->assertSame('Clarified', $movement->fresh()->reason);

        // update can't touch employment data
        $this->actingAs($staff)->put(route('people.employee-movements.update', $movement), [
            'reason' => 'Clarified',
            'to_job_title_id' => $this->junior->id,
            'status' => EmployeeMovement::STATUS_CANCELLED,
        ]);
        $this->assertSame($this->senior->id, (int) $movement->fresh()->to_job_title_id);
        $this->assertSame(EmployeeMovement::STATUS_EFFECTIVE, $movement->fresh()->status);
    }

    // ---------------------------------------------------------------
    // Reversal
    // ---------------------------------------------------------------

    #[Test]
    public function cancelling_the_latest_effective_movement_restores_the_previous_state(): void
    {
        $director = $this->userWithRole('hr_director');
        $employee = $this->staffMember();
        $this->record($director, $this->payload($employee, 'resignation', ['employment_status_id' => $this->resigned->id]));
        $movement = EmployeeMovement::sole();

        $this->actingAs($director)->post(route('people.employee-movements.cancel', $movement), ['cancellation_reason' => 'Recorded by mistake'])
            ->assertSessionHasNoErrors();

        $employee->refresh();
        $movement->refresh();
        $this->assertSame('active', $employee->status);
        $this->assertSame($this->regular->id, $employee->employment_status_id);
        $this->assertSame(EmployeeMovement::STATUS_CANCELLED, $movement->status);
        $this->assertSame('Recorded by mistake', $movement->cancellation_reason);
        $this->assertSame($director->id, $movement->cancelled_by);
        $this->assertSame(1, EmployeeMovement::count(), 'kept for audit, never deleted');
    }

    #[Test]
    public function reversal_refuses_to_overwrite_later_changes(): void
    {
        $director = $this->userWithRole('hr_director');
        $employee = $this->staffMember();

        $this->record($director, $this->payload($employee, 'promotion', ['job_title_id' => $this->senior->id], ['effective_date' => '2026-06-01']));
        $promotion = EmployeeMovement::sole();
        $this->record($director, $this->payload($employee, 'transfer', ['department_id' => $this->hr->id], ['effective_date' => '2026-07-01']));

        $this->actingAs($director)->post(route('people.employee-movements.cancel', $promotion), ['cancellation_reason' => 'x'])
            ->assertSessionHasErrors('movement');

        // an out-of-band edit also blocks reversal
        $transfer = EmployeeMovement::latest('id')->first();
        $employee->refresh()->update(['department_id' => $this->it->id]);
        $this->actingAs($director)->post(route('people.employee-movements.cancel', $transfer), ['cancellation_reason' => 'x'])
            ->assertSessionHasErrors('movement');

        $this->assertSame(0, EmployeeMovement::where('status', EmployeeMovement::STATUS_CANCELLED)->count());
        $this->assertSame($this->senior->id, $employee->fresh()->job_title_id);
    }

    #[Test]
    public function cancelling_a_scheduled_movement_changes_nothing_on_the_employee(): void
    {
        $director = $this->userWithRole('hr_director');
        $employee = $this->staffMember();
        $this->record($director, $this->payload($employee, 'transfer', ['department_id' => $this->hr->id], ['effective_date' => now()->addMonth()->toDateString()]));
        $movement = EmployeeMovement::sole();

        $this->actingAs($director)->post(route('people.employee-movements.cancel', $movement), ['cancellation_reason' => 'Plans changed'])
            ->assertSessionHasNoErrors();

        $this->assertSame(EmployeeMovement::STATUS_CANCELLED, $movement->fresh()->status);
        $this->assertSame($this->it->id, $employee->fresh()->department_id);
        $this->travel(2)->months();
        $this->assertSame(0, app(EmployeeMovementService::class)->applyDue(), 'a cancelled movement never applies');
    }

    // ---------------------------------------------------------------
    // Filters, export and self-service
    // ---------------------------------------------------------------

    #[Test]
    public function list_filters_and_export_use_the_same_query(): void
    {
        $director = $this->userWithRole('hr_director');
        $a = $this->staffMember();
        $b = $this->staffMember();
        $this->record($director, $this->payload($a, 'promotion', ['job_title_id' => $this->senior->id]));
        $this->record($director, $this->payload($b, 'transfer', ['department_id' => $this->hr->id]));

        $filters = ['movement_type_id' => $this->type('transfer')->id];

        $this->actingAs($director)->get(route('people.employee-movements.index', $filters))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('movements.total', 1)
                ->where('movements.data.0.employee_id', $b->id));

        $query = app(EmployeeMovementService::class)->query($filters);
        $this->assertSame([$b->id], $query->pluck('employee_id')->all());

        $this->actingAs($director)->get(route('people.employee-movements.export', $filters))
            ->assertOk()
            ->assertDownload();
    }

    #[Test]
    public function employee_sees_only_own_effective_history(): void
    {
        $user = $this->userWithRole('employee');
        $mine = $this->employeeOf($user);
        $director = $this->userWithRole('hr_director');

        $this->record($director, $this->payload($mine, 'promotion', ['job_title_id' => $this->senior->id]));
        $this->record($director, $this->payload($mine, 'transfer', ['department_id' => $this->hr->id], ['effective_date' => now()->addMonth()->toDateString()]));
        $this->record($director, $this->payload($this->staffMember(), 'transfer', ['department_id' => $this->hr->id]));

        $this->actingAs($user)->get(route('people.my-employment-history.index', ['employee_id' => 1]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('app/People/MyEmploymentHistory/Index', false)
                ->has('movements', 1)
                ->where('movements.0.type.code', 'promotion'));

        Role::findByName('employee')->revokePermissionTo('employee_movement.view_own');
        $this->actingAs($user->fresh())->get(route('people.my-employment-history.index'))->assertForbidden();
    }

    private function makeEffectiveMovement(?Employee $employee = null): EmployeeMovement
    {
        $employee ??= $this->staffMember();

        return app(EmployeeMovementService::class)->createMovement(
            $this->payload($employee, 'promotion', ['job_title_id' => $this->senior->id]),
            User::factory()->create()->assignRole('hr_director')
        );
    }
}
