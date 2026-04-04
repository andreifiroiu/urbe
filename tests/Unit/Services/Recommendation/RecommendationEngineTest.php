<?php

declare(strict_types=1);

use App\Enums\EventCategory;
use App\Models\Event;
use App\Models\User;
use App\Services\InterestProfile\ProfileScorer;
use App\Services\Recommendation\DiscoveryEngine;
use App\Services\Recommendation\DiversityFilter;
use App\Services\Recommendation\RecommendationEngine;

beforeEach(function () {
    $this->engine = new RecommendationEngine(
        profileScorer: new ProfileScorer(),
        discoveryEngine: new DiscoveryEngine(),
        diversityFilter: new DiversityFilter(),
    );
});

it('scores music events higher for users who like music', function () {
    $user = User::factory()->create([
        'interest_profile' => ['Music' => 0.9, 'Sports' => 0.1],
        'city' => 'Bucharest',
    ]);

    $musicEvent = Event::factory()->create([
        'category' => EventCategory::Music,
        'tags' => ['jazz', 'live-music'],
        'city' => 'Bucharest',
        'starts_at' => now()->addDays(3),
        'is_classified' => true,
    ]);

    $sportsEvent = Event::factory()->create([
        'category' => EventCategory::Sports,
        'tags' => ['football'],
        'city' => 'Bucharest',
        'starts_at' => now()->addDays(3),
        'is_classified' => true,
    ]);

    $musicScore = $this->engine->scoreEvent($user, $musicEvent);
    $sportsScore = $this->engine->scoreEvent($user, $sportsEvent);

    expect($musicScore)->toBeGreaterThan($sportsScore);
});

it('returns scores between 0 and 1', function () {
    $user = User::factory()->create([
        'interest_profile' => ['Music' => 0.8],
        'city' => 'Bucharest',
    ]);

    $event = Event::factory()->create([
        'category' => EventCategory::Music,
        'city' => 'Bucharest',
        'starts_at' => now()->addDays(5),
    ]);

    $score = $this->engine->scoreEvent($user, $event);

    expect($score)->toBeGreaterThanOrEqual(0.0)->toBeLessThanOrEqual(1.0);
});

it('gives location bonus for same city events', function () {
    $user = User::factory()->create([
        'interest_profile' => ['Music' => 0.5],
        'city' => 'Bucharest',
    ]);

    $localEvent = Event::factory()->create([
        'category' => EventCategory::Music,
        'city' => 'Bucharest',
        'starts_at' => now()->addDays(5),
    ]);

    $remoteEvent = Event::factory()->create([
        'category' => EventCategory::Music,
        'city' => 'Cluj-Napoca',
        'starts_at' => now()->addDays(5),
    ]);

    $localScore = $this->engine->scoreEvent($user, $localEvent);
    $remoteScore = $this->engine->scoreEvent($user, $remoteEvent);

    expect($localScore)->toBeGreaterThan($remoteScore);
});

it('generates a recommendation batch with events', function () {
    $user = User::factory()->create([
        'interest_profile' => ['Music' => 0.8, 'Arts' => 0.6],
        'city' => 'Bucharest',
    ]);

    Event::factory()->count(15)->create([
        'city' => 'Bucharest',
        'starts_at' => now()->addDays(5),
        'is_classified' => true,
    ]);

    $batch = $this->engine->recommend($user, 10);

    expect($batch->userId)->toBe($user->id);
    expect($batch->recommendedEventIds)->toBeArray();
    expect($batch->discoveryEventIds)->toBeArray();
    expect($batch->generatedAt)->toBeInstanceOf(\DateTimeImmutable::class);
});
