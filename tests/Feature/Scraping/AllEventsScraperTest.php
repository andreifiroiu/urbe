<?php

declare(strict_types=1);

use App\DTOs\RawEvent;
use App\Services\Scraping\Adapters\AllEventsScraper;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

// ---------------------------------------------------------------------------
// Test double — suppresses sleeps so tests run instantly.
// ---------------------------------------------------------------------------

class TestAllEventsScraper extends AllEventsScraper
{
    protected function sleepBetweenRequests(): void {}

    protected function sleepOnRetry(): void {}
}

// ---------------------------------------------------------------------------
// Helper: run scrape() and collect all emitted RawEvents.
// ---------------------------------------------------------------------------

/**
 * @param  array<string, mixed>  $sourceConfig
 * @param  array<string, mixed>  $cityConfig
 * @return Collection<int, RawEvent>
 */
function alScrapeToCollection(AllEventsScraper $scraper, array $sourceConfig, array $cityConfig): Collection
{
    $events = collect();
    $scraper->scrape($sourceConfig, $cityConfig, fn ($e) => $events->push($e));

    return $events;
}

// ---------------------------------------------------------------------------
// API response fixture builders
// ---------------------------------------------------------------------------

/**
 * Build a successful API response with the given event array.
 *
 * @param  array<int, array<string, mixed>>  $events
 * @return array<string, mixed>
 */
function alApiResponse(array $events): array
{
    return ['error' => 0, 'message' => '', 'data' => $events];
}

/** Empty response — no more events. */
function alEmptyApiResponse(): array
{
    return ['error' => 0, 'message' => '', 'data' => []];
}

