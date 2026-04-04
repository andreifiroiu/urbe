<?php

declare(strict_types=1);

namespace App\Services\Processing;

use App\DTOs\RawEvent;
use App\Models\Event;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class EventPipeline
{
    /**
     * Orchestrates the full event processing pipeline.
     *
     * Takes raw scraped events through deduplication, model creation,
     * AI classification, and geocoding/metadata enrichment. Each stage
     * is handled by a dedicated service to keep responsibilities clean.
     */
    public function __construct(
        private readonly EventDeduplicator $deduplicator,
        private readonly EventClassifier $classifier,
        private readonly EventEnricher $enricher,
    ) {}

    /**
     * Process a single raw event through the full pipeline.
     *
     * Pipeline stages: fingerprint -> dedup check -> create Event model ->
     * classify with LLM -> enrich with geocoding/metadata -> save.
     *
     * Returns the created Event, or null if the event was a duplicate.
     */
    public function process(RawEvent $rawEvent): ?Event
    {
        // TODO: Generate fingerprint using $this->deduplicator->generateFingerprint($rawEvent)
        // TODO: Check exact duplicate: $this->deduplicator->isDuplicate($fingerprint)
        //   TODO: If duplicate, log debug message and return null
        // TODO: Check fuzzy duplicate: $this->deduplicator->findFuzzyDuplicates($rawEvent)
        //   TODO: If fuzzy match found, log info and return null (or merge data into existing event)
        // TODO: Create a new Event model from RawEvent data:
        //   TODO: Map RawEvent fields to Event columns (title, description, source, source_url, etc.)
        //   TODO: Set fingerprint
        //   TODO: Set is_classified = false, is_geocoded = false, is_enriched = false
        //   TODO: Save the initial Event record
        // TODO: Classify the event: $classified = $this->classifier->classify($event)
        //   TODO: Wrap in try/catch; on failure, log and leave unclassified
        //   TODO: On success: update $event->category, $event->tags, $event->is_classified = true
        //   TODO: Save the event
        // TODO: Enrich with geocoding: $this->enricher->enrichGeocoding($event)
        // TODO: Enrich with metadata: $this->enricher->enrichMetadata($event)
        // TODO: Log info: "Processed event: {title} [{category}] from {source}"
        // TODO: Return the fully processed Event
        return null;
    }

    /**
     * Process a batch of raw events through the pipeline.
     *
     * Iterates through each RawEvent, passing it to process(). Collects
     * all successfully created Events and returns them. Failures for
     * individual events are logged but do not halt the batch.
     *
     * @param Collection<int, RawEvent> $rawEvents
     * @return Collection<int, Event>
     */
    public function processBatch(Collection $rawEvents): Collection
    {
        // TODO: Initialize empty results collection
        // TODO: For each $rawEvent in the collection:
        //   TODO: Call $this->process($rawEvent) inside try/catch
        //   TODO: If result is not null, add to results
        //   TODO: On exception: log error with event title and exception message, continue
        // TODO: Log summary: "Batch complete: {created}/{total} events processed"
        // TODO: Return results collection
        return collect();
    }
}
