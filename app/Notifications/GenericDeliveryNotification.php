<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GenericDeliveryNotification extends Notification
{
    use Queueable;

    /**
     * @param  array{subject?: string, body?: string, action_url?: string, action_text?: string}  $payload
     */
    public function __construct(private readonly array $payload) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->payload['subject'] ?? config('app.name').' notification')
            ->view('mail-notification', ['payload' => $this->payload]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->payload;
    }
}
