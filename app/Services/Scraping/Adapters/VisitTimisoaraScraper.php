<?php

declare(strict_types=1);

namespace App\Services\Scraping\Adapters;

use App\DTOs\RawEvent;
use Carbon\Carbon;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VisitTimisoaraScraper extends AbstractHtmlScraper
{
    private const string SOURCE = 'visit_timisoara';

    private const string BASE_URL = 'https://visit-timisoara.com';

    private const string API_PATH = '/wp-json/tribe/events/v1/events';

    public function adapterKey(): string
    {
        return self::SOURCE;
    }

    public function sourceIdentifier(array $sourceConfig): string
    {
        return self::SOURCE.'@visit-timisoara.com';
    }

    /**
     * @param  array{adapter: string, url: string, extra_urls?: list<string>, enabled: bool, interval_hours: int}  $sourceConfig
     * @param  array{label: string, timezone: string, coordinates: list<float>, radius_km: int}  $cityConfig
     * @param  callable(RawEvent): void  $onEvent
     */
    public function scrape(array $sourceConfig, array $cityConfig, callable $onEvent): void
    {
        $cityLabel = $cityConfig['label'];
        $apiUrl = self::BASE_URL.self::API_PATH;

        $events = $this->tryApiPath($apiUrl, $cityLabel)
            ?? $this->tryBrowsershotPath($sourceConfig['url'], $cityLabel);

        $emitted = 0;

        foreach ($events as $event) {
            $onEvent($event);
            $emitted++;
        }

        Log::info('VisitTimisoaraScraper: scrape complete', ['emitted' => $emitted]);
    }

    /**
     * Attempt to fetch events via The Events Calendar REST API.
     *
     * Returns a list of RawEvents on success, or null when the API is unavailable
     * or does not expose TEC endpoints (so the caller can fall back to Browsershot).
     * Returns an empty list when the API works but has no events.
     *
     * @return list<RawEvent>|null
     */
    private function tryApiPath(string $apiUrl, string $cityLabel): ?array
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
            ])->get($apiUrl, ['page' => 1, 'per_page' => 50]);

            if ($response->status() !== 200) {
                Log::debug('VisitTimisoaraScraper: API unavailable', ['status' => $response->status()]);

                return null;
            }

            $body = $response->json();

            if (! is_array($body) || ! array_key_exists('events', $body)) {
                Log::debug('VisitTimisoaraScraper: API response missing events key, falling back to Browsershot');

                return null;
            }

            if (! is_array($body['events']) || $body['events'] === []) {
                return [];
            }

            $events = [];
            $maxPages = (int) config('eventpulse.scrapers.max_pages', 10);
            $page = 1;

            foreach ($body['events'] as $raw) {
                if (! is_array($raw)) {
                    continue;
                }

                $event = $this->mapApiEvent($raw, $cityLabel);
                if ($event !== null) {
                    $events[] = $event;
                }
            }

            while (is_string($body['next_rest_url'] ?? null) && $page < $maxPages) {
                $page++;
                $nextUrl = (string) $body['next_rest_url'];
                $nextResponse = Http::withHeaders(['Accept' => 'application/json'])->get($nextUrl);

                if ($nextResponse->status() !== 200) {
                    break;
                }

                $body = $nextResponse->json();

                if (! is_array($body) || ! is_array($body['events'] ?? null)) {
                    break;
                }

                foreach ($body['events'] as $raw) {
                    if (! is_array($raw)) {
                        continue;
                    }

                    $event = $this->mapApiEvent($raw, $cityLabel);
                    if ($event !== null) {
                        $events[] = $event;
                    }
                }
            }

            Log::info('VisitTimisoaraScraper: API succeeded', ['count' => count($events)]);

            return $events;
        } catch (\Throwable $e) {
            Log::debug('VisitTimisoaraScraper: API exception, falling back to Browsershot', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Map a single TEC REST API event object to a RawEvent.
     *
     * @param  array<string, mixed>  $raw
     */
    private function mapApiEvent(array $raw, string $cityLabel): ?RawEvent
    {
        $title = trim(strip_tags((string) ($raw['title'] ?? '')));
        if ($title === '') {
            return null;
        }

        $sourceUrl = trim((string) ($raw['url'] ?? ''));
        if ($sourceUrl === '') {
            return null;
        }

        $sourceId = isset($raw['id']) ? (string) $raw['id'] : null;

        // TEC provides pre-computed UTC dates — prefer them over local + timezone math
        $startsAt = $this->parseTecDatetime((string) ($raw['utc_start_date'] ?? ''));
        $endsAt = isset($raw['utc_end_date']) ? $this->parseTecDatetime((string) $raw['utc_end_date']) : null;

        $imageUrl = null;
        if (is_array($raw['image'] ?? null)) {
            $imageUrl = trim((string) ($raw['image']['url'] ?? '')) ?: null;
        }

        $venue = null;
        $address = null;
        if (is_array($raw['venue'] ?? null)) {
            $venue = trim((string) ($raw['venue']['venue'] ?? '')) ?: null;
            $address = trim((string) ($raw['venue']['address'] ?? '')) ?: null;
        }

        $cost = $this->parseCost(trim((string) ($raw['cost'] ?? '')));

        $categoryHint = null;
        if (is_array($raw['categories'] ?? null) && $raw['categories'] !== []) {
            $first = $raw['categories'][0];
            $categoryHint = is_array($first) ? (trim((string) ($first['name'] ?? '')) ?: null) : null;
        }

        $tags = [];
        if (is_array($raw['tags'] ?? null)) {
            foreach ($raw['tags'] as $tag) {
                if (is_array($tag) && isset($tag['name'])) {
                    $name = trim((string) $tag['name']);
                    if ($name !== '') {
                        $tags[] = $name;
                    }
                }
            }
        }

        $description = null;
        if (isset($raw['description'])) {
            $stripped = $this->stripHtml((string) $raw['description']);
            $description = $stripped !== '' ? $stripped : null;
        }

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
            priceMin: $cost['priceMin'],
            priceMax: $cost['priceMax'],
            currency: $cost['priceMin'] !== null ? 'RON' : null,
            isFree: $cost['isFree'],
            imageUrl: $imageUrl,
            metadata: [
                'category_hint' => $categoryHint,
                'tags' => $tags,
            ],
        );
    }

    /**
     * Fetch events by rendering the listing page with Browsershot.
     *
     * Paginates via the `tribe-events-nav-next` link present in the rendered HTML.
     *
     * @return list<RawEvent>
     */
    private function tryBrowsershotPath(string $listingUrl, string $cityLabel): array
    {
        $events = [];
        $maxPages = (int) config('eventpulse.scrapers.max_pages', 10);
        $page = 0;
        $url = $listingUrl;

        while ($url !== '' && $page < $maxPages) {
            $html = $this->fetchWithBrowsershot($url);
            $page++;

            if ($html === '') {
                Log::warning('VisitTimisoaraScraper: empty Browsershot response', ['url' => $url]);
                break;
            }

            ['events' => $pageEvents, 'nextUrl' => $nextUrl] = $this->parseBrowsershotHtml($html, $cityLabel);

            foreach ($pageEvents as $event) {
                $events[] = $event;
            }

            $url = $nextUrl ?? '';
        }

        return $events;
    }

    /**
     * Parse a Browsershot-rendered HTML page into events and an optional next-page URL.
     *
     * @return array{events: list<RawEvent>, nextUrl: ?string}
     */
    private function parseBrowsershotHtml(string $html, string $cityLabel): array
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        $xpath = new DOMXPath($dom);

        $articles = $xpath->query('//article[contains(@class,"tribe_events")]');

        $events = [];

        if ($articles !== false) {
            foreach ($articles as $article) {
                if (! $article instanceof DOMElement) {
                    continue;
                }

                $event = $this->parseSingleArticle($article, $xpath, $cityLabel);
                if ($event !== null) {
                    $events[] = $event;
                }
            }
        }

        $nextUrl = null;
        $nextLinks = $xpath->query('//a[contains(@class,"tribe-events-nav-next")]/@href');
        if ($nextLinks !== false && $nextLinks->length > 0) {
            $raw = trim($nextLinks->item(0)->nodeValue);
            $nextUrl = $raw !== '' ? $this->absoluteUrl($raw) : null;
        }

        return ['events' => $events, 'nextUrl' => $nextUrl];
    }

    /**
     * Parse a single `<article>` TEC card into a RawEvent.
     */
    private function parseSingleArticle(DOMElement $article, DOMXPath $xpath, string $cityLabel): ?RawEvent
    {
        // Title and source URL from the heading link
        $titleLinks = $xpath->query(
            './/h2[contains(@class,"tribe-events-list-event-title")]//a',
            $article
        );

        if ($titleLinks === false || $titleLinks->length === 0) {
            return null;
        }

        $titleLink = $titleLinks->item(0);
        if (! $titleLink instanceof DOMElement) {
            return null;
        }

        $title = trim($titleLink->textContent);
        if ($title === '') {
            return null;
        }

        $sourceUrl = $this->absoluteUrl(trim($titleLink->getAttribute('href')));
        if ($sourceUrl === self::BASE_URL) {
            return null;
        }

        $sourceId = $this->extractEventSlug($sourceUrl);

        // Start datetime — ISO 8601 with offset from abbr/@title
        $startsAt = null;
        $startAbbrNodes = $xpath->query(
            './/abbr[contains(@class,"tribe-events-start-datetime")]',
            $article
        );
        if ($startAbbrNodes !== false && $startAbbrNodes->length > 0) {
            $startAbbr = $startAbbrNodes->item(0);
            if ($startAbbr instanceof DOMElement) {
                $startsAt = $this->parseTecDatetime($startAbbr->getAttribute('title'));
            }
        }

        // End datetime
        $endsAt = null;
        $endAbbrNodes = $xpath->query(
            './/abbr[contains(@class,"tribe-events-end-datetime")]',
            $article
        );
        if ($endAbbrNodes !== false && $endAbbrNodes->length > 0) {
            $endAbbr = $endAbbrNodes->item(0);
            if ($endAbbr instanceof DOMElement) {
                $endsAt = $this->parseTecDatetime($endAbbr->getAttribute('title'));
            }
        }

        // Description — first <p> in the events-content div
        $description = null;
        $descNodes = $xpath->query(
            './/div[contains(@class,"tribe-events-content")]//p[1]',
            $article
        );
        if ($descNodes !== false && $descNodes->length > 0) {
            $raw = trim($descNodes->item(0)->textContent);
            $description = $raw !== '' ? $raw : null;
        }

        // Venue
        $venue = null;
        $venueNodes = $xpath->query('.//span[@class="tribe-venue"]', $article);
        if ($venueNodes !== false && $venueNodes->length > 0) {
            $raw = trim($venueNodes->item(0)->textContent);
            $venue = $raw !== '' ? $raw : null;
        }

        // Address
        $address = null;
        $addressNodes = $xpath->query(
            './/span[contains(@class,"tribe-address")]',
            $article
        );
        if ($addressNodes !== false && $addressNodes->length > 0) {
            $raw = $this->stripHtml($addressNodes->item(0)->textContent);
            $address = $raw !== '' ? $raw : null;
        }

        // Image
        $imageUrl = null;
        $imgNodes = $xpath->query(
            './/div[contains(@class,"tribe-event-featured-image")]//img/@src',
            $article
        );
        if ($imgNodes !== false && $imgNodes->length > 0) {
            $raw = trim($imgNodes->item(0)->nodeValue);
            $imageUrl = $raw !== '' ? $raw : null;
        }

        // Cost
        $isFree = null;
        $costNodes = $xpath->query(
            './/div[contains(@class,"tribe-events-event-cost")]',
            $article
        );
        if ($costNodes !== false && $costNodes->length > 0) {
            $costText = trim($costNodes->item(0)->textContent);
            $cost = $this->parseCost($costText);
            $isFree = $cost['isFree'];
        }

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
            isFree: $isFree,
            imageUrl: $imageUrl,
            metadata: [],
        );
    }

    /**
     * Parse a UTC datetime string or an ISO 8601 string with offset to a UTC datetime string.
     *
     * Handles both "2026-05-10 16:00:00" (already UTC from TEC API) and
     * "2026-05-10T19:00:00+03:00" (ISO with offset from HTML attributes).
     */
    private function parseTecDatetime(string $dateStr): ?string
    {
        if ($dateStr === '') {
            return null;
        }

        try {
            return Carbon::parse($dateStr)->utc()->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Parse a cost/price string into structured price fields.
     *
     * @return array{priceMin: ?float, priceMax: ?float, isFree: ?bool}
     */
    private function parseCost(string $costText): array
    {
        if ($costText === '') {
            return ['priceMin' => null, 'priceMax' => null, 'isFree' => null];
        }

        if (preg_match('/liber|gratuit|free/iu', $costText)) {
            return ['priceMin' => 0.0, 'priceMax' => null, 'isFree' => true];
        }

        $price = $this->parsePrice($costText);

        return [
            'priceMin' => $price,
            'priceMax' => null,
            'isFree' => $price !== null ? ($price === 0.0) : null,
        ];
    }

    /**
     * Extract the last path segment (slug) from an event URL.
     *
     * "https://visit-timisoara.com/event/concert-jazz/" → "concert-jazz"
     */
    private function extractEventSlug(string $url): ?string
    {
        $slug = basename(rtrim($url, '/'));

        return $slug !== '' ? $slug : null;
    }

    /**
     * Prepend BASE_URL if the given path is not already absolute.
     */
    private function absoluteUrl(string $path): string
    {
        if ($path === '') {
            return '';
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return self::BASE_URL.'/'.ltrim($path, '/');
    }
}
