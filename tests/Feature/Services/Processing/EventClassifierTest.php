<?php

declare(strict_types=1);

use App\Enums\EventCategory;
use App\Models\Event;
use App\Models\LlmUsageLog;
use App\Services\Anthropic\AnthropicClient;
use App\Services\Processing\EventClassifier;
use Illuminate\Support\Facades\Http;

function makeClassifier(): EventClassifier
{
    return new EventClassifier(
        client: new AnthropicClient(
            apiKey: 'test-key',
            model: 'claude-sonnet-4-20250514',
        ),
    );
}

function fakeClaudeResponse(string $json, int $inputTokens = 100, int $outputTokens = 50): void
{
    Http::fake([
        'api.anthropic.com/v1/messages' => Http::response([
            'content' => [['type' => 'text', 'text' => $json]],
            'usage' => ['input_tokens' => $inputTokens, 'output_tokens' => $outputTokens],
        ]),
    ]);
}

it('classifies an event with a valid Claude response', function () {
    fakeClaudeResponse('{"category": "Music", "tags": ["jazz", "live-music"], "confidence": 0.95}');

    $event = Event::factory()->create([
        'title' => 'Jazz Night at Control',
        'description' => 'Live jazz every Friday at Control Club.',
        'venue' => 'Control Club',
        'city' => 'Bucharest',
        'is_classified' => false,
        'tags' => [],
    ]);

    $result = makeClassifier()->classify($event);

    expect($result->category)->toBe('music');
    expect($result->tags)->toBe(['jazz', 'live-music']);
    expect($result->confidence)->toBe(0.95);

    $event->refresh();
    expect($event->is_classified)->toBeTrue();
    expect($event->category)->toBe(EventCategory::Music);
    expect($event->tags)->toBe(['jazz', 'live-music']);
});

