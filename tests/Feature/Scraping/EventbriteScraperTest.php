<?php

declare(strict_types=1);

use App\DTOs\RawEvent;
use App\Services\Scraping\Adapters\EventbriteScraper;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

// ---------------------------------------------------------------------------
// Test double — tracks sleep calls without actually sleeping.
// ---------------------------------------------------------------------------

class TestEventbriteScraper extends EventbriteScraper
{
    public int $sleepCalls = 0;

    public int $lastSleepSeconds = 0;

    protected function sleepSeconds(int $seconds): void
    {
        $this->sleepCalls++;
        $this->lastSleepSeconds = $seconds;
    }
}

// ---------------------------------------------------------------------------
// Helper: run scrape() and collect all emitted RawEvents.
// ---------------------------------------------------------------------------

/**
 * @param  array<string, mixed>  $sourceConfig
 * @param  array<string, mixed>  $cityConfig
 * @return Collection<int, RawEvent>
 */
function ebScrapeToCollection(EventbriteScraper $scraper, array $sourceConfig, array $cityConfig): Collection
{
    $events = collect();
    Http::preventStrayRequests();
    $scraper->scrape($sourceConfig, $cityConfig, fn ($e) => $events->push($e));

    return $events;
}

// ---------------------------------------------------------------------------
// API response fixture builders
// ---------------------------------------------------------------------------

/**
 * Build a successful API response page.
 *
 * @param  array<int, array<string, mixed>>  $events
 * @return array<string, mixed>
 */
function ebApiResponse(array $events, bool $hasMore = false): array
{
    return [
        'pagination' => [
            'page_number' => 1,
            'page_count' => $hasMore ? 2 : 1,
            'has_more_items' => $hasMore,
        ],
        'events' => $events,
    ];
}

