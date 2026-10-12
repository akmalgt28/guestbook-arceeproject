<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\GuestbookEntry;
use App\Models\Invitation;
use App\Models\Recipient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GuestbookSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_guestbook_submission_is_persisted(): void
    {
        [, , $token] = $this->createInvitation();

        Livewire::test('guestbook.form', ['token' => $token])
            ->set('name', 'Guest <script>alert(1)</script>')
            ->set('message', 'Ucapan aman')
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('guestbook_entries', [
            'name' => 'Guest <script>alert(1)</script>',
            'message' => 'Ucapan aman',
            'status' => GuestbookEntry::STATUS_VISIBLE,
        ]);
    }

    public function test_guestbook_honeypot_rejects_bot_submission(): void
    {
        [, , $token] = $this->createInvitation();

        Livewire::test('guestbook.form', ['token' => $token])
            ->set('name', 'Bot')
            ->set('message', 'Spam')
            ->set('website', 'https://example.com')
            ->call('submit')
            ->assertHasErrors(['website']);

        $this->assertDatabaseCount('guestbook_entries', 0);
    }

    private function createInvitation(): array
    {
        $event = Event::query()->create([
            'name' => 'Guestbook Event',
            'type' => 'corporate',
            'event_date' => '2026-10-20',
            'status' => Event::STATUS_PUBLISHED,
        ]);
        $recipient = Recipient::query()->create([
            'event_id' => $event->id,
            'type' => Recipient::TYPE_INDIVIDUAL,
            'name' => 'Guest',
            'invited_guest_count' => 1,
        ]);
        ['token' => $token] = Invitation::createWithToken([
            'event_id' => $event->id,
            'recipient_id' => $recipient->id,
            'status' => Invitation::STATUS_ACTIVE,
        ]);

        return [$event, $recipient, $token];
    }
}
