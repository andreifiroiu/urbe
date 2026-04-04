<?php

declare(strict_types=1);

use App\Enums\EventCategory;
use App\Enums\Reaction;
use App\Models\Event;
use App\Models\User;
use App\Models\UserEventReaction;
use App\Services\InterestProfile\ProfileUpdater;
use App\Services\Recommendation\FeedbackProcessor;

beforeEach(function () {
    $this->processor = new FeedbackProcessor(
        profileUpdater: new ProfileUpdater(),
    );
});

it('processes an interested reaction and updates the profile', function () {
    $user = User::factory()->create([
        'interest_profile' => ['Music' => 0.5],
    ]);

    $event = Event::factory()->create([
        'category' => EventCategory::Music,
        'tags' => ['jazz'],
    ]);

    $reaction = UserEventReaction::factory()->create([
        'user_id' => $user->id,
        'event_id' => $event->id,
        'reaction' => Reaction::Interested,
        'is_processed' => false,
    ]);

    $this->processor->processReaction($reaction);

    $reaction->refresh();
    $user->refresh();

    expect($reaction->is_processed)->toBeTrue();
    expect($user->interest_profile['Music'])->toBeGreaterThan(0.5);
});

it('processes a not_interested reaction and decreases the score', function () {
    $user = User::factory()->create([
        'interest_profile' => ['Sports' => 0.6],
    ]);

    $event = Event::factory()->create([
        'category' => EventCategory::Sports,
        'tags' => [],
    ]);

    $reaction = UserEventReaction::factory()->create([
        'user_id' => $user->id,
        'event_id' => $event->id,
        'reaction' => Reaction::NotInterested,
        'is_processed' => false,
    ]);

    $this->processor->processReaction($reaction);

    $user->refresh();
    expect($user->interest_profile['Sports'])->toBeLessThan(0.6);
});

it('skips already processed reactions', function () {
    $user = User::factory()->create([
        'interest_profile' => ['Music' => 0.5],
    ]);

    $event = Event::factory()->create([
        'category' => EventCategory::Music,
        'tags' => [],
    ]);

    $reaction = UserEventReaction::factory()->create([
        'user_id' => $user->id,
        'event_id' => $event->id,
        'reaction' => Reaction::Interested,
        'is_processed' => true,
    ]);

    $this->processor->processReaction($reaction);

    $user->refresh();
    // Score should remain unchanged since reaction was already processed
    expect($user->interest_profile['Music'])->toBe(0.5);
});

it('processes saved reaction with higher delta than interested', function () {
    $user = User::factory()->create([
        'interest_profile' => ['Arts' => 0.4],
    ]);

    $event = Event::factory()->create([
        'category' => EventCategory::Arts,
        'tags' => [],
    ]);

    $reaction = UserEventReaction::factory()->create([
        'user_id' => $user->id,
        'event_id' => $event->id,
        'reaction' => Reaction::Saved,
        'is_processed' => false,
    ]);

    $this->processor->processReaction($reaction);

    $user->refresh();
    $savedDelta = config('eventpulse.feedback.deltas.saved');
    $expectedScore = 0.4 + $savedDelta;
    expect($user->interest_profile['Arts'])->toBe(min(1.0, $expectedScore));
});

it('processes all unprocessed reactions in batch', function () {
    $user = User::factory()->create([
        'interest_profile' => ['Music' => 0.5, 'Sports' => 0.5],
    ]);

    $event1 = Event::factory()->create(['category' => EventCategory::Music, 'tags' => []]);
    $event2 = Event::factory()->create(['category' => EventCategory::Sports, 'tags' => []]);

    UserEventReaction::factory()->create([
        'user_id' => $user->id,
        'event_id' => $event1->id,
        'reaction' => Reaction::Interested,
        'is_processed' => false,
    ]);
    UserEventReaction::factory()->create([
        'user_id' => $user->id,
        'event_id' => $event2->id,
        'reaction' => Reaction::Hidden,
        'is_processed' => false,
    ]);

    $count = $this->processor->processUnprocessed();

    expect($count)->toBe(2);
    expect(UserEventReaction::where('is_processed', false)->count())->toBe(0);
});
