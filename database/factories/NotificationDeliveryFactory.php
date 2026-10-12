<?php

namespace Database\Factories;

use App\Models\NotificationDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationDelivery>
 */
class NotificationDeliveryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'channel' => 'mail',
            'type' => 'invitation',
            'status' => NotificationDelivery::STATUS_PENDING,
            'attempts' => 0,
            'payload' => [
                'to' => fake()->safeEmail(),
                'subject' => fake()->sentence(4),
                'body' => fake()->paragraph(),
            ],
        ];
    }
}
