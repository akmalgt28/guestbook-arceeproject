<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Checkin;
use App\Models\Event;
use App\Models\Invitation;
use App\Models\QrCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckinController extends Controller
{
    public function scan(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('checkins.create'), 403);

        $validated = $request->validate([
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'qr_token' => ['required', 'string', 'max:2048'],
        ]);

        $event = $this->accessiblePublishedEvent((int) $validated['event_id']);
        $tokenHash = $this->extractTokenHash($validated['qr_token']);

        $qrCode = QrCode::query()
            ->where('token_hash', $tokenHash)
            ->where('status', QrCode::STATUS_ACTIVE)
            ->whereHas('invitation', function (Builder $query) use ($event): void {
                $query->where('event_id', $event->getKey())
                    ->where('status', Invitation::STATUS_ACTIVE)
                    ->where(function (Builder $query): void {
                        $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    });
            })
            ->with(['invitation.recipient', 'invitation.rsvp'])
            ->first();

        if (! $qrCode) {
            return response()->json(['message' => 'QR tidak valid, sudah dinonaktifkan, atau bukan untuk event ini.'], 422);
        }

        $existingCheckin = $qrCode->invitation->checkins()
            ->where('status', Checkin::STATUS_ACCEPTED)
            ->latest('checked_in_at')
            ->first();

        return response()->json([
            'qr_code_id' => $qrCode->getKey(),
            'already_checked_in' => $existingCheckin !== null,
            'checked_in_at' => $existingCheckin?->checked_in_at?->toIso8601String(),
            'recipient' => [
                'name' => $qrCode->invitation->recipient->name,
                'phone' => $qrCode->invitation->recipient->phone,
                'email' => $qrCode->invitation->recipient->email,
                'guest_count' => $qrCode->invitation->rsvp?->attending_count,
            ],
        ]);
    }

    public function confirm(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('checkins.create'), 403);

        $validated = $request->validate([
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'qr_code_id' => ['required', 'integer', 'exists:qr_codes,id'],
            'actual_attendee_count' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $event = $this->accessiblePublishedEvent((int) $validated['event_id']);

        $result = DB::transaction(function () use ($event, $validated, $request): array {
            $qrCode = QrCode::query()
                ->whereKey($validated['qr_code_id'])
                ->lockForUpdate()
                ->with(['invitation.recipient', 'invitation.rsvp'])
                ->first();

            if (! $qrCode || $qrCode->status !== QrCode::STATUS_ACTIVE || $qrCode->invitation->event_id !== $event->getKey()) {
                return ['error' => 'QR tidak valid untuk event ini.'];
            }

            $invitation = $qrCode->invitation;
            if ($invitation->status !== Invitation::STATUS_ACTIVE || ($invitation->expires_at && $invitation->expires_at->isPast())) {
                return ['error' => 'Invitation sudah tidak aktif atau kedaluwarsa.'];
            }

            if ($invitation->checkins()->where('status', Checkin::STATUS_ACCEPTED)->exists()) {
                return ['error' => 'Tamu sudah melakukan check-in sebelumnya.'];
            }

            $checkin = Checkin::query()->create([
                'event_id' => $event->getKey(),
                'invitation_id' => $invitation->getKey(),
                'qr_code_id' => $qrCode->getKey(),
                'recipient_id' => $invitation->recipient_id,
                'checked_in_by' => $request->user()->getKey(),
                'method' => 'qr',
                'status' => Checkin::STATUS_ACCEPTED,
                'actual_attendee_count' => $validated['actual_attendee_count'],
                'checked_in_at' => now(),
            ]);

            $qrCode->forceFill(['last_scanned_at' => $checkin->checked_in_at])->save();

            AuditLog::query()->create([
                'actor_id' => $request->user()->getKey(),
                'event_id' => $event->getKey(),
                'action' => 'checkin.created',
                'subject_type' => Checkin::class,
                'subject_id' => $checkin->getKey(),
                'new_values' => [
                    'method' => $checkin->method,
                    'actual_attendee_count' => $checkin->actual_attendee_count,
                    'checked_in_at' => $checkin->checked_in_at->toIso8601String(),
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return ['checkin' => $checkin];
        });

        if (isset($result['error'])) {
            return response()->json(['message' => $result['error']], 422);
        }

        return response()->json([
            'message' => 'Check-in berhasil.',
            'checked_in_at' => $result['checkin']->checked_in_at->toIso8601String(),
        ], 201);
    }

    private function accessiblePublishedEvent(int $eventId): Event
    {
        $user = request()->user();

        return Event::query()
            ->published()
            ->whereKey($eventId)
            ->when(
                ! $user->hasRole('Super Admin'),
                fn (Builder $query): Builder => $query->whereHas(
                    'admins',
                    fn (Builder $query): Builder => $query->whereKey($user->getKey()),
                ),
            )
            ->firstOrFail();
    }

    private function extractTokenHash(string $value): string
    {
        $path = parse_url($value, PHP_URL_PATH);
        $tokenHash = is_string($path) ? Str::afterLast(trim($path, '/'), '/') : $value;

        abort_unless((bool) preg_match('/^[a-f0-9]{64}$/', $tokenHash), 422, 'Format QR tidak valid.');

        return $tokenHash;
    }
}
