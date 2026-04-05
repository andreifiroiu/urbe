<?php

declare(strict_types=1);

namespace App\Services\Scraping;

use App\Contracts\ScraperAdapter;
use App\DTOs\RawEvent;
use App\Jobs\RunScraperJob;
use App\Models\ScraperRun;
use App\Services\Scraping\Adapters\GenericHtmlScraper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ScraperOrchestrator
{
    /**
     * Maps config source keys to their concrete adapter class names.
     * Add new entries here as each adapter is implemented.
     *
     * @var array<string, class-string<ScraperAdapter>>
     */
    public const array ADAPTER_REGISTRY = [
        'generic_html' => GenericHtmlScraper::class,
    ];

    /**
     * @param  array<int, ScraperAdapter>  $adapters
     */
    public function __construct(
        private readonly array $adapters = [],
    ) {}

    /**
     * Dispatch one RunScraperJob per enabled source that has a registered adapter.
     */
    public function runAll(): void
    {
        /** @var array<string, array{enabled: bool, base_url: string, interval_hours: int}> $sources */
        $sources = config('eventpulse.scrapers.sources', []);

        foreach ($sources as $key => $cfg) {
            if (! $cfg['enabled']) {
                continue;
            }

            if (! isset(self::ADAPTER_REGISTRY[$key])) {
                continue;
            }

            RunScraperJob::dispatch($key);
        }
    }

    /**
     * Run a single scraper identified by its source name and return scraped events.
     *
     * @return Collection<int, RawEvent>
     *
     * @throws \InvalidArgumentException If no adapter supports the given source.
     */
    public function runSource(string $source): Collection
    {
        $adapter = $this->getAdapterForSource($source);

        if ($adapter === null) {
            throw new \InvalidArgumentException("No adapter found for source: {$source}");
        }

        $run = ScraperRun::create([
            'source' => $source,
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $events = $adapter->scrape();

            $run->update([
                'status' => 'completed',
                'events_found' => $events->count(),
                'finished_at' => now(),
            ]);

            return $events;
        } catch (\Throwable $e) {
            Log::error("Scraper failed for source: {$source}", [
                'error' => $e->getMessage(),
            ]);

            $run->update([
                'status' => 'failed',
                'errors_count' => 1,
                'error_log' => [$e->getMessage()],
                'finished_at' => now(),
            ]);

            $this->alertIfConsecutiveFailuresExceedThreshold($source);

            return collect();
        }
    }

    /**
     * Find the adapter that supports the given source name.
     */
    public function getAdapterForSource(string $source): ?ScraperAdapter
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter->supports($source)) {
                return $adapter;
            }
        }

        return null;
    }

    private function alertIfConsecutiveFailuresExceedThreshold(string $source): void
    {
        $threshold = (int) config('eventpulse.scraping.max_consecutive_failures', 3);

        $consecutiveFailures = ScraperRun::where('source', $source)
            ->latest('started_at')
            ->take($threshold)
            ->get()
            ->every(fn (ScraperRun $run): bool => $run->status === 'failed');

        if ($consecutiveFailures) {
            Log::critical("Scraper source '{$source}' has failed {$threshold} consecutive times.", [
                'source' => $source,
            ]);
        }
    }
}