it('handles JSON wrapped in markdown code blocks', function () {
    fakeClaudeResponse('```json
{"category": "Technology", "tags": ["startup", "networking"], "confidence": 0.88}
```');

    $event = Event::factory()->create([
        'title' => 'Bucharest Startup Meetup',
        'is_classified' => false,
        'tags' => [],
    ]);

    $result = makeClassifier()->classify($event);

    expect($result->category)->toBe('technology');
    expect($result->tags)->toBe(['startup', 'networking']);
    expect($result->confidence)->toBe(0.88);
});

it('normalizes category casing from Claude response', function () {
    fakeClaudeResponse('{"category": "SPORTS", "tags": ["football"], "confidence": 0.9}');

    $event = Event::factory()->create(['is_classified' => false, 'tags' => []]);

    $result = makeClassifier()->classify($event);

    expect($result->category)->toBe('sports');
});

it('falls back to Other for unknown categories', function () {
    fakeClaudeResponse('{"category": "Underwater Basket Weaving", "tags": [], "confidence": 0.7}');

    $event = Event::factory()->create(['is_classified' => false, 'tags' => []]);

    $result = makeClassifier()->classify($event);

    expect($result->category)->toBe('other');
});

it('falls back to Other when Claude returns invalid JSON', function () {
    fakeClaudeResponse('I cannot classify this event properly, sorry.');

    $event = Event::factory()->create(['is_classified' => false, 'tags' => []]);

    $result = makeClassifier()->classify($event);

    expect($result->category)->toBe('other');
    expect($result->tags)->toBe([]);
    expect($result->confidence)->toBe(0.0);

    $event->refresh();
    expect($event->is_classified)->toBeTrue();
});

it('falls back to Other when API call fails', function () {
    Http::fake([
        'api.anthropic.com/v1/messages' => Http::response(
            ['error' => ['message' => 'overloaded']],
            529,
        ),
    ]);

    $event = Event::factory()->create(['is_classified' => false, 'tags' => []]);

    $result = makeClassifier()->classify($event);

    expect($result->category)->toBe('other');
    expect($result->confidence)->toBe(0.0);

    $event->refresh();
    expect($event->is_classified)->toBeTrue();
});

it('clamps confidence to valid range', function () {
    fakeClaudeResponse('{"category": "Music", "tags": [], "confidence": 1.5}');

    $event = Event::factory()->create(['is_classified' => false, 'tags' => []]);

    $result = makeClassifier()->classify($event);

    expect($result->confidence)->toBe(1.0);
});

it('handles missing confidence with default', function () {
    fakeClaudeResponse('{"category": "Arts", "tags": ["painting"]}');

    $event = Event::factory()->create(['is_classified' => false, 'tags' => []]);

    $result = makeClassifier()->classify($event);

    expect($result->category)->toBe('arts');
    expect($result->confidence)->toBe(0.5);
});

it('filters out non-string tags', function () {
    fakeClaudeResponse('{"category": "Food", "tags": ["street-food", 123, null, "vegan"], "confidence": 0.85}');

    $event = Event::factory()->create(['is_classified' => false, 'tags' => []]);

    $result = makeClassifier()->classify($event);

    expect($result->tags)->toBe(['street-food', 'vegan']);
});

it('logs token usage on successful classification', function () {
    fakeClaudeResponse('{"category": "Music", "tags": [], "confidence": 0.9}', 150, 80);

    $event = Event::factory()->create(['is_classified' => false, 'tags' => []]);

    makeClassifier()->classify($event);

    $log = LlmUsageLog::where('operation', 'classification')->latest()->first();

    expect($log)->not->toBeNull();
    expect($log->input_tokens)->toBe(150);
    expect($log->output_tokens)->toBe(80);
    expect($log->metadata)->toHaveKey('event_id', $event->id);
});

it('includes event details in the prompt sent to Claude', function () {
    fakeClaudeResponse('{"category": "Music", "tags": ["jazz"], "confidence": 0.9}');

    $event = Event::factory()->create([
        'title' => 'Jazz Night at Control',
        'description' => 'Live jazz every Friday.',
        'venue' => 'Control Club',
        'city' => 'Bucharest',
        'is_classified' => false,
        'tags' => ['existing-tag'],
    ]);

    makeClassifier()->classify($event);

    Http::assertSent(function ($request) {
        $message = $request['messages'][0]['content'];

        return str_contains($message, 'Title: Jazz Night at Control')
            && str_contains($message, 'Description: Live jazz every Friday.')
            && str_contains($message, 'Venue: Control Club')
            && str_contains($message, 'City: Bucharest')
            && str_contains($message, 'Source Tags: existing-tag');
    });
});

it('classifies a batch of events', function () {
    Http::fake([
        'api.anthropic.com/v1/messages' => Http::sequence()
            ->push([
                'content' => [['type' => 'text', 'text' => '{"category": "Music", "tags": ["jazz"], "confidence": 0.9}']],
                'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
            ])
            ->push([
                'content' => [['type' => 'text', 'text' => '{"category": "Sports", "tags": ["football"], "confidence": 0.85}']],
                'usage' => ['input_tokens' => 110, 'output_tokens' => 55],
            ]),
    ]);

    $events = Event::factory()->count(2)->create(['is_classified' => false, 'tags' => []]);

    $results = makeClassifier()->classifyBatch($events);

    expect($results)->toHaveCount(2);
    expect($results->first()->category)->toBe('music');
    expect($results->last()->category)->toBe('sports');
});

it('truncates long descriptions in the prompt', function () {
    fakeClaudeResponse('{"category": "Other", "tags": [], "confidence": 0.5}');

    $event = Event::factory()->create([
        'description' => str_repeat('A very long sentence. ', 200),
        'is_classified' => false,
        'tags' => [],
    ]);

    makeClassifier()->classify($event);

    Http::assertSent(function ($request) {
        $message = $request['messages'][0]['content'];

        // Description should be truncated to 1000 chars
        return mb_strlen($message) < 1500;
    });
});