/**
 * Build a minimal event object matching the AllEvents.in API shape.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function alEvent(array $overrides = []): array
{
    return array_merge([
        'event_id' => '200029772007404',
        'eventname' => 'Food Truck Festival 2026',
        'start_time' => '1777032000',   // Fri Apr 24 2026 12:00 UTC
        'end_time' => '1777240800',     // Sun Apr 26 2026 (different from start)
        'location' => 'Piata Libertatii Timisoara',
        'venue' => [
            'city' => 'Timisoara',
            'full_address' => 'Piata Libertatii Timisoara, Timisoara, Romania',
            'latitude' => '45.774349',
            'longitude' => '21.230091',
        ],
        'event_url' => 'https://allevents.in/timisoara/food-truck-festival-2026/200029772007404',
        'banner_url' => 'https://cdn-ip.allevents.in/banners/banner.jpg',
        'thumb_url' => 'https://cdn-az.allevents.in/banners/thumb.jpg',
        'categories' => ['food-drinks', 'festivals'],
        'tags' => ['street food', 'Festival'],
        'timezone' => '+03:00',
    ], $overrides);
}

// ---------------------------------------------------------------------------
// Default config fixtures
// ---------------------------------------------------------------------------

$defaultSourceConfig = [
    'adapter' => 'allevents',
    'url' => 'https://allevents.in/timisoara/all',
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
    it('returns "allevents"', function () {
        expect((new TestAllEventsScraper)->adapterKey())->toBe('allevents');
    });
});

// ---------------------------------------------------------------------------
// sourceIdentifier
// ---------------------------------------------------------------------------

describe('sourceIdentifier', function () use ($defaultSourceConfig) {
    it('returns "allevents@allevents.in"', function () use ($defaultSourceConfig) {
        expect((new TestAllEventsScraper)->sourceIdentifier($defaultSourceConfig))
            ->toBe('allevents@allevents.in');
    });
});

// ---------------------------------------------------------------------------
// Basic field mapping
// ---------------------------------------------------------------------------

describe('field mapping', function () use ($defaultSourceConfig, $defaultCityConfig) {
    it('maps eventname to title', function () use ($defaultSourceConfig, $defaultCityConfig) {
        Http::fake(['https://allevents.in/api/events/list' => Http::sequence()
            ->push(alApiResponse([alEvent(['eventname' => 'Rock Concert 2026'])]))
            ->push(alEmptyApiResponse())]);

        $events = alScrapeToCollection(new TestAllEventsScraper, $defaultSourceConfig, $defaultCityConfig);

        expect($events->first()->title)->toBe('Rock Concert 2026');
    });

    it('maps event_id to sourceId', function () use ($defaultSourceConfig, $defaultCityConfig) {
        Http::fake(['https://allevents.in/api/events/list' => Http::sequence()
            ->push(alApiResponse([alEvent(['event_id' => '99988877766655'])]))
            ->push(alEmptyApiResponse())]);

        $events = alScrapeToCollection(new TestAllEventsScraper, $defaultSourceConfig, $defaultCityConfig);

        expect($events->first()->sourceId)->toBe('99988877766655');
    });

    it('maps event_url to sourceUrl', function () use ($defaultSourceConfig, $defaultCityConfig) {
        Http::fake(['https://allevents.in/api/events/list' => Http::sequence()
            ->push(alApiResponse([alEvent(['event_url' => 'https://allevents.in/timisoara/my-event/123'])]))
            ->push(alEmptyApiResponse())]);

        $events = alScrapeToCollection(new TestAllEventsScraper, $defaultSourceConfig, $defaultCityConfig);

        expect($events->first()->sourceUrl)->toBe('https://allevents.in/timisoara/my-event/123');
    });

    it('sets source to "allevents"', function () use ($defaultSourceConfig, $defaultCityConfig) {
        Http::fake(['https://allevents.in/api/events/list' => Http::sequence()
            ->push(alApiResponse([alEvent()]))
            ->push(alEmptyApiResponse())]);

        $events = alScrapeToCollection(new TestAllEventsScraper, $defaultSourceConfig, $defaultCityConfig);

        expect($events->first()->source)->toBe('allevents');
    });

    it('sets city from cityConfig label', function () use ($defaultSourceConfig, $defaultCityConfig) {
        Http::fake(['https://allevents.in/api/events/list' => Http::sequence()
            ->push(alApiResponse([alEvent()]))
            ->push(alEmptyApiResponse())]);

        $events = alScrapeToCollection(new TestAllEventsScraper, $defaultSourceConfig, $defaultCityConfig);

        expect($events->first()->city)->toBe('Timișoara');
    });

    it('maps location to venue', function () use ($defaultSourceConfig, $defaultCityConfig) {
        Http::fake(['https://allevents.in/api/events/list' => Http::sequence()
            ->push(alApiResponse([alEvent(['location' => 'Sala Capitol'])]))
            ->push(alEmptyApiResponse())]);

        $events = alScrapeToCollection(new TestAllEventsScraper, $defaultSourceConfig, $defaultCityConfig);

        expect($events->first()->venue)->toBe('Sala Capitol');
    });

    it('maps venue.full_address to address', function () use ($defaultSourceConfig, $defaultCityConfig) {
        Http::fake(['https://allevents.in/api/events/list' => Http::sequence()
            ->push(alApiResponse([alEvent()]))
            ->push(alEmptyApiResponse())]);

        $events = alScrapeToCollection(new TestAllEventsScraper, $defaultSourceConfig, $defaultCityConfig);

        expect($events->first()->address)->toBe('Piata Libertatii Timisoara, Timisoara, Romania');
    });

    it('prefers banner_url over thumb_url for imageUrl', function () use ($defaultSourceConfig, $defaultCityConfig) {
        Http::fake(['https://allevents.in/api/events/list' => Http::sequence()
            ->push(alApiResponse([alEvent([
                'banner_url' => 'https://cdn/banner.jpg',
                'thumb_url' => 'https://cdn/thumb.jpg',
            ])]))
            ->push(alEmptyApiResponse())]);

        $events = alScrapeToCollection(new TestAllEventsScraper, $defaultSourceConfig, $defaultCityConfig);

        expect($events->first()->imageUrl)->toBe('https://cdn/banner.jpg');
    });

    it('falls back to thumb_url when banner_url is absent', function () use ($defaultSourceConfig, $defaultCityConfig) {
        Http::fake(['https://allevents.in/api/events/list' => Http::sequence()
            ->push(alApiResponse([alEvent(['banner_url' => '', 'thumb_url' => 'https://cdn/thumb.jpg'])]))
            ->push(alEmptyApiResponse())]);

        $events = alScrapeToCollection(new TestAllEventsScraper, $defaultSourceConfig, $defaultCityConfig);

        expect($events->first()->imageUrl)->toBe('https://cdn/thumb.jpg');
    });

    it('stores categories and tags in metadata', function () use ($defaultSourceConfig, $defaultCityConfig) {
        Http::fake(['https://allevents.in/api/events/list' => Http::sequence()
            ->push(alApiResponse([alEvent(['categories' => ['music', 'concerts'], 'tags' => ['jazz', 'live']])]))
            ->push(alEmptyApiResponse())]);

        $events = alScrapeToCollection(new TestAllEventsScraper, $defaultSourceConfig, $defaultCityConfig);

        expect($events->first()->metadata['categories'])->toBe(['music', 'concerts'])
            ->and($events->first()->metadata['tags'])->toBe(['jazz', 'live']);
    });

    it('sets priceMin, priceMax, and currency to null (not in API response)', function () use ($defaultSourceConfig, $defaultCityConfig) {
        Http::fake(['https://allevents.in/api/events/list' => Http::sequence()
            ->push(alApiResponse([alEvent()]))
            ->push(alEmptyApiResponse())]);

        $events = alScrapeToCollection(new TestAllEventsScraper, $defaultSourceConfig, $defaultCityConfig);

        expect($events->first()->priceMin)->toBeNull()
            ->and($events->first()->priceMax)->toBeNull()
            ->and($events->first()->currency)->toBeNull();
    });
});

// ---------------------------------------------------------------------------
// Date/time parsing from Unix timestamps
// ---------------------------------------------------------------------------

describe('timestamp parsing', function () use ($defaultSourceConfig, $defaultCityConfig) {
    it('converts start_time Unix timestamp to startsAt datetime string', function () use ($defaultSourceConfig, $defaultCityConfig) {
        Http::fake(['https://allevents.in/api/events/list' => Http::sequence()
            ->push(alApiResponse([alEvent(['start_time' => '1777032000', 'end_time' => '1777032000'])]))
            ->push(alEmptyApiResponse())]);

        $events = alScrapeToCollection(new TestAllEventsScraper, $defaultSourceConfig, $defaultCityConfig);

        $startsAt = Carbon::parse($events->first()->startsAt);
        expect($startsAt->timestamp)->toBe(1777032000);
    });

    it('sets endsAt when end_time differs from start_time', function () use ($defaultSourceConfig, $defaultCityConfig) {
        Http::fake(['https://allevents.in/api/events/list' => Http::sequence()
            ->push(alApiResponse([alEvent(['start_time' => '1777032000', 'end_time' => '1777240800'])]))
            ->push(alEmptyApiResponse())]);

        $events = alScrapeToCollection(new TestAllEventsScraper, $defaultSourceConfig, $defaultCityConfig);

        $endsAt = Carbon::parse($events->first()->endsAt);
        expect($endsAt->timestamp)->toBe(1777240800);
    });

    it('sets endsAt to null when end_time equals start_time', function () use ($defaultSourceConfig, $defaultCityConfig) {
        Http::fake(['https://allevents.in/api/events/list' => Http::sequence()
            ->push(alApiResponse([alEvent(['start_time' => '1777032000', 'end_time' => '1777032000'])]))
            ->push(alEmptyApiResponse())]);

        $events = alScrapeToCollection(new TestAllEventsScraper, $defaultSourceConfig, $defaultCityConfig);

        expect($events->first()->endsAt)->toBeNull();
    });

    it('sets startsAt to null when start_time is 0', function () use ($defaultSourceConfig, $defaultCityConfig) {
        Http::fake(['https://allevents.in/api/events/list' => Http::sequence()
            ->push(alApiResponse([alEvent(['start_time' => '0', 'end_time' => '0'])]))
            ->push(alEmptyApiResponse())]);

        $events = alScrapeToCollection(new TestAllEventsScraper, $defaultSourceConfig, $defaultCityConfig);

        expect($events->first()->startsAt)->toBeNull()
            ->and($events->first()->endsAt)->toBeNull();
    });
});

// ---------------------------------------------------------------------------
// City prefix stripping
// ---------------------------------------------------------------------------

describe('city prefix stripping', function () use ($defaultSourceConfig, $defaultCityConfig) {
    it('strips "TIMISOARA: " prefix from title', function () use ($defaultSourceConfig, $defaultCityConfig) {
        Http::fake(['https://allevents.in/api/events/list' => Http::sequence()
            ->push(alApiResponse([alEvent(['eventname' => 'TIMISOARA: Stand-up Show'])]))
            ->push(alEmptyApiResponse())]);

        $events = alScrapeToCollection(new TestAllEventsScraper, $defaultSourceConfig, $defaultCityConfig);

        expect($events->first()->title)->toBe('Stand-up Show');
    });

    it('strips "TIMIȘOARA: " prefix (with diacritic) from title', function () use ($defaultSourceConfig, $defaultCityConfig) {
        Http::fake(['https://allevents.in/api/events/list' => Http::sequence()
            ->push(alApiResponse([alEvent(['eventname' => 'TIMIȘOARA: Concert XYZ'])]))
            ->push(alEmptyApiResponse())]);

        $events = alScrapeToCollection(new TestAllEventsScraper, $defaultSourceConfig, $defaultCityConfig);

        expect($events->first()->title)->toBe('Concert XYZ');
    });

    it('does not strip city when it appears mid-title', function () use ($defaultSourceConfig, $defaultCityConfig) {
        Http::fake(['https://allevents.in/api/events/list' => Http::sequence()
            ->push(alApiResponse([alEvent(['eventname' => 'FuN Timișoara: The Comeback Edition'])]))
            ->push(alEmptyApiResponse())]);

        $events = alScrapeToCollection(new TestAllEventsScraper, $defaultSourceConfig, $defaultCityConfig);

        expect($events->first()->title)->toBe('FuN Timișoara: The Comeback Edition');
    });
});

// ---------------------------------------------------------------------------
// Pagination
// ---------------------------------------------------------------------------

describe('pagination', function () use ($defaultSourceConfig, $defaultCityConfig) {
    it('fetches multiple pages and stops when data array is empty', function () use ($defaultSourceConfig, $defaultCityConfig) {
        Http::fake([
            'https://allevents.in/api/events/list' => Http::sequence()
                ->push(alApiResponse([
                    alEvent(['event_id' => '1', 'eventname' => 'Event A']),
                    alEvent(['event_id' => '2', 'eventname' => 'Event B']),
                ]))
                ->push(alApiResponse([
                    alEvent(['event_id' => '3', 'eventname' => 'Event C']),
                ]))
                ->push(alEmptyApiResponse()),
        ]);

        $events = alScrapeToCollection(new TestAllEventsScraper, $defaultSourceConfig, $defaultCityConfig);

        expect($events)->toHaveCount(3);
    });

    it('stops after max_pages even if pages still have events', function () use ($defaultSourceConfig, $defaultCityConfig) {
        config(['eventpulse.scrapers.max_pages' => 2]);

        Http::fake([
            'https://allevents.in/api/events/list' => Http::sequence()
                ->push(alApiResponse([alEvent(['event_id' => '1', 'eventname' => 'Event 1'])]))
                ->push(alApiResponse([alEvent(['event_id' => '2', 'eventname' => 'Event 2'])]))
                ->push(alApiResponse([alEvent(['event_id' => '3', 'eventname' => 'Should not be fetched'])])),
        ]);

        $events = alScrapeToCollection(new TestAllEventsScraper, $defaultSourceConfig, $defaultCityConfig);

        expect($events)->toHaveCount(2);

        config(['eventpulse.scrapers.max_pages' => 10]);
    });

    it('stops immediately on HTTP error', function () use ($defaultSourceConfig, $defaultCityConfig) {
        Http::fake(['https://allevents.in/api/events/list' => Http::response('', 500)]);

        $events = alScrapeToCollection(new TestAllEventsScraper, $defaultSourceConfig, $defaultCityConfig);

        expect($events)->toBeEmpty();
    });

    it('stops when API returns error != 0', function () use ($defaultSourceConfig, $defaultCityConfig) {
        Http::fake([
            'https://allevents.in/api/events/list' => Http::response(
                json_encode(['error' => 1, 'message' => 'City not found', 'data' => []]),
                200,
            ),
        ]);

        $events = alScrapeToCollection(new TestAllEventsScraper, $defaultSourceConfig, $defaultCityConfig);

        expect($events)->toBeEmpty();
    });
});

// ---------------------------------------------------------------------------
// City slug extraction (parameterized)
// ---------------------------------------------------------------------------

describe('parameterized city URL', function () use ($defaultCityConfig) {
    it('uses city slug from sourceConfig URL in the POST payload', function () use ($defaultCityConfig) {
        $clujSourceConfig = [
            'adapter' => 'allevents',
            'url' => 'https://allevents.in/cluj-napoca/all',
            'enabled' => true,
            'interval_hours' => 6,
        ];
        $clujCityConfig = array_merge($defaultCityConfig, ['label' => 'Cluj-Napoca']);

        Http::fake([
            'https://allevents.in/api/events/list' => Http::sequence()
                ->push(alApiResponse([alEvent(['eventname' => 'Cluj Event'])]))
                ->push(alEmptyApiResponse()),
        ]);

        $events = alScrapeToCollection(new TestAllEventsScraper, $clujSourceConfig, $clujCityConfig);

        expect($events)->toHaveCount(1)
            ->and($events->first()->city)->toBe('Cluj-Napoca');

        // Verify that POST was sent with city=cluj-napoca
        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return $request->url() === 'https://allevents.in/api/events/list'
                && ($body['city'] ?? '') === 'cluj-napoca';
        });
    });

    it('uses country from sourceConfig when provided', function () use ($defaultCityConfig) {
        $sourceConfig = [
            'adapter' => 'allevents',
            'url' => 'https://allevents.in/timisoara/all',
            'country' => 'romania',
            'enabled' => true,
            'interval_hours' => 6,
        ];

        Http::fake([
            'https://allevents.in/api/events/list' => Http::sequence()
                ->push(alApiResponse([alEvent()]))
                ->push(alEmptyApiResponse()),
        ]);

        alScrapeToCollection(new TestAllEventsScraper, $sourceConfig, $defaultCityConfig);

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return ($body['country'] ?? '') === 'romania';
        });
    });
});

// ---------------------------------------------------------------------------
// Skips events with missing required fields
// ---------------------------------------------------------------------------

describe('skips invalid events', function () use ($defaultSourceConfig, $defaultCityConfig) {
    it('skips events with empty eventname', function () use ($defaultSourceConfig, $defaultCityConfig) {
        Http::fake(['https://allevents.in/api/events/list' => Http::sequence()
            ->push(alApiResponse([
                alEvent(['eventname' => '']),
                alEvent(['event_id' => '999', 'eventname' => 'Valid Event']),
            ]))
            ->push(alEmptyApiResponse())]);

        $events = alScrapeToCollection(new TestAllEventsScraper, $defaultSourceConfig, $defaultCityConfig);

        expect($events)->toHaveCount(1)
            ->and($events->first()->title)->toBe('Valid Event');
    });

    it('skips events with empty event_url', function () use ($defaultSourceConfig, $defaultCityConfig) {
        Http::fake(['https://allevents.in/api/events/list' => Http::sequence()
            ->push(alApiResponse([
                alEvent(['event_url' => '']),
                alEvent(['event_id' => '999', 'eventname' => 'Valid Event']),
            ]))
            ->push(alEmptyApiResponse())]);

        $events = alScrapeToCollection(new TestAllEventsScraper, $defaultSourceConfig, $defaultCityConfig);

        expect($events)->toHaveCount(1)
            ->and($events->first()->title)->toBe('Valid Event');
    });
});

// ---------------------------------------------------------------------------
// Adapter registered in config
// ---------------------------------------------------------------------------

describe('adapter registry', function () {
    it('is registered in the eventpulse adapter_registry config', function () {
        $registry = config('eventpulse.adapter_registry');

        expect($registry)->toHaveKey('allevents')
            ->and($registry['allevents'])->toBe(AllEventsScraper::class);
    });
});
