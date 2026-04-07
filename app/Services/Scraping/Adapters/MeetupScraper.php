<?php

declare(strict_types=1);

namespace App\Services\Scraping\Adapters;

use App\DTOs\RawEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MeetupScraper extends AbstractHtmlScraper
{
    private const string SOURCE = 'meetup';

    private const string GQL_URL = 'https://www.meetup.com/gql';

    private const string FIND_BASE = 'https://www.meetup.com/find/';

    public function adapterKey(): string
    {
        return self::SOURCE;
    }

    public function sourceIdentifier(array $sourceConfig): string
    {
        return self::SOURCE.'@meetup.com';
    }

    /**
     * @param  array{adapter: string, params?: array<string, mixed>, enabled: bool, interval_hours: int}  $sourceConfig
     * @param  array{label: string, timezone: string, coordinates: list<float>, radius_km: int}  $cityConfig
     * @param  callable(RawEvent): void  $onEvent
     */
    public function scrape(array $sourceConfig, array $cityConfig, callable $onEvent): void
    {
        $events = $this->attemptGraphQL($sourceConfig, $cityConfig)
            ?? $this->fetchViaNextData($sourceConfig, $cityConfig);

        $emitted = 0;

        foreach ($events as $event) {
            $onEvent($event);
            $emitted++;
        }

        Log::info('MeetupScraper: scrape complete', ['emitted' => $emitted]);
    }

    /**
     * Attempt to fetch events via the Meetup GraphQL API.
     *
     * Returns a list of RawEvents on success, or null on any failure (404,
     * auth error, malformed response) so the caller can fall back to HTML.
     *
     * @param  array{adapter: string, params?: array<string, mixed>, enabled: bool, interval_hours: int}  $sourceConfig
     * @param  array{label: string, timezone: string, coordinates: list<float>, radius_km: int}  $cityConfig
     * @return list<RawEvent>|null
     */
    private function attemptGraphQL(array $sourceConfig, array $cityConfig): ?array
    {
        $coords = $cityConfig['coordinates'];
        $lat = (float) $coords[0];
        $lon = (float) $coords[1];
        $radius = (int) $cityConfig['radius_km'];

        $query = '{ keywordSearch(input: { first: 20, lat: '.$lat.', lon: '.$lon.', radius: '.$radius.', source: EVENTS }) {'
            .' edges { node { id title dateTime endTime eventUrl'
            .' description { truncatedDescription }'
            .' images { baseUrl }'
            .' venue { name }'
            .' group { name urlname }'
            .' going'
            .' feeSettings { amount { amount currency } } } } } }';

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post(self::GQL_URL, ['query' => $query]);

            if ($response->failed()) {
                Log::debug('MeetupScraper: GraphQL unavailable', ['status' => $response->status()]);

                return null;
            }

            /** @var array<string, mixed>|null $edges */
            $edges = $response->json('data.keywordSearch.edges');

            if (! is_array($edges) || $edges === []) {
                Log::debug('MeetupScraper: GraphQL returned no edges, falling back to HTML');

                return null;
            }

            $events = [];

            foreach ($edges as $edge) {
                if (! is_array($edge) || ! is_array($edge['node'] ?? null)) {
                    continue;
                }

                $event = $this->mapGraphQlNode($edge['node'], $cityConfig['label']);
                if ($event !== null) {
                    $events[] = $event;
                }
            }

            Log::info('MeetupScraper: GraphQL succeeded', ['count' => count($events)]);

            return $events;
        } catch (\Throwable $e) {
            Log::debug('MeetupScraper: GraphQL exception, falling back to HTML', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Fetch events by parsing the `__NEXT_DATA__` JSON blob embedded in the
     * Meetup find page HTML.
     *
     * @param  array{adapter: string, params?: array<string, mixed>, enabled: bool, interval_hours: int}  $sourceConfig
     * @param  array{label: string, timezone: string, coordinates: list<float>, radius_km: int}  $cityConfig
     * @return list<RawEvent>
     */
    private function fetchViaNextData(array $sourceConfig, array $cityConfig): array
    {
        $params = $sourceConfig['params'] ?? null;
        $location = is_array($params) ? (string) ($params['location'] ?? '') : '';
        $url = self::FIND_BASE.ltrim($location, '/').'/';

        $html = $this->fetchPage($url);

        if ($html === '') {
            Log::warning('MeetupScraper: empty HTML response', ['url' => $url]);

            return [];
        }

        if (! preg_match('/<script[^>]+id="__NEXT_DATA__"[^>]*>(.*?)<\/script>/s', $html, $m)) {
            Log::warning('MeetupScraper: __NEXT_DATA__ not found', ['url' => $url]);

            return [];
        }

        $data = json_decode($m[1], true);

        if (! is_array($data)) {
            return [];
        }

        $items = $data['props']['pageProps']['eventsInLocation'] ?? [];

        if (! is_array($items)) {
            return [];
        }

        $events = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $event = $this->mapNextDataEvent($item, $cityConfig['label']);
            if ($event !== null) {
                $events[] = $event;
            }
        }

        return $events;
    }

    /**
     * Map a `__NEXT_DATA__` event array to a RawEvent.
     *
     * @param  array<string, mixed>  $event
     */
    private function mapNextDataEvent(array $event, string $cityLabel): ?RawEvent
    {
        $title = trim((string) ($event['title'] ?? ''));
        if ($title === '') {
            return null;
        }

        $sourceUrl = (string) ($event['eventUrl'] ?? '');
        if ($sourceUrl === '') {
            return null;
        }

        $sourceId = isset($event['id']) ? (string) $event['id'] : null;

        $startsAt = $this->parseIsoDate((string) ($event['dateTime'] ?? ''));
        $endsAt = isset($event['endTime']) ? $this->parseIsoDate((string) $event['endTime']) : null;

        $venue = is_array($event['venue'] ?? null)
            ? (trim((string) ($event['venue']['name'] ?? '')) ?: null)
            : null;

        $imageUrl = $this->extractImageUrl($event);

        $isFree = ($event['feeSettings'] ?? null) === null;

        /** @var array<string, mixed> $group */
        $group = is_array($event['group'] ?? null) ? $event['group'] : [];
        $groupName = isset($group['name']) ? (string) $group['name'] : null;

        /** @var array<string, mixed> $going */
        $going = is_array($event['going'] ?? null) ? $event['going'] : [];
        $rsvpCount = isset($going['totalCount']) ? (int) $going['totalCount'] : 0;

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
            endsAt: $endsAt,
            priceMin: null,
            priceMax: null,
            currency: null,
            isFree: $isFree,
            imageUrl: $imageUrl,
            metadata: [
                'group_name' => $groupName,
                'rsvp_count' => $rsvpCount,
                'event_type' => isset($event['eventType']) ? (string) $event['eventType'] : null,
            ],
        );
    }

    /**
     * Map a GraphQL `keywordSearch` node to a RawEvent.
     *
     * @param  array<string, mixed>  $node
     */
    private function mapGraphQlNode(array $node, string $cityLabel): ?RawEvent
    {
        $title = trim((string) ($node['title'] ?? ''));
        if ($title === '') {
            return null;
        }

        $sourceUrl = (string) ($node['eventUrl'] ?? '');
        if ($sourceUrl === '') {
            return null;
        }

        $sourceId = isset($node['id']) ? (string) $node['id'] : null;

        $startsAt = $this->parseIsoDate((string) ($node['dateTime'] ?? ''));
        $endsAt = isset($node['endTime']) ? $this->parseIsoDate((string) $node['endTime']) : null;

        $venue = is_array($node['venue'] ?? null)
            ? (trim((string) ($node['venue']['name'] ?? '')) ?: null)
            : null;

        $imageUrl = null;
        if (is_array($node['images'] ?? null) && $node['images'] !== []) {
            $first = $node['images'][0];
            $imageUrl = is_array($first) ? (trim((string) ($first['baseUrl'] ?? '')) ?: null) : null;
        }

        $isFree = ($node['feeSettings'] ?? null) === null;

        /** @var array<string, mixed> $group */
        $group = is_array($node['group'] ?? null) ? $node['group'] : [];
        $groupName = isset($group['name']) ? (string) $group['name'] : null;

        $rsvpCount = isset($node['going']) ? (int) $node['going'] : 0;

        $description = null;
        if (is_array($node['description'] ?? null)) {
            $raw = trim((string) ($node['description']['truncatedDescription'] ?? ''));
            $description = $raw !== '' ? $raw : null;
        }

        return new RawEvent(
            title: $title,
            description: $description,
            sourceUrl: $sourceUrl,
            sourceId: $sourceId,
            source: self::SOURCE,
            venue: $venue,
            address: null,
            city: $cityLabel,
            startsAt: $startsAt,
            endsAt: $endsAt,
            priceMin: null,
            priceMax: null,
            currency: null,
            isFree: $isFree,
            imageUrl: $imageUrl,
            metadata: [
                'group_name' => $groupName,
                'rsvp_count' => $rsvpCount,
            ],
        );
    }

    /**
     * Extract an image URL from a __NEXT_DATA__ event, preferring featuredEventPhoto.
     *
     * @param  array<string, mixed>  $event
     */
    private function extractImageUrl(array $event): ?string
    {
        if (is_array($event['featuredEventPhoto'] ?? null)) {
            $url = trim((string) ($event['featuredEventPhoto']['highResUrl'] ?? ''));
            if ($url !== '') {
                return $url;
            }
        }

        if (is_array($event['displayPhoto'] ?? null)) {
            $url = trim((string) ($event['displayPhoto']['highResUrl'] ?? ''));
            if ($url !== '') {
                return $url;
            }
        }

        return null;
    }

    /**
     * Parse an ISO 8601 datetime string with timezone offset to a UTC datetime string.
     *
     * Example: "2026-04-23T10:00:00+03:00" → "2026-04-23 07:00:00"
     */
    private function parseIsoDate(string $dateStr): ?string
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
}
