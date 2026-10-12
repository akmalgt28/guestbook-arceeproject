<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_admin_sees_assigned_events_only(): void
    {
        $admin = User::factory()->create();
        $creator = User::factory()->create();
        $role = Role::create(['name' => 'Event Admin', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::create(['name' => 'events.view', 'guard_name' => 'web']));
        $admin->assignRole($role);

        $assignedEvent = Event::query()->create([
            'created_by' => $creator->id,
            'name' => 'Assigned Event',
            'type' => 'corporate',
            'event_date' => '2026-10-20',
            'status' => Event::STATUS_DRAFT,
        ]);
        $unassignedEvent = Event::query()->create([
            'created_by' => $creator->id,
            'name' => 'Unassigned Event',
            'type' => 'corporate',
            'event_date' => '2026-10-21',
            'status' => Event::STATUS_DRAFT,
        ]);
        $assignedEvent->admins()->attach($admin, ['assignment' => 'event_admin']);

        $response = $this->actingAs($admin)->get('/admin/events');

        $response->assertOk();
        $response->assertSee('Assigned Event');
        $response->assertDontSee('Unassigned Event');
    }
}
