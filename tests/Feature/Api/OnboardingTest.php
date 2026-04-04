<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->withoutVite();
});

function fakeOnboardingClaude(string $text = 'Tell me more about your interests!'): void
{
    Http::fake([
        'api.anthropic.com/v1/messages' => Http::response([
            'content' => [['type' => 'text', 'text' => $text]],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
        ]),
    ]);
}

it('shows the onboarding page with welcome message for new users', function () {
    $user = User::factory()->create(['onboarding_completed' => false]);

    $response = $this->actingAs($user)->get('/onboarding');

    $response->assertStatus(200);
});

it('creates a welcome message on first visit', function () {
    $user = User::factory()->create(['onboarding_completed' => false]);

    $this->actingAs($user)->get('/onboarding');

    expect($user->chatMessages()->where('context', 'onboarding')->count())->toBe(1);
    expect($user->chatMessages()->first()->role)->toBe('assistant');
});

it('sends a chat message and gets a response', function () {
    fakeOnboardingClaude('What kinds of music do you enjoy?');

    $user = User::factory()->create(['onboarding_completed' => false]);
    // Seed welcome message
    $user->chatMessages()->create([
        'role' => 'assistant',
        'content' => 'Welcome!',
        'context' => 'onboarding',
    ]);

    $response = $this->actingAs($user)->postJson('/onboarding/chat', [
        'message' => 'I love live jazz concerts',
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'userMessage' => ['id', 'role', 'content'],
        'assistantMessage' => ['id', 'role', 'content'],
        'onboardingComplete',
    ]);

    expect($user->chatMessages()->where('role', 'user')->count())->toBe(1);
    expect($user->chatMessages()->where('role', 'assistant')->count())->toBe(2); // welcome + response
});

it('validates chat message is required', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/onboarding/chat', [
        'message' => '',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('message');
});

it('validates chat message max length', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/onboarding/chat', [
        'message' => str_repeat('a', 2001),
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('message');
});

it('requires authentication for chat', function () {
    $response = $this->postJson('/onboarding/chat', [
        'message' => 'Hello',
    ]);

    $response->assertStatus(401);
});

it('confirms profile and updates user', function () {
    Http::fake([
        'api.anthropic.com/v1/messages' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode([
                'music' => 0.9,
                'arts' => 0.7,
                'tag:jazz' => 0.85,
                'city' => 'Bucharest',
            ])]],
            'usage' => ['input_tokens' => 300, 'output_tokens' => 100],
        ]),
    ]);

    $user = User::factory()->create([
        'onboarding_completed' => false,
        'interest_profile' => [],
    ]);

    $user->chatMessages()->createMany([
        ['role' => 'assistant', 'content' => 'Welcome!', 'context' => 'onboarding'],
        ['role' => 'user', 'content' => 'I like jazz and art', 'context' => 'onboarding'],
        ['role' => 'assistant', 'content' => 'Nice!', 'context' => 'onboarding'],
    ]);

    $response = $this->actingAs($user)->postJson('/onboarding/confirm-profile');

    $response->assertStatus(200);
    $response->assertJson(['success' => true]);
    $response->assertJsonStructure(['profile']);

    $user->refresh();
    expect($user->onboarding_completed)->toBeTrue();
    expect($user->interest_profile)->toHaveKey('music');
    expect($user->interest_profile['music'])->toBe(0.9);
    expect($user->city)->toBe('Bucharest');
});

it('returns 422 when profile generation fails', function () {
    Http::fake([
        'api.anthropic.com/v1/messages' => Http::response(
            ['error' => ['message' => 'overloaded']],
            529,
        ),
    ]);

    $user = User::factory()->create(['onboarding_completed' => false]);
    $user->chatMessages()->create([
        'role' => 'user',
        'content' => 'test',
        'context' => 'onboarding',
    ]);

    $response = $this->actingAs($user)->postJson('/onboarding/confirm-profile');

    $response->assertStatus(422);
    $response->assertJson(['success' => false]);
});

it('returns 422 when no chat history exists', function () {
    $user = User::factory()->create(['onboarding_completed' => false]);

    $response = $this->actingAs($user)->postJson('/onboarding/confirm-profile');

    $response->assertStatus(422);
});
