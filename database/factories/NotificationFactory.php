<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\NotificationChannel;
use App\Enums\NotificationFrequency;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'channel' => fake()->randomElement(NotificationChannel::cases()),
            'frequency' => fake()->randomElement(NotificationFrequency::cases()),
            'event_ids' => [fake()->uuid(), fake()->uuid()],
            'discovery_event_ids' => [fake()->uuid()],
            'subject' => fake()->sentence(),
            'body_html' => '<p>' . fake()->paragraphs(2, true) . '</p>',
            'sent_at' => null,
            'opened_at' => null,
        ];
    }

    /**
     * Indicate that the notification has been sent.
     */
    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'sent_at' => now(),
        ]);
    }
}
