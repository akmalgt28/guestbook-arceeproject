<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitRsvpRequest;
use App\Models\Event;
use App\Models\Invitation;
use App\Models\InvitationTemplate;
use App\Models\QrCode;
use App\Models\Rsvp;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PublicInvitationController extends Controller
{
    public function preview(string $eventSlug): View
    {
        $event = Event::query()
            ->published()
            ->with('templates')
            ->get()
            ->first(fn (Event $event): bool => data_get($event->settings, 'invitation.custom_link') === $eventSlug || Str::slug($event->name) === $eventSlug);

        abort_if($event === null, 404);

        $template = $event->templates->first();
        $schema = InvitationTemplate::mergedSchema($template?->schema ?? [], $template?->pivot?->settings ?? []);

        return view('invitations.preview', [
            'event' => $event,
            'schema' => $schema,
        ]);
    }

    public function show(string $token): View
    {
        $invitation = $this->findActiveInvitation($token);

        $event = $invitation->event;
        $template = $event->templates->first();
        $schema = InvitationTemplate::mergedSchema($template?->schema ?? [], $template?->pivot?->settings ?? []);

        $mediaFiles = $event->mediaFiles->filter(fn ($mediaFile): bool => filled($mediaFile->url));
        $galleryMediaIds = array_values(array_filter(array_map('intval', data_get($schema, 'media.gallery', [])), fn (int $mediaId): bool => $mediaId > 0));
        if ($galleryMediaIds !== []) {
            $mediaFiles = $mediaFiles->whereIn('id', $galleryMediaIds)->sortBy(fn ($mediaFile): int => array_search($mediaFile->getKey(), $galleryMediaIds, true));
        }

        return view('invitations.show', [
            'invitation' => $invitation,
            'event' => $event,
            'recipient' => $invitation->recipient,
            'token' => $token,
            'qrCode' => $invitation->qrCodes->firstWhere('status', QrCode::STATUS_ACTIVE),
            'schema' => [
                'primary_color' => $this->safeColor($schema['theme']['primary_color'] ?? null, '#1F2937'),
                'secondary_color' => $this->safeColor($schema['theme']['secondary_color'] ?? null, '#F3F4F6'),
                'font_family' => $this->safeFont($schema['theme']['font_family'] ?? null),
                'heading_font_family' => $this->safeFont($schema['theme']['heading_font_family'] ?? null),
                'body_font_family' => $this->safeFont($schema['theme']['body_font_family'] ?? null),
                'text_color' => $this->safeColor($schema['theme']['text_color'] ?? null, '#1F2937'),
                'background_color' => $this->safeColor($schema['theme']['background_color'] ?? null, '#F3F4F6'),
                'decorations' => [
                    'enabled' => (bool) data_get($schema, 'decorations.enabled', true),
                    'primary' => $this->safeColor(data_get($schema, 'decorations.colors.primary'), '#0B4AA2'),
                    'blue' => $this->safeColor(data_get($schema, 'decorations.colors.blue'), '#168DE2'),
                    'light' => $this->safeColor(data_get($schema, 'decorations.colors.light'), '#8BC4F0'),
                ],                'heading_size' => $this->safeOption($schema['theme']['heading_size'] ?? null, ['sm', 'base', 'lg', 'xl'], 'lg'),
                'body_size' => $this->safeOption($schema['theme']['body_size'] ?? null, ['sm', 'base', 'lg'], 'base'),
                'radius' => $this->safeOption($schema['theme']['radius'] ?? null, ['none', 'md', 'xl', 'full'], 'xl'),
                'button_style' => $this->safeOption($schema['theme']['button_style'] ?? null, ['rounded', 'pill', 'square'], 'rounded'),
                'card_style' => $this->safeOption($schema['theme']['card_style'] ?? null, ['bordered', 'flat'], 'bordered'),
                'sections' => array_values(array_intersect(
                    $schema['section_keys'] ?? [],
                    ['hero', 'event_info', 'location', 'gallery', 'speakers', 'rsvp', 'qr', 'guestbook', 'contact'],
                )),
                'config' => $schema,
            ],
            'mediaFiles' => $event->mediaFiles->filter(fn ($mediaFile): bool => filled($mediaFile->url)),
            'countdownEnabled' => (bool) data_get($event->settings, 'countdown_enabled', true),
            'eventStartsAt' => $event->event_date?->setTimeFromTimeString(
                $event->start_time?->format('H:i:s') ?? '00:00:00',
            )?->toIso8601String(),
        ]);
    }

    public function rsvpForm(string $token): View
    {
        $invitation = $this->findActiveInvitation($token);

        $template = $invitation->event->templates->first();
        $schema = InvitationTemplate::mergedSchema($template?->schema ?? [], $template?->pivot?->settings ?? []);

        return view('invitations.rsvp', [
            'invitation' => $invitation,
            'config' => $schema,
            'event' => $invitation->event,
            'recipient' => $invitation->recipient,
            'token' => $token,
            'familyMembers' => $invitation->recipient->familyMembers()->where('is_expected', true)->get(),
            'rsvp' => $invitation->rsvp,
            'qrCode' => $invitation->qrCodes->firstWhere('status', QrCode::STATUS_ACTIVE),
        ]);
    }

    public function submitRsvp(SubmitRsvpRequest $request, string $token): RedirectResponse
    {
        $invitation = $this->findActiveInvitation($token);
        $data = $request->validated();
        $invitedGuestCount = $invitation->recipient->invited_guest_count;

        if ($data['status'] === Rsvp::STATUS_ATTENDING && $data['attending_count'] > $invitedGuestCount) {
            throw ValidationException::withMessages([
                'attending_count' => "Jumlah hadir maksimal {$invitedGuestCount} orang.",
            ]);
        }

        if ($data['status'] === Rsvp::STATUS_NOT_ATTENDING && $data['attending_count'] !== 0) {
            throw ValidationException::withMessages([
                'attending_count' => 'Jumlah hadir harus 0 jika tidak hadir.',
            ]);
        }

        $familyMemberIds = $invitation->recipient->familyMembers()
            ->whereIn('id', $data['family_member_ids'] ?? [])
            ->pluck('id');

        DB::transaction(function () use ($data, $familyMemberIds, $invitation): void {
            $invitation->recipient->update([
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
            ]);

            $rsvp = $invitation->rsvp()->updateOrCreate([], [
                'status' => $data['status'],
                'attending_count' => $data['status'] === Rsvp::STATUS_ATTENDING ? $data['attending_count'] : 0,
                'responded_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $rsvp->attendees()->delete();
            $rsvp->attendees()->create([
                'name' => $data['name'],
                'status' => $data['status'],
            ]);

            foreach ($familyMemberIds as $familyMemberId) {
                $familyMember = $invitation->recipient->familyMembers()->find($familyMemberId);
                $rsvp->attendees()->create([
                    'family_member_id' => $familyMember->id,
                    'name' => $familyMember->name,
                    'status' => $data['status'],
                ]);
            }

            if ($data['status'] === Rsvp::STATUS_ATTENDING) {
                $qrCode = $invitation->qrCodes()->where('status', QrCode::STATUS_ACTIVE)->first();

                if (! $qrCode) {
                    $invitation->qrCodes()->create([
                        'token_hash' => QrCode::generateTokenHash(),
                        'status' => QrCode::STATUS_ACTIVE,
                        'generated_at' => now(),
                    ]);
                }
            } else {
                $invitation->qrCodes()->where('status', QrCode::STATUS_ACTIVE)->update([
                    'status' => QrCode::STATUS_REVOKED,
                    'revoked_at' => now(),
                ]);
            }
        });

        $template = $invitation->event->templates->first();
        $schema = InvitationTemplate::mergedSchema($template?->schema ?? [], $template?->pivot?->settings ?? []);
        $successMessage = data_get($schema, 'rsvp.success_message', 'Terima kasih, konfirmasi kamu sudah tersimpan.');

        return to_route('invitations.rsvp.form', $token)
            ->with('rsvp_success', $successMessage);
    }

    private function findActiveInvitation(string $token): Invitation
    {
        return Invitation::query()
            ->with(['event.templates' => fn ($query) => $query->active(), 'event.mediaFiles', 'recipient.familyMembers', 'rsvp', 'qrCodes'])
            ->forPublicLink($token)
            ->where('status', Invitation::STATUS_ACTIVE)
            ->where(fn (Builder $query): Builder => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->whereHas('event', fn (Builder $query): Builder => $query->published())
            ->firstOrFail();
    }

    private function safeColor(mixed $value, string $fallback): string
    {
        return is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1
            ? $value
            : $fallback;
    }

    /** @param list<string> $allowed */
    private function safeOption(mixed $value, array $allowed, string $fallback): string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : $fallback;
    }

    private function safeFont(mixed $value): string
    {
        $allowedFonts = [
            'Inter',
            'Poppins',
            'Playfair Display',
            'Cormorant Garamond',
            'Merriweather',
        ];

        return is_string($value) && in_array($value, $allowedFonts, true)
            ? $value
            : 'Inter';
    }
}
