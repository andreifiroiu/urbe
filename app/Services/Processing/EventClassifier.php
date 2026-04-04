<?php

declare(strict_types=1);

namespace App\Services\Processing;

use App\DTOs\ClassifiedEvent;
use App\Enums\EventCategory;
use App\Models\Event;
use App\Services\Anthropic\AnthropicClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class EventClassifier
{
    public function __construct(
        private readonly AnthropicClient $client,
    ) {}

    /**
     * Classify a single event using the Claude API.
     *
     * Builds a prompt from the event data, sends it to Claude, parses the
     * structured JSON response, and returns a ClassifiedEvent DTO. Falls back
     * to EventCategory::Other on any failure.
     */
    public function classify(Event $event): ClassifiedEvent
    {
        $systemPrompt = config('eventpulse.llm.classification_prompt');

        $userMessage = $this->buildUserMessage($event);

        try {
            $response = $this->client->sendMessage(
                systemPrompt: $systemPrompt,
                userMessage: $userMessage,
                operation: 'classification',
                logMetadata: ['event_id' => $event->id],
            );

            $classified = $this->parseResponse($response['content']);

            $event->update([
                'category' => $classified->category,
                'tags' => $classified->tags,
                'is_classified' => true,
            ]);

            return $classified;
        } catch (\Throwable $e) {
            Log::warning('Event classification failed, using fallback', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);

            $fallback = new ClassifiedEvent(
                category: EventCategory::Other->value,
                tags: [],
                confidence: 0.0,
            );

            $event->update([
                'category' => $fallback->category,
                'tags' => $fallback->tags,
                'is_classified' => true,
            ]);

            return $fallback;
        }
    }

    /**
     * Classify a batch of events sequentially.
     *
     * @param  Collection<int, Event>  $events
     * @return Collection<int, ClassifiedEvent>
     */
    public function classifyBatch(Collection $events): Collection
    {
        return $events->map(fn (Event $event) => $this->classify($event));
    }

    /**
     * Build the user message containing event details for classification.
     */
    private function buildUserMessage(Event $event): string
    {
        $parts = ["Title: {$event->title}"];

        if ($event->description) {
            $description = mb_substr($event->description, 0, 1000);
            $parts[] = "Description: {$description}";
        }

        if ($event->venue) {
            $parts[] = "Venue: {$event->venue}";
        }

        if ($event->city) {
            $parts[] = "City: {$event->city}";
        }

        if (! empty($event->tags)) {
            $parts[] = 'Source Tags: '.implode(', ', $event->tags);
        }

        return implode("\n", $parts);
    }

    /**
     * Parse and validate the JSON response from Claude.
     *
     * Extracts category, tags, and confidence from the response text.
     * Handles cases where Claude wraps JSON in markdown code blocks.
     *
     * @throws \JsonException If the response is not valid JSON.
     * @throws \InvalidArgumentException If required fields are missing or invalid.
     */
    private function parseResponse(string $responseText): ClassifiedEvent
    {
        $json = $this->extractJson($responseText);

        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($data) || ! isset($data['category'])) {
            throw new \InvalidArgumentException('Response missing required "category" field');
        }

        $category = $this->resolveCategory($data['category']);

        $tags = $this->resolveTags($data['tags'] ?? []);

        $confidence = $this->resolveConfidence($data['confidence'] ?? 0.5);

        return new ClassifiedEvent(
            category: $category,
            tags: $tags,
            confidence: $confidence,
        );
    }

    /**
     * Extract JSON from response text, handling markdown code blocks.
     */
    private function extractJson(string $text): string
    {
        $text = trim($text);

        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $text, $matches)) {
            return $matches[1];
        }

        if (str_starts_with($text, '{')) {
            return $text;
        }

        throw new \InvalidArgumentException('No JSON found in response');
    }

    /**
     * Resolve the category string to a valid EventCategory value.
     *
     * Handles case-insensitive matching since Claude may return "Music" while
     * our enum uses "music".
     */
    private function resolveCategory(mixed $category): string
    {
        if (! is_string($category)) {
            return EventCategory::Other->value;
        }

        $normalized = mb_strtolower(trim($category));

        $match = EventCategory::tryFrom($normalized);

        if ($match !== null) {
            return $match->value;
        }

        // Try matching by enum case name (e.g., "Music" -> EventCategory::Music)
        foreach (EventCategory::cases() as $case) {
            if (mb_strtolower($case->name) === $normalized) {
                return $case->value;
            }
        }

        Log::info('Unknown category from LLM, falling back to Other', [
            'raw_category' => $category,
        ]);

        return EventCategory::Other->value;
    }

    /**
     * Resolve tags to an array of lowercase trimmed strings.
     */
    private function resolveTags(mixed $tags): array
    {
        if (! is_array($tags)) {
            return [];
        }

        return array_values(
            array_filter(
                array_map(
                    fn (mixed $tag) => is_string($tag) ? mb_strtolower(trim($tag)) : null,
                    $tags,
                ),
            ),
        );
    }

    /**
     * Resolve confidence to a float clamped to [0.0, 1.0].
     */
    private function resolveConfidence(mixed $confidence): float
    {
        if (! is_numeric($confidence)) {
            return 0.5;
        }

        return max(0.0, min(1.0, (float) $confidence));
    }
}
