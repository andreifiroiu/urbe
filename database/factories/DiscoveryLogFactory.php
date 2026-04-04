<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EventCategory;
use App\Models\DiscoveryLog;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscoveryLog>
 */
class DiscoveryLogFactory extends Factory
{
    protected $model = DiscoveryLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'event_id' => Event::factory(),
            'category_explored' => fake()->randomElement(EventCategory::cases())->value,
            'surprise_score' => fake()->randomFloat(2, 0, 1),
            'outcome' => fake()->randomElement(['interested', 'not_interested', 'ignored', null]),
        ];
    }
}
