<?php

declare(strict_types=1);

use App\Enums\EventCategory;
use App\Models\Event;
use App\Models\User;
use App\Services\Recommendation\DiscoveryEngine;

beforeEach(function () {
    $this->engine = new DiscoveryEngine();
});

it('discovers events from categories with low user scores', function () {
    $user = User::factory()->create([
        'interest_profile' => [
            'Music' => 0.9,
            'Sports' => 0.8,
            'Technology' => 0.1,
            'Arts' => 0.0,
        ],
    ]);

    // Create events in low-score categories
    Event::factory()->create([
        'category' => EventCategory::Technology,
        'starts_at' => now()->addDays(5),
        'is_classified' => true,
    ]);
    Event::factory()->create([
        'category' => EventCategory::Arts,
        'starts_at' => now()->addDays(5),
        'is_classified' => true,
    ]);

    $discoveries = $this->engine->discoverForUser($user, 2);

    expect($discoveries)->toHaveCount(2);
    $categories = $discoveries->pluck('category.value')->toArray();
    // Should not include Music or Sports (high scores)
    expect($categories)->not->toContain('Music');
    expect($categories)->not->toContain('Sports');
});

it('respects the exploration budget count', function () {
    $user = User::factory()->create([
        'interest_profile' => ['Music' => 0.9],
    ]);

    Event::factory()->count(10)->create([
        'category' => EventCategory::Technology,
        'starts_at' => now()->addDays(5),
        'is_classified' => true,
    ]);

    $discoveries = $this->engine->discoverForUser($user, 3);

    expect($discoveries->count())->toBeLessThanOrEqual(3);
});

it('calculates surprise score as inverse of profile score', function () {
    $user = User::factory()->create([
        'interest_profile' => ['Music' => 0.8, 'Technology' => 0.2],
    ]);

    $musicEvent = Event::factory()->create(['category' => EventCategory::Music]);
    $techEvent = Event::factory()->create(['category' => EventCategory::Technology]);

    $musicSurprise = $this->engine->calculateSurpriseScore($user, $musicEvent);
    $techSurprise = $this->engine->calculateSurpriseScore($user, $techEvent);

    expect($musicSurprise)->toBe(0.2); // 1.0 - 0.8
    expect($techSurprise)->toBe(0.8); // 1.0 - 0.2
});

it('returns empty collection when user has high scores everywhere', function () {
    $profile = [];
    foreach (EventCategory::cases() as $category) {
        $profile[$category->value] = 0.9;
    }

    $user = User::factory()->create(['interest_profile' => $profile]);

    $discoveries = $this->engine->discoverForUser($user, 2);

    expect($discoveries)->toBeEmpty();
});
