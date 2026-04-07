<?php

declare(strict_types=1);

namespace App\Services\Scraping\Adapters;

use App\DTOs\RawEvent;
use Carbon\Carbon;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Log;

class TimisoreniScraper extends AbstractHtmlScraper
{
    private const string SOURCE = 'timisoreni';

    private const string BASE_URL = 'https://www.timisoreni.ro';

    public function adapterKey(): string
    {
        return self::SOURCE;
    }

    public function sourceIdentifier(array $sourceConfig): string
    {
        return self::SOURCE.'@timisoreni.ro';
    }

    /**
     * Timisoreni uses /{N}.htm suffix for pages beyond the first.
     * e.g. https://www.timisoreni.ro/info/index/t--evenimente/2.htm
     */
    protected function buildPageUrl(string $baseUrl, int $page): string
    {
        if ($page === 1) {
            return $baseUrl;
        }

        return rtrim($baseUrl, '/').'/'.$page.'.htm';
    }

    public function scrape(array $sourceConfig, array $cityConfig, callable $onEvent): void
    {
        $urls = array_merge([$this->getUrl($sourceConfig)], $this->getExtraUrls($sourceConfig));
        $maxPages = min((int) config('eventpulse.scrapers.max_pages', 10), 5);
        $cityLabel = $cityConfig['label'];

        /** @var array<string, true> $seen — keyed by "sourceUrl|startsAt" */
        $seen = [];
        $emitted = 0;

        Log::debug('TimisoreniScraper: starting scrape', [
            'urls' => $urls,
            'max_pages' => $maxPages,
        ]);

        foreach ($urls as $baseUrl) {
            for ($page = 1; $page <= $maxPages; $page++) {
                $html = $this->fetchPage($this->buildPageUrl($baseUrl, $page));

                if ($html === '') {
                    break;
                }

                $events = $this->parseEventsFromHtml($html, $cityLabel);

                Log::debug('TimisoreniScraper: page parsed', [
                    'url' => $baseUrl,
                    'page' => $page,
                    'events_found' => count($events),
                ]);

                if ($events === []) {
                    break;
                }

                foreach ($events as $event) {
                    $key = $event->sourceUrl.'|'.($event->startsAt ?? '');
                    if (isset($seen[$key])) {
                        continue;
                    }

                    $seen[$key] = true;
                    $onEvent($event);
                    $emitted++;
                }
            }
        }

        Log::info('TimisoreniScraper: scrape complete', ['emitted' => $emitted]);
    }

    /**
     * Parse all events from a page's HTML.
     *
     * Each page has N event cards (`ul.itemc`) and N corresponding `div.grid-view`
     * blocks with the date/time/venue table. The two lists are positionally correlated.
     * One card can produce multiple RawEvents (one per performance date row).
     *
     * @return list<RawEvent>
     */
    private function parseEventsFromHtml(string $html, string $cityLabel): array
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        $xpath = new DOMXPath($dom);

        $cardNodes = $xpath->query('//ul[contains(@itemtype,"schema.org/Event")]');
        $gridNodes = $xpath->query('//div[contains(@class,"grid-view")]');

        if ($cardNodes === false || $cardNodes->length === 0) {
            return [];
        }

        $grids = $gridNodes !== false ? iterator_to_array($gridNodes) : [];

        $events = [];

        foreach ($cardNodes as $i => $card) {
            if (! $card instanceof DOMElement) {
                continue;
            }

            /** @var DOMElement|null $grid */
            $grid = $grids[$i] ?? null;

            $cardEvents = $this->parseCard($card, $grid, $cityLabel, $xpath);
            array_push($events, ...$cardEvents);
        }

