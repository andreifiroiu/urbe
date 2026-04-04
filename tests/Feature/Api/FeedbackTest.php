<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\User;

it('can react to an event', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create();

    $response = $this->actingAs($user)->postJson('/feedback', [
        'event_id' => $event->id,
        'reaction' => 'interested',
    ]);

    $response->assertStatus(200);
    $this->assertDatabaseHas('user_event_reactions', [
        'user_id' => $user->id,
        'event_id' => $event->id,
        'reaction' => 'interested',
    ]);
});

it('validates reaction type', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create();

    $response = $this->actingAs($user)->postJson('/feedback', [
        'event_id' => $event->id,
        'reaction' => 'invalid_reaction',
    ]);

    $response->assertStatus(422);
});

it('validates event exists', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/feedback', [
        'event_id' => '00000000-0000-0000-0000-000000000000',
        'reaction' => 'interested',
    ]);

    $response->assertStatus(422);
});

it('requires authentication to submit feedback', function () {
    $event = Event::factory()->create();

    $response = $this->postJson('/feedback', [
        'event_id' => $event->id,
        'reaction' => 'interested',
    ]);

    $response->assertStatus(401);
});
