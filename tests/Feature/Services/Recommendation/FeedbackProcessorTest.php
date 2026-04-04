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
        profileUpdater: new ProfileUpdater,
    );
});

it('processes an interested reaction and increases category score', function () {
    $user = User::factory()->create(['interest_profile' => ['music' => 0.5]]);
    $event = Event::factory()->create(['category' => EventCategory::Music, 'tags' => ['jazz']]);

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
    expect($user->interest_profile['music'])->toBeGreaterThan(0.5);
});

it('processes not_interested and decreases score', function () {
    $user = User::factory()->create(['interest_profile' => ['sports' => 0.6]]);
    $event = Event::factory()->create(['category' => EventCategory::Sports, 'tags' => []]);

    $reaction = UserEventReaction::factory()->create([
        'user_id' => $user->id,
        'event_id' => $event->id,
        'reaction' => Reaction::NotInterested,
        'is_processed' => false,
    ]);

    $this->processor->processReaction($reaction);

    $user->refresh();
    expect($user->interest_profile['sports'])->toBeLessThan(0.6);
});

it('skips already processed reactions', function () {
    $user = User::factory()->create(['interest_profile' => ['music' => 0.5]]);
    $event = Event::factory()->create(['category' => EventCategory::Music, 'tags' => []]);

    $reaction = UserEventReaction::factory()->create([
        'user_id' => $user->id,
        'event_id' => $event->id,
        'reaction' => Reaction::Interested,
        'is_processed' => true,
    ]);

    $this->processor->processReaction($reaction);

    $user->refresh();
    expect($user->interest_profile['music'])->toBe(0.5);
});

it('processes all unprocessed reactions in batch', function () {
    $user = User::factory()->create(['interest_profile' => ['music' => 0.5, 'sports' => 0.5]]);

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

it('saved reaction gives higher delta than interested', function () {
    $user = User::factory()->create(['interest_profile' => ['arts' => 0.4]]);
    $event = Event::factory()->create(['category' => EventCategory::Arts, 'tags' => []]);

    $reaction = UserEventReaction::factory()->create([
        'user_id' => $user->id,
        'event_id' => $event->id,
        'reaction' => Reaction::Saved,
        'is_processed' => false,
    ]);

    $this->processor->processReaction($reaction);

    $user->refresh();
    $savedDelta = config('eventpulse.feedback.deltas.saved');
    expect($user->interest_profile['arts'])->toBe(min(1.0, 0.4 + $savedDelta));
});
