<?php

declare(strict_types=1);

namespace App\Services\Processing;

use App\DTOs\RawEvent;
use App\Enums\EventCategory;
use App\Jobs\ClassifyEventJob;
use App\Jobs\DownloadEventImageJob;
use App\Models\Event;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class EventPipeline
{
    public function __construct(
        private readonly EventDeduplicator $deduplicator,
    ) {}

    /**
     * Process a single raw event: deduplicate, persist, and return the Event.
     * Returns null if the event is a duplicate.
     */
    public function process(RawEvent $rawEvent): ?Event
    {
        $fingerprint = $this->deduplicator->generateFingerprint($rawEvent);

        if ($this->deduplicator->isDuplicate($fingerprint)) {
            Log::debug('EventPipeline: exact duplicate skipped', ['title' => $rawEvent->title]);

            return null;
        }

        if ($this->deduplicator->findFuzzyDuplicates($rawEvent) !== null) {
            Log::debug('EventPipeline: fuzzy duplicate skipped', ['title' => $rawEvent->title]);

            return null;
        }

        // Recurring events share the same source_url across different dates.
        // If this URL already exists (different fingerprint, same URL), skip it.
        if (Event::where('source_url', $rawEvent->sourceUrl)->exists()) {
            Log::debug('EventPipeline: source_url already exists, skipping', ['url' => $rawEvent->sourceUrl]);

            return null;
        }

        $attributes = [
            'title' => $rawEvent->title,
            'description' => $rawEvent->description,
            'source' => $rawEvent->source,
            'source_url' => $rawEvent->sourceUrl,
            'source_id' => $rawEvent->sourceId,
            'fingerprint' => $fingerprint,
            'category' => EventCategory::Other,
            'tags' => [],
            'venue' => $rawEvent->venue,
            'address' => $rawEvent->address,
            'city' => $rawEvent->city,
            'starts_at' => $rawEvent->startsAt,
            'ends_at' => $rawEvent->endsAt,
            'price_min' => $rawEvent->priceMin,
            'price_max' => $rawEvent->priceMax,
            'currency' => $rawEvent->currency ?? 'RON',
            'is_free' => $rawEvent->isFree ?? false,
            'image_url' => $rawEvent->imageUrl,
            'metadata' => $rawEvent->metadata,
            'is_classified' => false,
            'is_geocoded' => false,
            'is_enriched' => false,
        ];

        // Wrap in withoutSyncingToSearch so a missing/offline Meilisearch instance
        // never blocks saving. Scout import can populate the index in a separate step.
        /** @var Event $event */
        $event = Event::withoutSyncingToSearch(fn () => Event::create($attributes));

        Log::info('EventPipeline: saved event', [
            'title' => $event->title,
            'source' => $event->source,
            'starts_at' => $event->getRawOriginal('starts_at'),
        ]);

        ClassifyEventJob::dispatch($event->id);

        if ($rawEvent->imageUrl !== null) {
            DownloadEventImageJob::dispatch($event);
        }

        return $event;
    }

    /**
     * Process a batch of raw events. Failures for individual events are logged
     * but do not halt the batch.
     *
     * @param  Collection<int, RawEvent>  $rawEvents
     * @return Collection<int, Event>
     */
    public function processBatch(Collection $rawEvents): Collection
    {
        $results = collect();
        $total = $rawEvents->count();

        foreach ($rawEvents as $rawEvent) {
            try {
                $event = $this->process($rawEvent);
                if ($event !== null) {
                    $results->push($event);
                }
            } catch (\Throwable $e) {
                Log::error('EventPipeline: failed to process event', [
                    'title' => $rawEvent->title,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info("EventPipeline: batch complete — {$results->count()}/{$total} events saved");

        return $results;
    }
}
