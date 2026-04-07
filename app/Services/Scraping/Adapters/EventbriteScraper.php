<?php

declare(strict_types=1);

namespace App\Services\Scraping\Adapters;

use App\Contracts\ScraperAdapter;
use App\DTOs\RawEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EventbriteScraper implements ScraperAdapter
{
    private const string SOURCE = 'eventbrite';

    private const string API_BASE = 'https://www.eventbriteapi.com/v3/events/search/';

    public function adapterKey(): string
    {
        return self::SOURCE;
    }

    public function sourceIdentifier(array $sourceConfig): string
    {
        return self::SOURCE.'@eventbriteapi.com';
    }

    public function scrape(array $sourceConfig, array $cityConfig, callable $onEvent): void
    {
        $apiKey = config('eventpulse.eventbrite_api_key');

        if (empty($apiKey)) {
            Log::warning('EventbriteScraper: EVENTBRITE_API_KEY is not set, skipping');

            return;
        }

        $address = $sourceConfig['params']['address'] ?? '';
        $within = $cityConfig['radius_km'].'km';
        $maxPages = (int) config('eventpulse.scrapers.max_pages', 10);
        $emitted = 0;

        Log::debug('EventbriteScraper: starting scrape', [
            'address' => $address,
            'within' => $within,
            'max_pages' => $maxPages,
        ]);

        for ($page = 1; $page <= $maxPages; $page++) {
            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->get(self::API_BASE, [
                    'location.address' => $address,
                    'location.within' => $within,
                    'start_date.range_start' => now()->toIso8601String(),
                    'expand' => 'venue,category,ticket_availability',
                    'page' => $page,
                ]);

            if ($response->status() === 401) {
                Log::error('EventbriteScraper: invalid API key (401)');
                break;
            }

            if ($response->status() === 429) {
                $retryAfter = (int) ($response->header('Retry-After') ?: 60);
                Log::warning("EventbriteScraper: rate limited, sleeping {$retryAfter}s");
                $this->sleepSeconds($retryAfter);
                $page--;

                continue;
            }

            if ($response->failed()) {
                Log::warning('EventbriteScraper: HTTP error', ['status' => $response->status(), 'page' => $page]);
                break;
            }

            $body = $response->json();
            $events = $body['events'] ?? [];

            Log::debug('EventbriteScraper: received '.count($events)." events on page {$page}");

            if (empty($events)) {
                Log::debug("EventbriteScraper: no events on page {$page}, stopping pagination");
                break;
            }

            foreach ($events as $raw) {
                $event = $this->mapToRawEvent($raw, $cityConfig['label']);
                if ($event !== null) {
                    $onEvent($event);
                    $emitted++;
                }
            }

            if (! ($body['pagination']['has_more_items'] ?? false)) {
                break;
            }
        }

        Log::info('EventbriteScraper: scrape complete', ['emitted' => $emitted, 'address' => $address]);
    }

    /**
     * Map a single Eventbrite API event object to a RawEvent DTO.
     *
     * @param  array<string, mixed>  $raw
     */
    private function mapToRawEvent(array $raw, string $cityLabel): ?RawEvent
    {
        $title = (string) ($raw['name']['text'] ?? '');
        if ($title === '') {
            return null;
        }

        $sourceUrl = (string) ($raw['url'] ?? '');
        if ($sourceUrl === '') {
            return null;
        }

        $sourceId = (string) ($raw['id'] ?? '');

        $description = null;
        $descriptionText = (string) ($raw['description']['text'] ?? '');
        if ($descriptionText !== '') {
            $description = $descriptionText;
        } elseif (isset($raw['summary']) && (string) $raw['summary'] !== '') {
            $description = (string) $raw['summary'];
        }

        $startUtc = (string) ($raw['start']['utc'] ?? '');
        $startsAt = $startUtc !== '' ? Carbon::parse($startUtc)->toDateTimeString() : null;

        $endUtc = (string) ($raw['end']['utc'] ?? '');
        $endsAt = $endUtc !== '' ? Carbon::parse($endUtc)->toDateTimeString() : null;

        $venue = null;
        $address = null;
        if (is_array($raw['venue'] ?? null)) {
            $venueData = $raw['venue'];
            $venueName = (string) ($venueData['name'] ?? '');
            $venue = $venueName !== '' ? $venueName : null;

            $addressText = (string) ($venueData['address']['localized_address_display'] ?? '');
            $address = $addressText !== '' ? $addressText : null;
        }

        $imageUrl = null;
        $logoUrl = (string) ($raw['logo']['url'] ?? '');
        if ($logoUrl !== '') {
            $imageUrl = $logoUrl;
        }

        $isFree = (bool) ($raw['is_free'] ?? false);

        $priceMin = null;
        $priceMax = null;
        $currency = null;
        if (is_array($raw['ticket_availability'] ?? null)) {
            $ta = $raw['ticket_availability'];

            if (is_array($ta['minimum_ticket_price'] ?? null)) {
                $priceMin = isset($ta['minimum_ticket_price']['value'])
                    ? (float) $ta['minimum_ticket_price']['value']
                    : null;
                $currency = (string) ($ta['minimum_ticket_price']['currency'] ?? '') ?: null;
            }

            if (is_array($ta['maximum_ticket_price'] ?? null)) {
                $priceMax = isset($ta['maximum_ticket_price']['value'])
                    ? (float) $ta['maximum_ticket_price']['value']
                    : null;
            }
        }

        $metadata = array_filter([
            'category' => is_array($raw['category'] ?? null) ? ($raw['category']['name'] ?? null) : null,
            'subcategory' => is_array($raw['subcategory'] ?? null) ? ($raw['subcategory']['name'] ?? null) : null,
        ]);

        Log::debug('EventbriteScraper: parsed event', [
            'title' => $title,
            'venue' => $venue,
            'starts_at' => $startsAt,
            'source_url' => $sourceUrl,
        ]);

        return new RawEvent(
            title: $title,
            description: $description,
            sourceUrl: $sourceUrl,
            sourceId: $sourceId !== '' ? $sourceId : null,
            source: self::SOURCE,
            venue: $venue,
            address: $address,
            city: $cityLabel,
            startsAt: $startsAt,
            endsAt: $endsAt,
            priceMin: $priceMin,
            priceMax: $priceMax,
            currency: $currency,
            isFree: $isFree,
            imageUrl: $imageUrl,
            metadata: $metadata,
        );
    }

    protected function sleepSeconds(int $seconds): void
    {
        sleep($seconds);
    }
}
