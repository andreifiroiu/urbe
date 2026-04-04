<?php

declare(strict_types=1);

namespace App\Services\Processing;

use App\DTOs\ClassifiedEvent;
use App\Enums\EventCategory;
use App\Models\Event;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class EventClassifier
{
    /**
     * Classifies events into categories and extracts tags using the Claude API.
     *
     * Sends event title and description to the LLM with a structured prompt
     * requesting JSON output containing category, tags, and confidence score.
     * Falls back to EventCategory::Other on classification failure.
     */
    public function __construct(
        private readonly HttpClient $http,
    ) {}

    /**
     * Classify a single event using the Claude API.
     *
     * Builds a prompt containing the event's title and description, sends it
     * to the Anthropic API, and parses the structured JSON response into a
     * ClassifiedEvent DTO.
     */
    public function classify(Event $event): ClassifiedEvent
    {
        // TODO: Build the system prompt from config('eventpulse.prompts.classification')
        // TODO: Build the user message with event title, description, venue, and any tags from source
        // TODO: Send request to Anthropic API:
        //   TODO: POST to https://api.anthropic.com/v1/messages
        //   TODO: Use config('eventpulse.anthropic.api_key') for authorization
        //   TODO: Use config('eventpulse.anthropic.model') for model selection
        //   TODO: Set max_tokens to a reasonable limit (e.g., 256)
        //   TODO: Include system prompt instructing JSON output with keys: category, tags, confidence
        // TODO: Parse the JSON response from Claude's content
        // TODO: Validate the response:
        //   TODO: Ensure 'category' is a valid EventCategory value
        //   TODO: Ensure 'tags' is an array of strings
        //   TODO: Ensure 'confidence' is a float between 0 and 1
        // TODO: If parsing or validation fails:
        //   TODO: Log warning with event ID and raw response
        //   TODO: Return ClassifiedEvent with category='other', empty tags, confidence=0.0
        // TODO: Log token usage to llm_usage_log table for cost tracking
        // TODO: Return the ClassifiedEvent DTO
        return new ClassifiedEvent(
            category: EventCategory::Other->value,
            tags: [],
            confidence: 0.0,
        );
    }

    /**
     * Classify a batch of events sequentially with rate limiting.
     *
     * Processes each event through classify() with a configurable delay
     * between calls to respect Anthropic API rate limits.
     *
     * @param Collection<int, Event> $events
     * @return Collection<int, ClassifiedEvent>
     */
    public function classifyBatch(Collection $events): Collection
    {
        // TODO: Get rate limit delay from config('eventpulse.anthropic.rate_limit_delay_ms', 500)
        // TODO: Initialize results collection
        // TODO: For each event in the collection:
        //   TODO: Call classify($event) inside try/catch
        //   TODO: On success: add ClassifiedEvent to results
        //   TODO: On failure: log error, add fallback ClassifiedEvent with category='other'
        //   TODO: Sleep for rate_limit_delay_ms between calls (usleep for millisecond precision)
        // TODO: Return results collection
        return collect();
    }
}