        return $events;
    }

    /**
     * Parse a single event card with its associated date/venue grid into RawEvents.
     *
     * @return list<RawEvent>
     */
    private function parseCard(DOMElement $card, ?DOMElement $grid, string $cityLabel, DOMXPath $xpath): array
    {
        // Title — from h3 > a[itemprop="url"] > span[itemprop="name"]
        $titleNodes = $xpath->query('.//h3//span[@itemprop="name"]', $card);
        $title = $titleNodes !== false && $titleNodes->length > 0
            ? trim($titleNodes->item(0)->textContent)
            : '';

        if ($title === '') {
            return [];
        }

        // Source URL — relative path, needs host prefix
        $urlNodes = $xpath->query('.//h3//a[@itemprop="url"]', $card);
        $relativeUrl = $urlNodes !== false && $urlNodes->length > 0
            ? trim(($urlNodes->item(0) instanceof DOMElement ? $urlNodes->item(0)->getAttribute('href') : ''))
            : '';

        if ($relativeUrl === '') {
            return [];
        }

        $sourceUrl = $this->absoluteUrl($relativeUrl);
        $sourceId = $this->extractSlug($relativeUrl);

        // Description — li.text[itemprop="description"]
        $descNodes = $xpath->query('.//li[@itemprop="description"]', $card);
        $description = null;
        if ($descNodes !== false && $descNodes->length > 0) {
            $raw = trim($descNodes->item(0)->textContent);
            $description = $raw !== '' ? $raw : null;
        }

        // Image — img[itemprop="image"]
        $imgNodes = $xpath->query('.//*[@itemprop="image"]', $card);
        $imageUrl = null;
        if ($imgNodes !== false && $imgNodes->length > 0) {
            $imgNode = $imgNodes->item(0);
            if ($imgNode instanceof DOMElement) {
                $src = $imgNode->getAttribute('src');
                $imageUrl = $src !== '' ? $this->absoluteUrl($src) : null;
            }
        }

        if ($grid === null) {
            // No date table — emit one event with no date/venue data
            return [new RawEvent(
                title: $title,
                description: $description,
                sourceUrl: $sourceUrl,
                sourceId: $sourceId,
                source: self::SOURCE,
                venue: null,
                address: null,
                city: $cityLabel,
                startsAt: null,
                endsAt: null,
                priceMin: null,
                priceMax: null,
                currency: null,
                isFree: false,
                imageUrl: $imageUrl,
                metadata: [],
            )];
        }

        // Date rows — one RawEvent per <tr>
        $rows = $xpath->query('.//tbody/tr', $grid);

        if ($rows === false || $rows->length === 0) {
            return [];
        }

        $events = [];

        foreach ($rows as $row) {
            if (! $row instanceof DOMElement) {
                continue;
            }

            $event = $this->parseRow($row, $xpath, $title, $description, $sourceUrl, $sourceId, $imageUrl, $cityLabel);
            if ($event !== null) {
                $events[] = $event;
            }
        }

        return $events;
    }

    /**
     * Parse a single date/venue table row into a RawEvent.
     */
    private function parseRow(
        DOMElement $row,
        DOMXPath $xpath,
        string $title,
        ?string $description,
        string $sourceUrl,
        ?string $sourceId,
        ?string $imageUrl,
        string $cityLabel,
    ): ?RawEvent {
        $cells = $row->getElementsByTagName('td');

        if ($cells->length < 2) {
            return null;
        }

        // Cell 0: start date (and optional end date)
        $cell0 = $cells->item(0);
        $startDateContent = '';
        $endDateContent = '';

        if ($cell0 instanceof DOMElement) {
            $startSpan = $xpath->query('./span[@itemprop="startDate"]', $cell0);
            if ($startSpan !== false && $startSpan->length > 0 && $startSpan->item(0) instanceof DOMElement) {
                $startDateContent = $startSpan->item(0)->getAttribute('content');
            }

            $endSpan = $xpath->query('./span[@itemprop="endDate"]', $cell0);
            if ($endSpan !== false && $endSpan->length > 0 && $endSpan->item(0) instanceof DOMElement) {
                $endDateContent = $endSpan->item(0)->getAttribute('content');
            }
        }

        if ($startDateContent === '') {
            return null;
        }

        // Cell 1: time string "19:00"
        $timeStr = trim($cells->item(1)->textContent);

        $startsAt = $this->combineDateAndTime($startDateContent, $timeStr);
        $endsAt = $endDateContent !== '' ? $this->parseDateContent($endDateContent) : null;

        // Cell 2: venue and address
        $venue = null;
        $address = null;
        $cell2 = $cells->item(2);

        if ($cell2 instanceof DOMElement) {
            $venueSpan = $xpath->query('.//span[@itemprop="name"]', $cell2);
            if ($venueSpan !== false && $venueSpan->length > 0) {
                $venueName = trim($venueSpan->item(0)->textContent);
                $venue = $venueName !== '' ? $venueName : null;
            }

            $addressMeta = $xpath->query('.//meta[@itemprop="address"]', $cell2);
            if ($addressMeta !== false && $addressMeta->length > 0 && $addressMeta->item(0) instanceof DOMElement) {
                $addr = $addressMeta->item(0)->getAttribute('content');
                $address = $addr !== '' ? $addr : null;
            }
        }

        Log::debug('TimisoreniScraper: parsed event row', [
            'title' => $title,
            'starts_at' => $startsAt,
            'venue' => $venue,
        ]);

        return new RawEvent(
            title: $title,
            description: $description,
            sourceUrl: $sourceUrl,
            sourceId: $sourceId,
            source: self::SOURCE,
            venue: $venue,
            address: $address,
            city: $cityLabel,
            startsAt: $startsAt,
            endsAt: $endsAt,
            priceMin: null,
            priceMax: null,
            currency: null,
            isFree: false,
            imageUrl: $imageUrl,
            metadata: [],
        );
    }

    /**
     * Combine an ISO 8601 date string (midnight) with a "HH:MM" time string.
     *
     * Input: "2026-04-07T00:00:00+03:00" + "19:00"
     * Output: "2026-04-07 16:00:00" (UTC)
     */
    private function combineDateAndTime(string $dateContent, string $timeStr): ?string
    {
        if (! preg_match('/^(\d{4}-\d{2}-\d{2})T\d{2}:\d{2}:\d{2}([+-]\d{2}:\d{2})$/', $dateContent, $m)) {
            return $this->parseDateContent($dateContent);
        }

        if (preg_match('/^\d{2}:\d{2}$/', $timeStr)) {
            $combined = $m[1].'T'.$timeStr.':00'.$m[2];
        } else {
            $combined = $m[1].'T00:00:00'.$m[2];
        }

        try {
            return Carbon::parse($combined)->utc()->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Parse an ISO 8601 date content attribute to a UTC datetime string.
     */
    private function parseDateContent(string $dateContent): ?string
    {
        if ($dateContent === '') {
            return null;
        }

        try {
            return Carbon::parse($dateContent)->utc()->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Prepend the site host to a relative path.
     */
    private function absoluteUrl(string $path): string
    {
        if (str_starts_with($path, 'http')) {
            return $path;
        }

        return self::BASE_URL.'/'.ltrim($path, '/');
    }

    /**
     * Extract the slug segment from a relative URL like "/despre/concert-bosquito/".
     */
    private function extractSlug(string $relativeUrl): ?string
    {
        $path = rtrim($relativeUrl, '/');
        $slug = basename($path);

        return $slug !== '' ? $slug : null;
    }
}
