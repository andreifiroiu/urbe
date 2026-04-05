<?php

declare(strict_types=1);

namespace App\Services\Scraping\Adapters;

use App\DTOs\RawEvent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class GenericHtmlScraper extends AbstractHtmlScraper
{
    /**
     * A generic HTML scraper that extracts event data from configured web pages.
     *
     * Extend AbstractHtmlScraper and implement site-specific selectors in scrape().
     */
    public function adapterKey(): string
    {
        return 'generic_html';
    }

    public function sourceIdentifier(array $sourceConfig): string
    {
        $host = (string) parse_url($sourceConfig['url'], PHP_URL_HOST);

        return 'generic_html@'.$host;
    }

    /**
     * Scrape configured HTML pages and return a collection of RawEvent DTOs.
     *
     * @param  array{adapter: string, url: string, extra_urls?: list<string>, enabled: bool, interval_hours: int}  $sourceConfig
     * @param  array{label: string, timezone: string, coordinates: list<float>, radius_km: int}  $cityConfig
     * @return Collection<int, RawEvent>
     */
    public function scrape(array $sourceConfig, array $cityConfig): Collection
    {
        // TODO: Read page definitions from config('eventpulse.scrapers.generic_html.pages')
        // TODO: For each page definition, fetchPage(), parse DOM, extract event containers
        // TODO: Map each container to a RawEvent DTO using configured CSS/XPath selectors
        Log::info('GenericHtmlScraper: scrape() not yet implemented');

        return collect();
    }
}
