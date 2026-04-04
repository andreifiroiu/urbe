<?php

declare(strict_types=1);

use App\Enums\EventCategory;
use App\Models\Event;
use App\Models\User;
use App\Services\InterestProfile\ProfileUpdater;

beforeEach(function () {
    $this->updater = new ProfileUpdater();
});

it('increases category score for interested reaction', function () {
    $user = User::factory()->create([
        'interest_profile' => ['Music' => 0.5],
    ]);

    $event = Event::factory()->create([
        'category' => EventCategory::Music,
        'tags' => ['jazz'],
    ]);

    $this->updater->updateFromFeedback($user, $event, 'interested');

    $user->refresh();
    expect($user->interest_profile['Music'])->toBeGreaterThan(0.5);
});

it('decreases category score for not_interested reaction', function () {
    $user = User::factory()->create([
        'interest_profile' => ['Sports' => 0.6],
    ]);

    $event = Event::factory()->create([
        'category' => EventCategory::Sports,
        'tags' => [],
    ]);

    $this->updater->updateFromFeedback($user, $event, 'not_interested');

    $user->refresh();
    expect($user->interest_profile['Sports'])->toBeLessThan(0.6);
});

it('clamps score to maximum 1.0', function () {
    $user = User::factory()->create([
        'interest_profile' => ['Music' => 0.95],
    ]);

    $event = Event::factory()->create([
        'category' => EventCategory::Music,
        'tags' => [],
    ]);

    $this->updater->updateFromFeedback($user, $event, 'saved');

    $user->refresh();
    expect($user->interest_profile['Music'])->toBeLessThanOrEqual(1.0);
});

it('clamps score to minimum 0.0', function () {
    $user = User::factory()->create([
        'interest_profile' => ['Technology' => 0.05],
    ]);

    $event = Event::factory()->create([
        'category' => EventCategory::Technology,
        'tags' => [],
    ]);

    $this->updater->updateFromFeedback($user, $event, 'hidden');

    $user->refresh();
    expect($user->interest_profile['Technology'])->toBeGreaterThanOrEqual(0.0);
});

it('updates tag scores alongside category scores', function () {
    $user = User::factory()->create([
        'interest_profile' => ['Music' => 0.5, 'tag:jazz' => 0.3],
    ]);

    $event = Event::factory()->create([
        'category' => EventCategory::Music,
        'tags' => ['jazz', 'live-music'],
    ]);

    $this->updater->updateFromFeedback($user, $event, 'interested');

    $user->refresh();
    expect($user->interest_profile)->toHaveKey('tag:jazz');
    expect($user->interest_profile)->toHaveKey('tag:live-music');
    expect($user->interest_profile['tag:jazz'])->toBeGreaterThan(0.3);
});

it('does nothing for zero delta reactions', function () {
    $user = User::factory()->create([
        'interest_profile' => ['Music' => 0.5],
    ]);

    $event = Event::factory()->create([
        'category' => EventCategory::Music,
        'tags' => [],
    ]);

    // 'unknown_reaction' should have no delta configured
    $this->updater->updateFromFeedback($user, $event, 'unknown_reaction');

    $user->refresh();
    expect($user->interest_profile['Music'])->toBe(0.5);
});

it('correctly clamps scores via clampScore method', function () {
    expect($this->updater->clampScore(1.5))->toBe(1.0);
    expect($this->updater->clampScore(-0.3))->toBe(0.0);
    expect($this->updater->clampScore(0.7))->toBe(0.7);
});
