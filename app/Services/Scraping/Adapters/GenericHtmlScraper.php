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
     * Configuration is read from config('eventpulse.scrapers.sources.generic_html').
     * Extend AbstractHtmlScraper and implement site-specific selectors in scrape().
     */
    public function source(): string
    {
        return 'generic_html';
    }

    protected function sourceUrl(): string
    {
        return (string) config('eventpulse.scrapers.sources.generic_html.base_url', '');
    }

    /**
     * Scrape configured HTML pages and return a collection of RawEvent DTOs.
     *
     * @return Collection<int, RawEvent>
     */
    public function scrape(): Collection
    {
        // TODO: Read page definitions from config('eventpulse.scrapers.generic_html.pages')
        // TODO: For each page definition, fetchPage(), parse DOM, extract event containers
        // TODO: Map each container to a RawEvent DTO using configured CSS/XPath selectors
        Log::info('GenericHtmlScraper: scrape() not yet implemented');

        return collect();
    }
}
