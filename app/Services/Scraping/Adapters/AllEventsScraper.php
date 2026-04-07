<?php

declare(strict_types=1);

namespace App\Services\Scraping\Adapters;

use App\DTOs\RawEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AllEventsScraper extends AbstractHtmlScraper
{
    private const string SOURCE = 'allevents';

    private const string API_ENDPOINT = 'https://allevents.in/api/events/list';

    private const int ROWS_PER_PAGE = 20;

    public function adapterKey(): string
    {
        return self::SOURCE;
    }

    public function sourceIdentifier(array $sourceConfig): string
    {
        return self::SOURCE.'@allevents.in';
    }

    /**
     * @param  array{adapter: string, url: string, extra_urls?: list<string>, enabled: bool, interval_hours: int, country?: string}  $sourceConfig
     * @param  array{label: string, timezone: string, coordinates: list<float>, radius_km: int}  $cityConfig
     */
    public function scrape(array $sourceConfig, array $cityConfig, callable $onEvent): void
    {
        $city = $this->extractCitySlug($sourceConfig['url']);
        $country = $sourceConfig['country'] ?? 'romania';
        $cityLabel = $cityConfig['label'];
        $maxPages = (int) config('eventpulse.scrapers.max_pages', 10);
        $emitted = 0;

        Log::debug('AllEventsScraper: starting scrape', [
            'city' => $city,
            'country' => $country,
            'max_pages' => $maxPages,
        ]);

        for ($page = 1; $page <= $maxPages; $page++) {
            $payload = [
                'city' => $city,
                'country' => $country,
                'page' => $page,
                'rows' => self::ROWS_PER_PAGE,
                'popular' => true,
                'venue' => [],
                'keywords' => '',
                'type' => '',
                'ids' => [],
                'sdate' => '',
                'edate' => '',
            ];

            Log::debug("AllEventsScraper: fetching page {$page}", ['payload' => $payload]);

            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
                'Referer' => $sourceConfig['url'],
                'Accept' => 'application/json',
                'Origin' => 'https://allevents.in',
            ])->timeout(30)->post(self::API_ENDPOINT, $payload);

            if ($response->failed()) {
                Log::warning("AllEventsScraper: HTTP error on page {$page}", [
                    'status' => $response->status(),
                ]);
                break;
            }

            $body = $response->json();

            if (($body['error'] ?? 1) !== 0) {
                Log::warning('AllEventsScraper: API returned error', ['body' => $body]);
                break;
            }

            $events = $body['data'] ?? [];

            Log::debug('AllEventsScraper: received '.count($events)." events on page {$page}");

            if (empty($events)) {
                Log::debug("AllEventsScraper: no events on page {$page}, stopping pagination");
                break;
            }

            foreach ($events as $raw) {
                $event = $this->mapToRawEvent($raw, $cityLabel);
                if ($event !== null) {
                    $onEvent($event);
                    $emitted++;
                }
            }

            $this->sleepBetweenRequests();
        }

        Log::info('AllEventsScraper: scrape complete', ['emitted' => $emitted, 'city' => $city]);
    }

    /**
     * Map a single API event object to a RawEvent DTO.
     *
     * @param  array<string, mixed>  $raw
     */
    private function mapToRawEvent(array $raw, string $cityLabel): ?RawEvent
    {
        $title = $this->stripHtml((string) ($raw['eventname'] ?? ''));
        if ($title === '') {
            return null;
        }

        $title = $this->stripCityPrefix($title, $cityLabel);
        if ($title === '') {
            return null;
        }

        $sourceUrl = (string) ($raw['event_url'] ?? '');
        if ($sourceUrl === '') {
            return null;
        }

        $sourceId = (string) ($raw['event_id'] ?? '');

        $startTimestamp = (int) ($raw['start_time'] ?? 0);
        $endTimestamp = (int) ($raw['end_time'] ?? 0);

        $startsAt = $startTimestamp > 0
            ? Carbon::createFromTimestamp($startTimestamp)->toDateTimeString()
            : null;

        $endsAt = ($endTimestamp > 0 && $endTimestamp !== $startTimestamp)
            ? Carbon::createFromTimestamp($endTimestamp)->toDateTimeString()
            : null;

        $venue = (string) ($raw['location'] ?? '');
        $venue = $venue !== '' ? $this->stripHtml($venue) : null;

        $venueData = is_array($raw['venue'] ?? null) ? $raw['venue'] : [];
        $address = isset($venueData['full_address']) && $venueData['full_address'] !== ''
            ? (string) $venueData['full_address']
            : null;

        $bannerUrl = (string) ($raw['banner_url'] ?? '');
        $thumbUrl = (string) ($raw['thumb_url'] ?? '');
        $imageUrl = $bannerUrl !== '' ? $bannerUrl : ($thumbUrl !== '' ? $thumbUrl : null);

        $categories = is_array($raw['categories'] ?? null) ? $raw['categories'] : [];
        $tags = is_array($raw['tags'] ?? null) ? $raw['tags'] : [];

        Log::debug('AllEventsScraper: parsed event', [
            'title' => $title,
            'venue' => $venue,
            'starts_at' => $startsAt,
            'source_url' => $sourceUrl,
        ]);

        return new RawEvent(
            title: $title,
            description: null,
            sourceUrl: $sourceUrl,
            sourceId: $sourceId !== '' ? $sourceId : null,
            source: $this->adapterKey(),
            venue: $venue,
            address: $address,
            city: $cityLabel,
            startsAt: $startsAt,
            endsAt: $endsAt,
            priceMin: null,
            priceMax: null,
            currency: null,
            isFree: null,
            imageUrl: $imageUrl,
            metadata: array_filter([
                'categories' => $categories ?: null,
                'tags' => $tags ?: null,
            ]),
        );
    }

    /**
     * Extract the city slug from the source URL.
     *
     * "https://allevents.in/timisoara/all" → "timisoara"
     * "https://allevents.in/cluj-napoca/all" → "cluj-napoca"
     */
    private function extractCitySlug(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $segments = array_values(array_filter(explode('/', $path)));

        return mb_strtolower($segments[0] ?? '');
    }

    /**
     * Strip a leading "City: " prefix from a title if present (case- and diacritic-insensitive).
     *
     * "TIMISOARA: Stand-up Show" → "Stand-up Show"
     * "TIMIȘOARA: Concert" → "Concert"
     * "FuN Timișoara: The Comeback Edition" → unchanged (city not at start)
     */
    private function stripCityPrefix(string $title, string $city): string
    {
        $normalizedCity = $this->normalizeText($city);
        $normalizedTitle = $this->normalizeText($title);
        $prefix = $normalizedCity.': ';

        if (str_starts_with($normalizedTitle, $prefix)) {
            return trim(mb_substr($title, mb_strlen($prefix)));
        }

        return $title;
    }
}
