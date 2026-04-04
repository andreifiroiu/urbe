<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Scraping\ScraperOrchestrator;
use Illuminate\Console\Command;

class ScrapeEventsCommand extends Command
{
    protected $signature = 'eventpulse:scrape {--source= : Run a specific scraper source}';

    protected $description = 'Run event scrapers to fetch new events from configured sources';

    public function handle(ScraperOrchestrator $orchestrator): int
    {
        $source = $this->option('source');

        if ($source) {
            $this->info("Running scraper for source: {$source}");
            $events = $orchestrator->runSource($source);
        } else {
            $this->info('Running all scrapers...');
            $events = $orchestrator->runAll();
        }

        $this->info("Scraped {$events->count()} raw events.");

        return self::SUCCESS;
    }
}
