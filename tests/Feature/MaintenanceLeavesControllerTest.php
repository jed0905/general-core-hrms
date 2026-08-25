<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;
use App\Models\User;
use App\Models\Leave;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\WithFaker;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MaintenanceLeavesControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithoutMiddleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SuperAdminSeeder::class);

        $user = User::where('username', 'superadmin')->first();
        $this->actingAs($user);
    }

    /** @test */
    public function it_displays_the_leaves_index_page()
    {
        Leave::factory()->count(3)->create();

        $response = $this->get(route('maintenance.leaves.index'));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) =>
            $page->component('app/Maintenance/Leaves/Index')
                ->has('leaves', 3)
        );
    }

    /** @test */
    public function it_displays_the_create_leave_page()
    {
        $response = $this->get(route('maintenance.leaves.create'));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) =>
            $page->component('app/Maintenance/Leaves/Create')
        );
    }

    /** @test */
    public function it_can_store_a_leave_record()
    {
        $this->assertAuthenticated();
        $this->assertEquals('superadmin', auth()->user()->username);
        $data = [
            'name' => 'Vacation Leave',
            'shortcut' => 'VL',
        ];

        $response = $this->post(route('maintenance.leaves.store'), $data);

        $response->assertRedirect(route('maintenance.leaves.create'));
        $this->assertDatabaseHas('leaves', $data);
    }

    /** @test */
    public function it_displays_the_edit_leave_page()
    {
        $leave = Leave::factory()->create();

        $response = $this->get(route('maintenance.leaves.edit', $leave->id));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) =>
            $page->component('app/Maintenance/Leaves/Edit')
                ->where('leave.id', $leave->id)
        );
    }

    /** @test */
    public function it_can_update_a_leave_record()
    {
        $leave = Leave::create([
            'name' => 'Old Leave',
            'shortcut' => 'OL',
        ]);

        $data = [
            'name' => 'Updated Leave',
            'shortcut' => 'Updated Description',
        ];

        $response = $this->put(route('maintenance.leaves.update', $leave->id), $data);

        $response->assertRedirect(route('maintenance.leaves.index'));
        $this->assertDatabaseHas('leaves', array_merge(['id' => $leave->id], $data));
    }

    /** @test */
    public function it_can_delete_a_leave_record()
    {
        $leave = Leave::factory()->create();

        $response = $this->delete(route('maintenance.leaves.destroy', $leave->id));

        $response->assertRedirect(route('maintenance.leave.index'));
        $this->assertDatabaseMissing('leaves', ['id' => $leave->id]);
    }
}
