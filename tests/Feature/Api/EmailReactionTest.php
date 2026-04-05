<?php

declare(strict_types=1);

use App\Enums\Reaction;
use App\Jobs\ProcessFeedbackJob;
use App\Models\Event;
use App\Models\User;
use App\Models\UserEventReaction;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;

it('records a reaction from a signed email URL', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create();

    $url = URL::signedRoute('reactions.email', [
        'user' => $user->id,
        'event' => $event->id,
        'reaction' => 'interested',
    ]);

    $response = $this->get($url);

    $response->assertStatus(200);
    $response->assertSee('Feedback Recorded');
    $response->assertSee($event->title);

    $this->assertDatabaseHas('user_event_reactions', [
        'user_id' => $user->id,
        'event_id' => $event->id,
        'reaction' => 'interested',
    ]);
});

it('rejects unsigned URLs with 403', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create();

    $response = $this->get("/reactions/{$user->id}/{$event->id}/interested");

    $response->assertStatus(403);
});

it('handles not_interested reaction from email', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create();

    $url = URL::signedRoute('reactions.email', [
        'user' => $user->id,
        'event' => $event->id,
        'reaction' => 'not_interested',
    ]);

    $this->get($url);

    $this->assertDatabaseHas('user_event_reactions', [
        'user_id' => $user->id,
        'event_id' => $event->id,
        'reaction' => 'not_interested',
    ]);
});

it('handles saved reaction from email', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create();

    $url = URL::signedRoute('reactions.email', [
        'user' => $user->id,
        'event' => $event->id,
        'reaction' => 'saved',
    ]);

    $this->get($url);

    $this->assertDatabaseHas('user_event_reactions', [
        'user_id' => $user->id,
        'event_id' => $event->id,
        'reaction' => 'saved',
    ]);
});

it('returns 404 for invalid reaction types', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create();

    $url = URL::signedRoute('reactions.email', [
        'user' => $user->id,
        'event' => $event->id,
        'reaction' => 'invalid_type',
    ]);

    $response = $this->get($url);

    $response->assertStatus(404);
});

it('updates an existing reaction when clicked again', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create();

    // First reaction
    UserEventReaction::create([
        'user_id' => $user->id,
        'event_id' => $event->id,
        'reaction' => Reaction::Interested,
    ]);

    // Click "saved" from email
    $url = URL::signedRoute('reactions.email', [
        'user' => $user->id,
        'event' => $event->id,
        'reaction' => 'saved',
    ]);

    $this->get($url);

    expect(
        UserEventReaction::where('user_id', $user->id)
            ->where('event_id', $event->id)
            ->latest()
            ->first()
            ->reaction
    )->toBe(Reaction::Saved);
});

it('dispatches ProcessFeedbackJob on reaction', function () {
    Queue::fake();

    $user = User::factory()->create();
    $event = Event::factory()->create();

    $url = URL::signedRoute('reactions.email', [
        'user' => $user->id,
        'event' => $event->id,
        'reaction' => 'interested',
    ]);

    $this->get($url);

    Queue::assertPushed(ProcessFeedbackJob::class);
});
