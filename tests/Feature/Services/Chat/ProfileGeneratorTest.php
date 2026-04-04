<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Anthropic\AnthropicClient;
use App\Services\Chat\ProfileGenerator;
use Illuminate\Support\Facades\Http;

function makeProfileGenerator(): ProfileGenerator
{
    return new ProfileGenerator(
        client: new AnthropicClient(
            apiKey: 'test-key',
            model: 'claude-sonnet-4-20250514',
        ),
    );
}

it('generates a profile from chat history', function () {
    Http::fake([
        'api.anthropic.com/v1/messages' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode([
                'music' => 0.9,
                'arts' => 0.7,
                'sports' => 0.1,
                'tag:jazz' => 0.85,
                'tag:painting' => 0.6,
                'city' => 'Bucharest',
                'price_sensitive' => true,
                'preferred_times' => ['evening', 'weekend'],
            ])]],
            'usage' => ['input_tokens' => 300, 'output_tokens' => 100],
        ]),
    ]);

    $user = User::factory()->create();
    $user->chatMessages()->createMany([
        ['role' => 'assistant', 'content' => 'What events do you enjoy?', 'context' => 'onboarding'],
        ['role' => 'user', 'content' => 'I love jazz concerts and art galleries', 'context' => 'onboarding'],
        ['role' => 'assistant', 'content' => 'Great taste! Budget preference?', 'context' => 'onboarding'],
        ['role' => 'user', 'content' => 'I prefer free or cheap events', 'context' => 'onboarding'],
    ]);

    $profile = makeProfileGenerator()->generateFromChat($user);

    expect($profile)->toHaveKey('music');
    expect($profile['music'])->toBe(0.9);
    expect($profile)->toHaveKey('tag:jazz');
    expect($profile['tag:jazz'])->toBe(0.85);
    expect($profile)->toHaveKey('city', 'Bucharest');
    expect($profile)->toHaveKey('price_sensitive', true);
});

it('handles markdown-wrapped JSON in response', function () {
    Http::fake([
        'api.anthropic.com/v1/messages' => Http::response([
            'content' => [['type' => 'text', 'text' => "```json\n{\"music\": 0.8, \"tag:rock\": 0.7}\n```"]],
            'usage' => ['input_tokens' => 200, 'output_tokens' => 50],
        ]),
    ]);

    $user = User::factory()->create();
    $user->chatMessages()->create([
        'role' => 'user',
        'content' => 'I like rock music',
        'context' => 'onboarding',
    ]);

    $profile = makeProfileGenerator()->generateFromChat($user);

    expect($profile['music'])->toBe(0.8);
    expect($profile['tag:rock'])->toBe(0.7);
});

it('clamps scores to 0-1 range', function () {
    Http::fake([
        'api.anthropic.com/v1/messages' => Http::response([
            'content' => [['type' => 'text', 'text' => '{"music": 1.5, "sports": -0.3}']],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 30],
        ]),
    ]);

    $user = User::factory()->create();
    $user->chatMessages()->create([
        'role' => 'user',
        'content' => 'test',
        'context' => 'onboarding',
    ]);

    $profile = makeProfileGenerator()->generateFromChat($user);

    expect($profile['music'])->toBe(1.0);
    expect($profile['sports'])->toBe(0.0);
});

it('returns empty array when chat history is empty', function () {
    $user = User::factory()->create();

    $profile = makeProfileGenerator()->generateFromChat($user);

    expect($profile)->toBeEmpty();
});

it('returns empty array on Claude API failure', function () {
    Http::fake([
        'api.anthropic.com/v1/messages' => Http::response(
            ['error' => ['message' => 'overloaded']],
            529,
        ),
    ]);

    $user = User::factory()->create();
    $user->chatMessages()->create([
        'role' => 'user',
        'content' => 'I like music',
        'context' => 'onboarding',
    ]);

    $profile = makeProfileGenerator()->generateFromChat($user);

    expect($profile)->toBeEmpty();
});

it('returns empty array on invalid JSON response', function () {
    Http::fake([
        'api.anthropic.com/v1/messages' => Http::response([
            'content' => [['type' => 'text', 'text' => 'This is not JSON at all.']],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 20],
        ]),
    ]);

    $user = User::factory()->create();
    $user->chatMessages()->create([
        'role' => 'user',
        'content' => 'I like music',
        'context' => 'onboarding',
    ]);

    $profile = makeProfileGenerator()->generateFromChat($user);

    expect($profile)->toBeEmpty();
});

it('normalises category keys to lowercase', function () {
    Http::fake([
        'api.anthropic.com/v1/messages' => Http::response([
            'content' => [['type' => 'text', 'text' => '{"Music": 0.9, "SPORTS": 0.4}']],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 30],
        ]),
    ]);

    $user = User::factory()->create();
    $user->chatMessages()->create([
        'role' => 'user',
        'content' => 'test',
        'context' => 'onboarding',
    ]);

    $profile = makeProfileGenerator()->generateFromChat($user);

    expect($profile)->toHaveKey('music');
    expect($profile)->toHaveKey('sports');
    expect($profile)->not->toHaveKey('Music');
});

it('adds tag prefix to unrecognised keys', function () {
    Http::fake([
        'api.anthropic.com/v1/messages' => Http::response([
            'content' => [['type' => 'text', 'text' => '{"jazz": 0.9, "tag:rock": 0.8}']],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 30],
        ]),
    ]);

    $user = User::factory()->create();
    $user->chatMessages()->create([
        'role' => 'user',
        'content' => 'test',
        'context' => 'onboarding',
    ]);

    $profile = makeProfileGenerator()->generateFromChat($user);

    expect($profile)->toHaveKey('tag:jazz');
    expect($profile)->toHaveKey('tag:rock');
});

it('merges profiles by averaging overlapping scores', function () {
    $generator = makeProfileGenerator();

    $existing = ['music' => 0.6, 'tag:jazz' => 0.4, 'sports' => 0.8];
    $new = ['music' => 0.8, 'tag:jazz' => 1.0, 'technology' => 0.5];

    $merged = $generator->mergeProfiles($existing, $new);

    expect($merged['music'])->toEqualWithDelta(0.7, 0.001);
    expect($merged['tag:jazz'])->toEqualWithDelta(0.7, 0.001);
    expect($merged['sports'])->toBe(0.8); // unchanged
    expect($merged['technology'])->toBe(0.5); // new key
});

it('merges non-numeric metadata fields directly', function () {
    $generator = makeProfileGenerator();

    $existing = ['music' => 0.5, 'city' => 'Bucharest'];
    $new = ['city' => 'Cluj-Napoca', 'price_sensitive' => true];

    $merged = $generator->mergeProfiles($existing, $new);

    expect($merged['city'])->toBe('Cluj-Napoca');
    expect($merged['price_sensitive'])->toBeTrue();
    expect($merged['music'])->toBe(0.5);
});
