<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\FamilyMember;
use App\Models\Invitation;
use App\Models\Recipient;
use App\Models\Rsvp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_invitation_token_shows_public_invitation(): void
    {
        [, , $token] = $this->createInvitation();

        $response = $this->get('/invite/'.$token);

        $response->assertOk();
    }

    public function test_invalid_invitation_token_is_not_accepted(): void
    {
        $this->createInvitation();

        $response = $this->get('/invite/'.str_repeat('a', 64));

        $response->assertNotFound();
    }

    public function test_rsvp_persists_attendance_and_selected_family_members(): void
    {
        [, $recipient, $token] = $this->createInvitation(2);
        $familyMember = FamilyMember::query()->create([
            'recipient_id' => $recipient->id,
            'name' => 'Family Guest',
            'relationship' => 'Sibling',
            'is_expected' => true,
        ]);

        $response = $this->post('/invite/'.$token.'/rsvp', [
            'name' => 'Updated Guest',
            'phone' => '08123456789',
            'email' => 'guest@example.com',
            'status' => Rsvp::STATUS_ATTENDING,
            'attending_count' => 2,
            'family_member_ids' => [$familyMember->id],
            'notes' => 'Datang bersama keluarga.',
        ]);

        $response->assertRedirectToRoute('invitations.rsvp.form', $token);
        $this->assertDatabaseHas('rsvps', [
            'invitation_id' => $recipient->invitations()->first()->id,
            'status' => Rsvp::STATUS_ATTENDING,
            'attending_count' => 2,
        ]);
        $this->assertDatabaseHas('rsvp_attendees', [
            'family_member_id' => $familyMember->id,
            'name' => 'Family Guest',
        ]);
        $this->assertDatabaseHas('recipients', [
            'id' => $recipient->id,
            'name' => 'Updated Guest',
            'email' => 'guest@example.com',
        ]);
    }

    public function test_rsvp_rejects_attendance_above_invitation_limit(): void
    {
        [, , $token] = $this->createInvitation(1);

        $response = $this->from('/invite/'.$token.'/rsvp')->post('/invite/'.$token.'/rsvp', [
            'name' => 'Guest',
            'status' => Rsvp::STATUS_ATTENDING,
            'attending_count' => 2,
        ]);

        $response->assertSessionHasErrors(['attending_count']);
        $this->assertDatabaseCount('rsvps', 0);
    }

    private function createInvitation(int $invitedGuestCount = 1): array
    {
        $event = Event::query()->create([
            'name' => 'Test Event',
            'type' => 'corporate',
            'event_date' => '2026-10-20',
            'status' => Event::STATUS_PUBLISHED,
        ]);
        $recipient = Recipient::query()->create([
            'event_id' => $event->id,
            'type' => Recipient::TYPE_INDIVIDUAL,
            'name' => 'Guest',
            'invited_guest_count' => $invitedGuestCount,
        ]);
        ['invitation' => $invitation, 'token' => $token] = Invitation::createWithToken([
            'event_id' => $event->id,
            'recipient_id' => $recipient->id,
            'status' => Invitation::STATUS_ACTIVE,
        ]);

        return [$event, $recipient, $token, $invitation];
    }
}
