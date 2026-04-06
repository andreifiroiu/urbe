<?php

declare(strict_types=1);

namespace App\Services\Scraping\Adapters;

use App\DTOs\RawEvent;
use Carbon\Carbon;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Log;

class EntertixScraper extends AbstractHtmlScraper
{
    private const string SOURCE = 'entertix';

    public function adapterKey(): string
    {
        return self::SOURCE;
    }

    public function sourceIdentifier(array $sourceConfig): string
    {
        return self::SOURCE.'@entertix.ro';
    }

    /**
     * @param  array{adapter: string, url: string, city_filter?: string, extra_urls?: list<string>, enabled: bool, interval_hours: int}  $sourceConfig
     * @param  array{label: string, timezone: string, coordinates: list<float>, radius_km: int}  $cityConfig
     * @param  callable(RawEvent): void  $onEvent
     */
    public function scrape(array $sourceConfig, array $cityConfig, callable $onEvent): void
    {
        $url = $this->getUrl($sourceConfig);
        $cityFilter = $sourceConfig['city_filter'] ?? $cityConfig['label'];
        $cityLabel = $cityConfig['label'];
        $timezone = $cityConfig['timezone'];
        $html = $this->fetchPage($url);

        if ($html === '') {
            Log::warning('EntertixScraper: empty response', ['url' => $url]);

            return;
        }

        $emitted = 0;

        foreach ($this->parseEvents($html, $cityFilter, $cityLabel, $timezone) as $event) {
            $onEvent($event);
            $emitted++;
        }

        Log::info('EntertixScraper: scrape complete', ['emitted' => $emitted, 'url' => $url, 'city_filter' => $cityFilter]);
    }

    /**
     * Parse all event cards from the events listing page, keeping only those
     * whose venue/location text contains the city filter string.
     *
     * The page is server-rendered and contains all nationwide events. City
     * filtering is handled post-parse by matching against `eghtextwhen` text.
     *
     * @return list<RawEvent>
     */
    private function parseEvents(string $html, string $cityFilter, string $cityLabel, string $timezone): array
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        $xpath = new DOMXPath($dom);

        $cards = $xpath->query(
            '//div[contains(concat(" ",normalize-space(@class)," ")," egh ")]'
            .'[.//a[contains(concat(" ",normalize-space(@class)," ")," eghrowticketsbutton ")]]'
        );

        if ($cards === false || $cards->length === 0) {
            return [];
        }

        $events = [];

        foreach ($cards as $card) {
            if (! $card instanceof DOMElement) {
                continue;
            }

            $event = $this->parseCard($card, $xpath, $cityFilter, $cityLabel, $timezone);
            if ($event !== null) {
                $events[] = $event;
            }
        }

