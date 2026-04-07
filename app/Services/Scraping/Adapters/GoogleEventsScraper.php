<?php

declare(strict_types=1);

namespace App\Services\Scraping\Adapters;

use App\Contracts\ScraperAdapter;
use App\DTOs\RawEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleEventsScraper implements ScraperAdapter
{
    private const string SOURCE = 'google_events';

    private const string API_URL = 'https://serpapi.com/search.json';

    public function adapterKey(): string
    {
        return self::SOURCE;
    }

    public function sourceIdentifier(array $sourceConfig): string
    {
        return self::SOURCE.'@serpapi.com';
    }

    /**
     * @param  array{adapter: string, params?: array<string, mixed>, enabled: bool, interval_hours: int}  $sourceConfig
     * @param  array{label: string, timezone: string, coordinates: list<float>, radius_km: int}  $cityConfig
     * @param  callable(RawEvent): void  $onEvent
     */
    public function scrape(array $sourceConfig, array $cityConfig, callable $onEvent): void
    {
        $apiKey = (string) config('eventpulse.serpapi_api_key', '');

        if ($apiKey === '') {
            Log::warning('GoogleEventsScraper: SERPAPI_API_KEY is not set, skipping');

            return;
        }

        $params = $sourceConfig['params'] ?? null;
        $query = is_array($params) ? trim((string) ($params['q'] ?? '')) : '';

        if ($query === '') {
            Log::warning('GoogleEventsScraper: no query (params.q) configured, skipping');

            return;
        }

        $response = Http::timeout(30)->get(self::API_URL, [
            'engine' => 'google_events',
            'q' => $query,
            'api_key' => $apiKey,
        ]);

        if ($response->status() === 401) {
            Log::error('GoogleEventsScraper: invalid API key (401)');

            return;
        }

        if ($response->failed()) {
            Log::warning('GoogleEventsScraper: API request failed', [
                'status' => $response->status(),
                'query' => $query,
            ]);

            return;
        }

        $body = $response->json();
        $results = $body['events_results'] ?? null;

        if (! is_array($results)) {
            Log::debug('GoogleEventsScraper: no events_results in response', ['query' => $query]);

            return;
        }

        $emitted = 0;

        foreach ($results as $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $event = $this->mapToRawEvent($raw, $cityConfig['label']);
            if ($event !== null) {
                $onEvent($event);
                $emitted++;
            }
        }

        Log::info('GoogleEventsScraper: scrape complete', ['emitted' => $emitted, 'query' => $query]);
    }

    /**
     * Map a single `events_results` item to a RawEvent.
     *
     * @param  array<string, mixed>  $raw
     */
    private function mapToRawEvent(array $raw, string $cityLabel): ?RawEvent
    {
        $title = trim((string) ($raw['title'] ?? ''));
        if ($title === '') {
            return null;
        }

        $sourceUrl = trim((string) ($raw['link'] ?? ''));
        if ($sourceUrl === '') {
            return null;
        }

        // Date — `date.when` is a human-readable string with no year
        $dateWhen = is_array($raw['date'] ?? null)
            ? trim((string) ($raw['date']['when'] ?? ''))
            : '';
        $startsAt = $this->parseGoogleDate($dateWhen);

        // Address array: first element is venue/location name, rest is street address
        $venue = null;
        $address = null;

        if (is_array($raw['address'] ?? null) && $raw['address'] !== []) {
            $parts = array_values(array_filter(
                array_map(fn ($p) => trim((string) $p), $raw['address']),
                fn ($p) => $p !== '',
            ));

            if ($parts !== []) {
                $venue = $parts[0];
                $address = count($parts) > 1 ? implode(', ', array_slice($parts, 1)) : null;
            }
        }

        // venue.name overrides address[0] when present (more reliable)
        if (is_array($raw['venue'] ?? null)) {
            $venueName = trim((string) ($raw['venue']['name'] ?? ''));
            if ($venueName !== '') {
                $venue = $venueName;
            }
        }

        // Prefer thumbnail, fall back to image
        $imageUrl = null;
        $thumbRaw = trim((string) ($raw['thumbnail'] ?? ''));
        $imageRaw = trim((string) ($raw['image'] ?? ''));
        if ($thumbRaw !== '') {
            $imageUrl = $thumbRaw;
        } elseif ($imageRaw !== '') {
            $imageUrl = $imageRaw;
        }

        $description = trim((string) ($raw['description'] ?? ''));
        $description = $description !== '' ? $description : null;

        // Collect ticket source names for metadata
        $ticketSources = [];
        if (is_array($raw['ticket_info'] ?? null)) {
            foreach ($raw['ticket_info'] as $ticket) {
                if (is_array($ticket) && isset($ticket['source'])) {
                    $name = trim((string) $ticket['source']);
                    if ($name !== '') {
                        $ticketSources[] = $name;
                    }
                }
            }
        }

        return new RawEvent(
            title: $title,
            description: $description,
            sourceUrl: $sourceUrl,
            sourceId: null,
            source: self::SOURCE,
            venue: $venue,
            address: $address,
            city: $cityLabel,
            startsAt: $startsAt,
            endsAt: null,
            priceMin: null,
            priceMax: null,
            currency: null,
            isFree: null,
            imageUrl: $imageUrl,
            metadata: [
                'ticket_sources' => $ticketSources,
            ],
        );
    }

    /**
     * Parse a Google Events "when" string to a UTC datetime string.
     *
     * Input examples:
     *   "Thu, Apr 10, 7:00 PM"
     *   "Sat, Apr 5, 10:00 AM – 3:00 PM"
     *   "Apr 10 – 12"
     *
     * Google Events omits the year. We assume the current year and advance by one
     * year when the parsed date is more than 60 days in the past.
     */
    private function parseGoogleDate(string $when): ?string
    {
        if ($when === '') {
            return null;
        }

        // Keep only the start portion; strip range suffixes like "– 3:00 PM" or "– Apr 12"
        $parts = preg_split('/\s*[–—\-]\s*/', $when, 2);
        $start = is_array($parts) ? trim($parts[0]) : trim($when);

        if ($start === '') {
            return null;
        }

        try {
            $carbon = Carbon::parse($start);

            // Advance to next year when date is more than 60 days in the past
            if ($carbon->diffInDays(now(), false) > 60) {
                $carbon->addYear();
            }

            return $carbon->utc()->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }
}
