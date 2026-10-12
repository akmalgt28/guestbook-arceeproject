<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Invitation;
use App\Models\QrCode;
use App\Models\Recipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CheckinSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_scanning_invalid_qr_token_returns_unprocessable_entity(): void
    {
        [$user, $event] = $this->createScannerContext();

        $response = $this->actingAs($user)->postJson('/checkin/scan', [
            'event_id' => $event->id,
            'qr_token' => str_repeat('a', 64),
        ]);

        $response->assertStatus(422)->assertJson(['message' => 'QR tidak valid, sudah dinonaktifkan, atau bukan untuk event ini.']);
    }

    public function test_manual_search_finds_invitation_by_recipient_name(): void
    {
        [$user, $event] = $this->createScannerContext();

        $this->actingAs($user);

        Livewire::test('admin.checkins')
            ->set('eventFilter', (string) $event->id)
            ->set('search', 'Scanner Guest')
            ->assertSee('Scanner Guest');
    }

    public function test_confirming_same_qr_twice_rejects_second_checkin(): void
    {
        [$user, $event, $qrCode] = $this->createScannerContext();

        $payload = [
            'event_id' => $event->id,
            'qr_code_id' => $qrCode->id,
            'actual_attendee_count' => 1,
        ];

        $firstResponse = $this->actingAs($user)->postJson('/checkin/confirm', $payload);
        $secondResponse = $this->actingAs($user)->postJson('/checkin/confirm', $payload);

        $firstResponse->assertCreated();
        $secondResponse->assertStatus(422)->assertJson(['message' => 'Tamu sudah melakukan check-in sebelumnya.']);
        $this->assertDatabaseCount('checkins', 1);
    }

    private function createScannerContext(): array
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'QR Scanner', 'guard_name' => 'web']);
        $role->givePermissionTo([
            Permission::create(['name' => 'checkins.create', 'guard_name' => 'web']),
            Permission::create(['name' => 'checkins.view', 'guard_name' => 'web']),
        ]);
        $user->assignRole($role);

        $event = Event::query()->create([
            'name' => 'Scanner Event',
            'type' => 'corporate',
            'event_date' => '2026-10-20',
            'status' => Event::STATUS_PUBLISHED,
        ]);
        $event->admins()->attach($user, ['assignment' => 'qr_scanner']);
        $recipient = Recipient::query()->create([
            'event_id' => $event->id,
            'type' => Recipient::TYPE_INDIVIDUAL,
            'name' => 'Scanner Guest',
            'invited_guest_count' => 1,
        ]);
        [$invitation] = [Invitation::createWithToken([
            'event_id' => $event->id,
            'recipient_id' => $recipient->id,
            'status' => Invitation::STATUS_ACTIVE,
        ])['invitation']];
        $qrCode = QrCode::query()->create([
            'invitation_id' => $invitation->id,
            'token_hash' => QrCode::generateTokenHash(),
            'status' => QrCode::STATUS_ACTIVE,
            'generated_at' => now(),
        ]);

        return [$user, $event, $qrCode];
    }
}
