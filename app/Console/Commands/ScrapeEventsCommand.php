<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Scraping\ScraperOrchestrator;
use Illuminate\Console\Command;

class ScrapeEventsCommand extends Command
{
    protected $signature = 'eventpulse:scrape
        {--city= : Run scrapers for a specific city key (default: all cities)}
        {--source= : Run a specific adapter key within the city (requires --city)}';

    protected $description = 'Run event scrapers to fetch new events from configured sources';

    public function handle(ScraperOrchestrator $orchestrator): int
    {
        $city = $this->option('city');
        $source = $this->option('source');

        if (is_string($city) && is_string($source)) {
            $this->info("Running scraper for {$source}@{$city}...");
            $events = $orchestrator->runSource($city, $source);
            $this->info("Scraped {$events->count()} raw events.");
        } elseif (is_string($city)) {
            $this->info("Dispatching scraper jobs for city: {$city}...");
            $orchestrator->runCity($city);
            $this->info('Scraper jobs dispatched.');
        } else {
            $this->info('Dispatching scraper jobs for all cities...');
            $orchestrator->runAll();
            $this->info('Scraper jobs dispatched.');
        }

        return self::SUCCESS;
    }
}