/**
 * Build a minimal Eventbrite API event matching the real v3 shape.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function ebEvent(array $overrides = []): array
{
    return array_replace_recursive([
        'id' => '123456789',
        'name' => ['text' => 'Rock Concert Timisoara'],
        'description' => ['text' => 'A great rock concert in Timisoara.'],
        'summary' => 'Short summary fallback',
        'url' => 'https://www.eventbrite.com/e/rock-concert-123456789',
        'start' => ['utc' => '2026-04-10T18:00:00Z'],
        'end' => ['utc' => '2026-04-10T22:00:00Z'],
        'is_free' => false,
        'logo' => ['url' => 'https://img.evbstatic.com/banner.jpg'],
        'venue' => [
            'name' => 'Club Doors',
            'address' => [
                'localized_address_display' => 'Str. Mercy 3, Timisoara',
            ],
        ],
        'category' => ['name' => 'Music'],
        'subcategory' => ['name' => 'Rock'],
        'ticket_availability' => [
            'minimum_ticket_price' => ['value' => 50.0, 'currency' => 'RON'],
            'maximum_ticket_price' => ['value' => 100.0, 'currency' => 'RON'],
        ],
    ], $overrides);
}

// ---------------------------------------------------------------------------
// Default config fixtures
// ---------------------------------------------------------------------------

$defaultSourceConfig = [
    'adapter' => 'eventbrite',
    'params' => ['address' => 'Timisoara,Romania'],
    'enabled' => true,
    'interval_hours' => 6,
];

$defaultCityConfig = [
    'label' => 'Timișoara',
    'timezone' => 'Europe/Bucharest',
    'coordinates' => [45.7489, 21.2087],
    'radius_km' => 25,
];

// ---------------------------------------------------------------------------
// adapterKey
// ---------------------------------------------------------------------------

describe('adapterKey', function () {
    it('returns "eventbrite"', function () {
        expect((new TestEventbriteScraper)->adapterKey())->toBe('eventbrite');
    });
});

// ---------------------------------------------------------------------------
// sourceIdentifier
// ---------------------------------------------------------------------------

describe('sourceIdentifier', function () use ($defaultSourceConfig) {
    it('returns "eventbrite@eventbriteapi.com"', function () use ($defaultSourceConfig) {
        expect((new TestEventbriteScraper)->sourceIdentifier($defaultSourceConfig))
            ->toBe('eventbrite@eventbriteapi.com');
    });
});

// ---------------------------------------------------------------------------
// Basic field mapping
// ---------------------------------------------------------------------------

describe('field mapping', function () use ($defaultSourceConfig, $defaultCityConfig) {
    beforeEach(function () {
        config(['eventpulse.eventbrite_api_key' => 'test-key']);
    });

    it('maps name.text to title', function () use ($defaultSourceConfig, $defaultCityConfig) {
        Http::fake(['https://www.eventbriteapi.com/v3/events/search/*' => Http::response(
            json_encode(ebApiResponse([ebEvent(['name' => ['text' => 'Jazz Night']])]))
        )]);

        $events = ebScrapeToCollection(new TestEventbriteScraper, $defaultSourceConfig, $defaultCityConfig);

        expect($events->first()->title)->toBe('Jazz Night');
    });

    it('maps id to sourceId', function () use ($defaultSourceConfig, $defaultCityConfig) {
        Http::fake(['https://www.eventbriteapi.com/v3/events/search/*' => Http::response(
            json_encode(ebApiResponse([ebEvent(['id' => '987654321'])]))
        )]);

        $events = ebScrapeToCollection(new TestEventbriteScraper, $defaultSourceConfig, $defaultCityConfig);

        expect($events->first()->sourceId)->toBe('987654321');
    });

    it('maps url to sourceUrl', function () use ($defaultSourceConfig, $defaultCityConfig) {
        Http::fake(['https://www.eventbriteapi.com/v3/events/search/*' => Http::response(
            json_encode(ebApiResponse([ebEvent(['url' => 'https://www.eventbrite.com/e/my-event-999'])]))
        )]);

        $events = ebScrapeToCollection(new TestEventbriteScraper, $defaultSourceConfig, $defaultCityConfig);

        expect($events->first()->sourceUrl)->toBe('https://www.eventbrite.com/e/my-event-999');
    });

    it('sets source to "eventbrite"', function () use ($defaultSourceConfig, $defaultCityConfig) {
        Http::fake(['https://www.eventbriteapi.com/v3/events/search/*' => Http::response(
            json_encode(ebApiResponse([ebEvent()]))
        )]);

        $events = ebScrapeToCollection(new TestEventbriteScraper, $defaultSourceConfig, $defaultCityConfig);

        expect($events->first()->source)->toBe('eventbrite');
    });

    it('sets city from cityConfig label', function () use ($defaultSourceConfig, $defaultCityConfig) {
        Http::fake(['https://www.eventbriteapi.com/v3/events/search/*' => Http::response(
            json_encode(ebApiResponse([ebEvent()]))
        )]);

        $events = ebScrapeToCollection(new TestEventbriteScraper, $defaultSourceConfig, $defaultCityConfig);

        expect($events->first()->city)->toBe('Timișoara');
    });

    it('maps venue name to venue', function () use ($defaultSourceConfig, $defaultCityConfig) {
        Http::fake(['https://www.eventbriteapi.com/v3/events/search/*' => Http::response(
            json_encode(ebApiResponse([ebEvent()]))
        )]);

        $events = ebScrapeToCollection(new TestEventbriteScraper, $defaultSourceConfig, $defaultCityConfig);

        expect($events->first()->venue)->toBe('Club Doors');
    });

    it('maps venue.address.localized_address_display to address', function () use ($defaultSourceConfig, $defaultCityConfig) {
        Http::fake(['https://www.eventbriteapi.com/v3/events/search/*' => Http::response(
            json_encode(ebApiResponse([ebEvent()]))
        )]);

        $events = ebScrapeToCollection(new TestEventbriteScraper, $defaultSourceConfig, $defaultCityConfig);

        expect($events->first()->address)->toBe('Str. Mercy 3, Timisoara');
    });

    it('maps logo.url to imageUrl', function () use ($defaultSourceConfig, $defaultCityConfig) {
        Http::fake(['https://www.eventbriteapi.com/v3/events/search/*' => Http::response(
            json_encode(ebApiResponse([ebEvent(['logo' => ['url' => 'https://img.evbstatic.com/photo.jpg']])]))
        )]);

        $events = ebScrapeToCollection(new TestEventbriteScraper, $defaultSourceConfig, $defaultCityConfig);

        expect($events->first()->imageUrl)->toBe('https://img.evbstatic.com/photo.jpg');
    });

    it('maps start.utc to startsAt', function () use ($defaultSourceConfig, $defaultCityConfig) {
        Http::fake(['https://www.eventbriteapi.com/v3/events/search/*' => Http::response(
            json_encode(ebApiResponse([ebEvent(['start' => ['utc' => '2026-06-15T20:00:00Z']])]))
        )]);

        $events = ebScrapeToCollection(new TestEventbriteScraper, $defaultSourceConfig, $defaultCityConfig);

        $startsAt = Carbon::parse($events->first()->startsAt);
        expect($startsAt->toDateString())->toBe('2026-06-15')
            ->and($startsAt->hour)->toBe(20);
    });

    it('maps end.utc to endsAt', function () use ($defaultSourceConfig, $defaultCityConfig) {
        Http::fake(['https://www.eventbriteapi.com/v3/events/search/*' => Http::response(
            json_encode(ebApiResponse([ebEvent(['end' => ['utc' => '2026-06-15T23:00:00Z']])]))
        )]);

        $events = ebScrapeToCollection(new TestEventbriteScraper, $defaultSourceConfig, $defaultCityConfig);

        $endsAt = Carbon::parse($events->first()->endsAt);
        expect($endsAt->toDateString())->toBe('2026-06-15')
            ->and($endsAt->hour)->toBe(23);
    });

    it('maps description.text to description', function () use ($defaultSourceConfig, $defaultCityConfig) {
        Http::fake(['https://www.eventbriteapi.com/v3/events/search/*' => Http::response(
            json_encode(ebApiResponse([ebEvent(['description' => ['text' => 'Full event description.']])]))
        )]);

        $events = ebScrapeToCollection(new TestEventbriteScraper, $defaultSourceConfig, $defaultCityConfig);

        expect($events->first()->description)->toBe('Full event description.');
    });

    it('falls back to summary when description.text is absent', function () use ($defaultSourceConfig, $defaultCityConfig) {
        Http::fake(['https://www.eventbriteapi.com/v3/events/search/*' => Http::response(
            json_encode(ebApiResponse([ebEvent(['description' => ['text' => ''], 'summary' => 'Fallback summary'])]))
        )]);

        $events = ebScrapeToCollection(new TestEventbriteScraper, $defaultSourceConfig, $defaultCityConfig);

        expect($events->first()->description)->toBe('Fallback summary');
    });
});

// ---------------------------------------------------------------------------
// Pricing
// ---------------------------------------------------------------------------

describe('pricing', function () use ($defaultSourceConfig, $defaultCityConfig) {
    beforeEach(function () {
        config(['eventpulse.eventbrite_api_key' => 'test-key']);
    });

    it('maps ticket_availability prices to priceMin, priceMax, currency', function () use ($defaultSourceConfig, $defaultCityConfig) {
        Http::fake(['https://www.eventbriteapi.com/v3/events/search/*' => Http::response(
            json_encode(ebApiResponse([ebEvent()]))
        )]);

        $events = ebScrapeToCollection(new TestEventbriteScraper, $defaultSourceConfig, $defaultCityConfig);

        expect($events->first()->priceMin)->toBe(50.0)
            ->and($events->first()->priceMax)->toBe(100.0)
            ->and($events->first()->currency)->toBe('RON');
    });

    it('maps is_free:true to isFree', function () use ($defaultSourceConfig, $defaultCityConfig) {
        $freeEvent = ebEvent(['is_free' => true]);
        $freeEvent['ticket_availability'] = [];

        Http::fake(['https://www.eventbriteapi.com/v3/events/search/*' => Http::response(
            json_encode(ebApiResponse([$freeEvent]))
        )]);

        $events = ebScrapeToCollection(new TestEventbriteScraper, $defaultSourceConfig, $defaultCityConfig);

        expect($events->first()->isFree)->toBeTrue()
            ->and($events->first()->priceMin)->toBeNull()
            ->and($events->first()->priceMax)->toBeNull()
            ->and($events->first()->currency)->toBeNull();
    });
});

// ---------------------------------------------------------------------------
// Online / no-venue events
// ---------------------------------------------------------------------------

describe('online events', function () use ($defaultSourceConfig, $defaultCityConfig) {
    beforeEach(function () {
        config(['eventpulse.eventbrite_api_key' => 'test-key']);
    });

    it('emits event with null venue and address when no venue is present', function () use ($defaultSourceConfig, $defaultCityConfig) {
        $onlineEvent = ebEvent();
        unset($onlineEvent['venue']);

        Http::fake(['https://www.eventbriteapi.com/v3/events/search/*' => Http::response(
            json_encode(ebApiResponse([$onlineEvent]))
        )]);

        $events = ebScrapeToCollection(new TestEventbriteScraper, $defaultSourceConfig, $defaultCityConfig);

        expect($events)->toHaveCount(1)
            ->and($events->first()->venue)->toBeNull()
            ->and($events->first()->address)->toBeNull();
    });
});

// ---------------------------------------------------------------------------
// Pagination
// ---------------------------------------------------------------------------

describe('pagination', function () use ($defaultSourceConfig, $defaultCityConfig) {
    beforeEach(function () {
        config(['eventpulse.eventbrite_api_key' => 'test-key']);
    });

    it('fetches multiple pages until has_more_items is false', function () use ($defaultSourceConfig, $defaultCityConfig) {
        Http::fake([
            'https://www.eventbriteapi.com/v3/events/search/*' => Http::sequence()
                ->push(json_encode(ebApiResponse([
                    ebEvent(['id' => '1', 'name' => ['text' => 'Event A']]),
                    ebEvent(['id' => '2', 'name' => ['text' => 'Event B']]),
                ], hasMore: true)))
                ->push(json_encode(ebApiResponse([
                    ebEvent(['id' => '3', 'name' => ['text' => 'Event C']]),
                ], hasMore: false))),
        ]);

        $events = ebScrapeToCollection(new TestEventbriteScraper, $defaultSourceConfig, $defaultCityConfig);

        expect($events)->toHaveCount(3);
    });

    it('stops after max_pages even if has_more_items is true', function () use ($defaultSourceConfig, $defaultCityConfig) {
        config(['eventpulse.scrapers.max_pages' => 2]);

        Http::fake([
            'https://www.eventbriteapi.com/v3/events/search/*' => Http::sequence()
                ->push(json_encode(ebApiResponse([ebEvent(['id' => '1', 'name' => ['text' => 'Event 1']])], hasMore: true)))
                ->push(json_encode(ebApiResponse([ebEvent(['id' => '2', 'name' => ['text' => 'Event 2']])], hasMore: true)))
                ->push(json_encode(ebApiResponse([ebEvent(['id' => '3', 'name' => ['text' => 'Should not fetch']])]))),
        ]);

        $events = ebScrapeToCollection(new TestEventbriteScraper, $defaultSourceConfig, $defaultCityConfig);

        expect($events)->toHaveCount(2);

        config(['eventpulse.scrapers.max_pages' => 10]);
    });
});

// ---------------------------------------------------------------------------
// Error handling
// ---------------------------------------------------------------------------

describe('error handling', function () use ($defaultSourceConfig, $defaultCityConfig) {
    it('emits 0 events and makes no HTTP calls when API key is missing', function () use ($defaultSourceConfig, $defaultCityConfig) {
        config(['eventpulse.eventbrite_api_key' => null]);

        Http::fake();

        $events = ebScrapeToCollection(new TestEventbriteScraper, $defaultSourceConfig, $defaultCityConfig);

        expect($events)->toBeEmpty();
        Http::assertNothingSent();
    });

    it('stops and emits 0 events on 401 response', function () use ($defaultSourceConfig, $defaultCityConfig) {
        config(['eventpulse.eventbrite_api_key' => 'bad-key']);

        Http::fake(['https://www.eventbriteapi.com/v3/events/search/*' => Http::response('Unauthorized', 401)]);

        $events = ebScrapeToCollection(new TestEventbriteScraper, $defaultSourceConfig, $defaultCityConfig);

        expect($events)->toBeEmpty();
    });

    it('stops and emits 0 events on 500 response', function () use ($defaultSourceConfig, $defaultCityConfig) {
        config(['eventpulse.eventbrite_api_key' => 'test-key']);

        Http::fake(['https://www.eventbriteapi.com/v3/events/search/*' => Http::response('Server Error', 500)]);

        $events = ebScrapeToCollection(new TestEventbriteScraper, $defaultSourceConfig, $defaultCityConfig);

        expect($events)->toBeEmpty();
    });

    it('retries the page on 429 and emits events after retry', function () use ($defaultSourceConfig, $defaultCityConfig) {
        config(['eventpulse.eventbrite_api_key' => 'test-key']);

        $scraper = new TestEventbriteScraper;

        Http::fake([
            'https://www.eventbriteapi.com/v3/events/search/*' => Http::sequence()
                ->push('', 429, ['Retry-After' => '2'])
                ->push(json_encode(ebApiResponse([ebEvent()]))),
        ]);

        $events = ebScrapeToCollection($scraper, $defaultSourceConfig, $defaultCityConfig);

        expect($events)->toHaveCount(1)
            ->and($scraper->sleepCalls)->toBe(1)
            ->and($scraper->lastSleepSeconds)->toBe(2);
    });
});

// ---------------------------------------------------------------------------
// HTTP contract
// ---------------------------------------------------------------------------

describe('HTTP contract', function () use ($defaultSourceConfig, $defaultCityConfig) {
    beforeEach(function () {
        config(['eventpulse.eventbrite_api_key' => 'my-secret-key']);
    });

    it('sends Bearer token in Authorization header', function () use ($defaultSourceConfig, $defaultCityConfig) {
        Http::fake(['https://www.eventbriteapi.com/v3/events/search/*' => Http::response(
            json_encode(ebApiResponse([]))
        )]);

        ebScrapeToCollection(new TestEventbriteScraper, $defaultSourceConfig, $defaultCityConfig);

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer my-secret-key');
        });
    });

    it('sends location.address from sourceConfig params', function () use ($defaultSourceConfig, $defaultCityConfig) {
        Http::fake(['https://www.eventbriteapi.com/v3/events/search/*' => Http::response(
            json_encode(ebApiResponse([]))
        )]);

        ebScrapeToCollection(new TestEventbriteScraper, $defaultSourceConfig, $defaultCityConfig);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'location.address=Timisoara%2CRomania');
        });
    });

    it('sends location.within from cityConfig radius_km', function () use ($defaultSourceConfig, $defaultCityConfig) {
        Http::fake(['https://www.eventbriteapi.com/v3/events/search/*' => Http::response(
            json_encode(ebApiResponse([]))
        )]);

        ebScrapeToCollection(new TestEventbriteScraper, $defaultSourceConfig, $defaultCityConfig);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'location.within=25km');
        });
    });
});

// ---------------------------------------------------------------------------
// Adapter registered in config
// ---------------------------------------------------------------------------

describe('adapter registry', function () {
    it('is registered in the eventpulse adapter_registry config', function () {
        $registry = config('eventpulse.adapter_registry');

        expect($registry)->toHaveKey('eventbrite')
            ->and($registry['eventbrite'])->toBe(EventbriteScraper::class);
    });
});
