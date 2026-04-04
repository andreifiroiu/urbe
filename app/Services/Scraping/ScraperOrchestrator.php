<?php

declare(strict_types=1);

namespace App\Services\Scraping;

use App\Contracts\ScraperAdapter;
use App\DTOs\RawEvent;
use App\Models\ScraperRun;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ScraperOrchestrator
{
    /**
     * Orchestrates the execution of all registered scraper adapters.
     *
     * Manages scraper lifecycle (start, run, record results) and handles
     * failures gracefully so that one broken scraper never crashes the
     * entire pipeline.
     *
     * @param array<int, ScraperAdapter> $adapters
     */
    public function __construct(
        private readonly array $adapters = [],
    ) {}

    /**
     * Run all registered scrapers and return aggregated raw events.
     *
     * Iterates through every adapter, creates a ScraperRun audit record for
     * each, invokes the scrape method, and collects all resulting RawEvent
     * DTOs into a single collection. Failures are caught per-adapter so one
     * broken source does not block the rest.
     *
     * @return Collection<int, RawEvent>
     */
    public function runAll(): Collection
    {
        // TODO: Initialize an empty collection to accumulate RawEvents
        // TODO: Iterate through all $this->adapters
        // TODO: For each adapter, create a ScraperRun record with status='running' and started_at=now()
        // TODO: Call adapter->scrape() inside a try/catch block
        // TODO: On success: update ScraperRun with events_found count, status='completed', finished_at=now()
        // TODO: On failure: catch \Throwable, log the error with adapter source name
        // TODO:   Update ScraperRun with status='failed', errors_count++, error_log with exception message
        // TODO:   Check consecutive failure count for this source using ScraperRun::where('source', ...)->latest()->take(threshold)
        // TODO:   If consecutive failures > config('eventpulse.scraper.max_consecutive_failures'), log a critical alert
        // TODO: Merge successful results into the accumulator collection
        // TODO: Return the accumulated RawEvent collection
        return collect();
    }

    /**
     * Run a single scraper identified by its source name.
     *
     * Looks up the adapter that supports the given source string, executes
     * it with the same error-handling semantics as runAll(), and returns
     * the scraped raw events.
     *
     * @return Collection<int, RawEvent>
     *
     * @throws \InvalidArgumentException If no adapter supports the given source.
     */
    public function runSource(string $source): Collection
    {
        // TODO: Call getAdapterForSource($source)
        // TODO: If null, throw \InvalidArgumentException("No adapter found for source: {$source}")
        // TODO: Create a ScraperRun record with status='running' and started_at=now()
        // TODO: Call adapter->scrape() inside try/catch
        // TODO: On success: update ScraperRun with results, return scraped RawEvents
        // TODO: On failure: update ScraperRun as failed, log error, return empty collection
        return collect();
    }

    /**
     * Find the adapter that supports the given source name.
     *
     * Iterates through registered adapters and returns the first one whose
     * supports() method returns true for the given source string.
     */
    public function getAdapterForSource(string $source): ?ScraperAdapter
    {
        // TODO: Loop through $this->adapters
        // TODO: Return the first adapter where $adapter->supports($source) === true
        // TODO: Return null if no adapter matches
        return null;
    }
}