        return $events;
    }

    /**
     * Parse a single `div.egh` card into a RawEvent.
     *
     * Returns null when the card's location does not match the city filter,
     * or when required fields (title, sourceUrl) are missing.
     */
    private function parseCard(
        DOMElement $card,
        DOMXPath $xpath,
        string $cityFilter,
        string $cityLabel,
        string $timezone,
    ): ?RawEvent {
        // Title — div.eghtexttitle
        $titleNodes = $xpath->query(
            './/div[contains(concat(" ",normalize-space(@class)," ")," eghtexttitle ")]',
            $card
        );
        if ($titleNodes === false || $titleNodes->length === 0) {
            return null;
        }

        $rawTitle = trim($titleNodes->item(0)->textContent);
        if ($rawTitle === '') {
            return null;
        }

        [$title] = $this->splitTitleVenue($rawTitle);
        if ($title === '') {
            return null;
        }

        // Source URL — a.eghrowticketsbutton
        $linkNodes = $xpath->query(
            './/a[contains(concat(" ",normalize-space(@class)," ")," eghrowticketsbutton ")]',
            $card
        );
        if ($linkNodes === false || $linkNodes->length === 0) {
            return null;
        }

        $linkNode = $linkNodes->item(0);
        if (! $linkNode instanceof DOMElement) {
            return null;
        }

        $sourceUrl = $linkNode->getAttribute('href');
        if ($sourceUrl === '') {
            return null;
        }

        $sourceId = $this->extractEventId($sourceUrl);

        // eghtextwhen — venue, location, date range
        $whenNodes = $xpath->query(
            './/div[contains(concat(" ",normalize-space(@class)," ")," eghtextwhen ")]',
            $card
        );
        $venue = null;
        $startsAt = null;
        $whenText = '';

        if ($whenNodes !== false && $whenNodes->length > 0) {
            $whenText = trim($whenNodes->item(0)->textContent);
            [$venue, , $dateRange] = $this->parseWhenText($whenText);

            if ($dateRange !== null) {
                $startsAt = $this->parseDateRange($dateRange, $timezone);
            }
        }

        // City filter — skip cards whose eghtextwhen doesn't mention the city
        if (! str_contains($this->normalizeText($whenText), $this->normalizeText($cityFilter))) {
            return null;
        }

        Log::debug('EntertixScraper: parsed event', [
            'title' => $title,
            'venue' => $venue,
            'starts_at' => $startsAt,
        ]);

        return new RawEvent(
            title: $title,
            description: null,
            sourceUrl: $sourceUrl,
            sourceId: $sourceId,
            source: self::SOURCE,
            venue: $venue,
            address: null,
            city: $cityLabel,
            startsAt: $startsAt,
            endsAt: null,
            priceMin: null,
            priceMax: null,
            currency: null,
            isFree: null,
            imageUrl: null,
            metadata: ['category_hint' => 'Entertainment'],
        );
    }

    /**
     * Split a raw title like "Concert @Filarmonica Banatul" on the last `@`.
     *
     * @return array{string, ?string} [title, venueHint]
     */
    private function splitTitleVenue(string $raw): array
    {
        $pos = mb_strrpos($raw, '@');

        if ($pos === false) {
            return [trim($raw), null];
        }

        return [trim(mb_substr($raw, 0, $pos)), trim(mb_substr($raw, $pos + 1)) ?: null];
    }

    /**
     * Parse the `eghtextwhen` text into venue, location, and date range.
     *
     * Format: "VENUE, LOCATION, START - END YEAR"
     * The last comma-segment is the date range; the second-to-last is the location;
     * everything before is the venue (re-joined with ", ").
     *
     * @return array{?string, ?string, ?string} [venue, location, dateRange]
     */
    private function parseWhenText(string $when): array
    {
        $parts = explode(', ', $when);
        $count = count($parts);

        if ($count < 2) {
            return [null, null, null];
        }

        $dateRange = $parts[$count - 1];
        $location = $parts[$count - 2];
        $venue = $count > 2 ? implode(', ', array_slice($parts, 0, $count - 2)) : null;

        return [$venue, $location, $dateRange];
    }

    /**
     * Parse a date range like "20 mar - 30 apr 2026" and return the start date
     * as a UTC midnight datetime string.
     *
     * The year is extracted from the end part and propagated to the start part
     * when the start part lacks a year (the common case on Entertix).
     */
    private function parseDateRange(string $dateRange, string $timezone): ?string
    {
        $parts = array_map('trim', explode(' - ', $dateRange, 2));
        $startStr = $parts[0];
        $endStr = $parts[1] ?? $startStr;

        // Propagate 4-digit year from end string to start string when missing
        if (! preg_match('/\b(20\d{2})\b/', $startStr) && preg_match('/\b(20\d{2})\b/', $endStr, $m)) {
            $startStr .= ' '.$m[1];
        }

        $carbon = $this->parseRomanianDate($startStr);
        if ($carbon === null) {
            return null;
        }

        try {
            return Carbon::create($carbon->year, $carbon->month, $carbon->day, 0, 0, 0, $timezone)
                ->utc()
                ->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Extract the numeric event ID from a URL like
     * "https://www.entertix.ro/evenimente/35468/dino-egipt-...html".
     */
    private function extractEventId(string $url): ?string
    {
        if (preg_match('#/evenimente/(\d+)/#', $url, $m)) {
            return $m[1];
        }

        return null;
    }
}
