<?php

declare(strict_types=1);

use App\Models\LlmUsageLog;
use App\Services\Anthropic\AnthropicClient;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->client = new AnthropicClient(
        apiKey: 'test-api-key',
        model: 'claude-sonnet-4-20250514',
        maxTokens: 256,
    );
});

it('sends a message and returns parsed response', function () {
    Http::fake([
        'api.anthropic.com/v1/messages' => Http::response([
            'content' => [
                ['type' => 'text', 'text' => '{"result": "ok"}'],
            ],
            'usage' => [
                'input_tokens' => 100,
                'output_tokens' => 50,
            ],
        ]),
    ]);

    $response = $this->client->sendMessage(
        systemPrompt: 'You are a test assistant.',
        userMessage: 'Hello',
        operation: 'test',
    );

    expect($response['content'])->toBe('{"result": "ok"}');
    expect($response['input_tokens'])->toBe(100);
    expect($response['output_tokens'])->toBe(50);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.anthropic.com/v1/messages'
            && $request->header('x-api-key')[0] === 'test-api-key'
            && $request->header('anthropic-version')[0] === '2023-06-01'
            && $request['model'] === 'claude-sonnet-4-20250514'
            && $request['max_tokens'] === 256
            && $request['system'] === 'You are a test assistant.'
            && $request['messages'][0]['role'] === 'user'
            && $request['messages'][0]['content'] === 'Hello';
    });
});

it('logs token usage after successful call', function () {
    Http::fake([
        'api.anthropic.com/v1/messages' => Http::response([
            'content' => [['type' => 'text', 'text' => 'response']],
            'usage' => ['input_tokens' => 200, 'output_tokens' => 100],
        ]),
    ]);

    $this->client->sendMessage(
        systemPrompt: 'system',
        userMessage: 'user',
        operation: 'classification',
        logMetadata: ['event_id' => 'test-123'],
    );

    $log = LlmUsageLog::latest()->first();

    expect($log)->not->toBeNull();
    expect($log->operation)->toBe('classification');
    expect($log->model)->toBe('claude-sonnet-4-20250514');
    expect($log->input_tokens)->toBe(200);
    expect($log->output_tokens)->toBe(100);
    expect($log->cost_usd)->toBeGreaterThan(0);
    expect($log->metadata)->toBe(['event_id' => 'test-123']);
});

it('throws on API failure', function () {
    Http::fake([
        'api.anthropic.com/v1/messages' => Http::response(
            ['error' => ['message' => 'rate limited']],
            429,
        ),
    ]);

    $this->client->sendMessage(
        systemPrompt: 'system',
        userMessage: 'user',
        operation: 'test',
    );
})->throws(RuntimeException::class, 'Anthropic API returned status 429');
