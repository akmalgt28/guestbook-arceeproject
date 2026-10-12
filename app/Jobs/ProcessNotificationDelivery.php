<?php

namespace App\Jobs;

use App\Models\NotificationDelivery;
use App\Notifications\GenericDeliveryNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;
use Throwable;

class ProcessNotificationDelivery implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public NotificationDelivery $delivery) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(): void
    {
        $delivery = $this->delivery->fresh();
        if (! $delivery || $delivery->status === NotificationDelivery::STATUS_SENT) {
            return;
        }

        $delivery->update([
            'status' => NotificationDelivery::STATUS_PROCESSING,
            'attempts' => $delivery->attempts + 1,
            'last_error' => null,
        ]);

        if ($delivery->channel !== 'mail') {
            throw new \RuntimeException('Unsupported notification channel: '.$delivery->channel);
        }

        $recipient = data_get($delivery->payload, 'to');
        if (! is_string($recipient) || $recipient === '') {
            throw new \InvalidArgumentException('Notification recipient email is required.');
        }

        $payload = [...($delivery->payload ?? []), 'type' => $delivery->type];
        Notification::route('mail', $recipient)->notify(new GenericDeliveryNotification($payload));

        $delivery->update([
            'status' => NotificationDelivery::STATUS_SENT,
            'sent_at' => now(),
            'failed_at' => null,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->delivery->fresh()?->update([
            'status' => NotificationDelivery::STATUS_FAILED,
            'failed_at' => now(),
            'last_error' => $exception?->getMessage(),
        ]);
    }
}
