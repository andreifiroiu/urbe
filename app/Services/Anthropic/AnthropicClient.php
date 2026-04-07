<?php

declare(strict_types=1);

namespace App\Services\Anthropic;

use App\Models\LlmUsageLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AnthropicClient
{
    private const string API_URL = 'https://api.anthropic.com/v1/messages';

    private const string API_VERSION = '2023-06-01';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly int $maxTokens = 1024,
    ) {}

    /**
     * Send a message to the Claude API and return the parsed response.
     *
     * @param  string  $systemPrompt  The system prompt to set context.
     * @param  string  $userMessage  The user message to send.
     * @param  string  $operation  The operation name for usage logging.
     * @param  array<string, mixed>  $logMetadata  Additional metadata to log.
     * @return array{content: string, input_tokens: int, output_tokens: int}
     *
     * @throws RuntimeException If the API call fails after retries.
     */
    public function sendMessage(
        string $systemPrompt,
        string $userMessage,
        string $operation = 'unknown',
        array $logMetadata = [],
    ): array {
        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => self::API_VERSION,
            ])
                ->timeout(30)
                ->connectTimeout(10)
                ->retry(2, 1000, throw: false)
                ->post(self::API_URL, [
                    'model' => $this->model,
                    'max_tokens' => $this->maxTokens,
                    'system' => $systemPrompt,
                    'messages' => [
                        ['role' => 'user', 'content' => $userMessage],
                    ],
                ]);

            if ($response->failed()) {
                Log::error('Anthropic API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'operation' => $operation,
                ]);

                throw new RuntimeException(
                    "Anthropic API returned status {$response->status()}: {$response->body()}"
                );
            }

            $data = $response->json();
            $content = $data['content'][0]['text'] ?? '';
            $inputTokens = $data['usage']['input_tokens'] ?? 0;
            $outputTokens = $data['usage']['output_tokens'] ?? 0;

            $this->logUsage($operation, $inputTokens, $outputTokens, $logMetadata);

            return [
                'content' => $content,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
            ];
        } catch (ConnectionException $e) {
            Log::error('Anthropic API connection failed', [
                'message' => $e->getMessage(),
                'operation' => $operation,
            ]);

            throw new RuntimeException("Anthropic API connection failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Send a multi-turn conversation to the Claude API.
     *
     * @param  string  $systemPrompt  The system prompt.
     * @param  array<int, array{role: string, content: string}>  $messages  Alternating user/assistant messages.
     * @param  string  $operation  Operation name for logging.
     * @param  array<string, mixed>  $logMetadata  Extra log metadata.
     * @return array{content: string, input_tokens: int, output_tokens: int}
     *
     * @throws RuntimeException If the API call fails.
     */
    public function sendMultiTurn(
        string $systemPrompt,
        array $messages,
        string $operation = 'unknown',
        array $logMetadata = [],
    ): array {
        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => self::API_VERSION,
            ])
                ->timeout(30)
                ->connectTimeout(10)
                ->retry(2, 1000, throw: false)
                ->post(self::API_URL, [
                    'model' => $this->model,
                    'max_tokens' => $this->maxTokens,
                    'system' => $systemPrompt,
                    'messages' => $messages,
                ]);

            if ($response->failed()) {
                Log::error('Anthropic API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'operation' => $operation,
                ]);

                throw new RuntimeException(
                    "Anthropic API returned status {$response->status()}: {$response->body()}"
                );
            }

            $data = $response->json();
            $content = $data['content'][0]['text'] ?? '';
            $inputTokens = $data['usage']['input_tokens'] ?? 0;
            $outputTokens = $data['usage']['output_tokens'] ?? 0;

            $this->logUsage($operation, $inputTokens, $outputTokens, $logMetadata);

            return [
                'content' => $content,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
            ];
        } catch (ConnectionException $e) {
            Log::error('Anthropic API connection failed', [
                'message' => $e->getMessage(),
                'operation' => $operation,
            ]);

            throw new RuntimeException("Anthropic API connection failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Log token usage to the llm_usage_logs table.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function logUsage(string $operation, int $inputTokens, int $outputTokens, array $metadata): void
    {
        try {
            LlmUsageLog::create([
                'operation' => $operation,
                'model' => $this->model,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'cost_usd' => $this->estimateCost($inputTokens, $outputTokens),
                'metadata' => $metadata,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to log LLM usage', [
                'error' => $e->getMessage(),
                'operation' => $operation,
            ]);
        }
    }

    /**
     * Estimate USD cost based on token counts.
     *
     * Uses approximate Sonnet pricing: $3/M input, $15/M output.
     */
    private function estimateCost(int $inputTokens, int $outputTokens): float
    {
        return ($inputTokens * 3.0 / 1_000_000) + ($outputTokens * 15.0 / 1_000_000);
    }
}
