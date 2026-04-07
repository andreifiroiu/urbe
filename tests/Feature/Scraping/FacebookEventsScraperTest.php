<?php

declare(strict_types=1);

use App\DTOs\RawEvent;
use App\Models\ApifyUsageLog;
use App\Services\Scraping\Adapters\FacebookEventsScraper;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

// ---------------------------------------------------------------------------
// Test double — overrides slow/external operations
// ---------------------------------------------------------------------------

class TestFacebookEventsScraper extends FacebookEventsScraper
{
    public string $processOutput = '[]';

    public bool $processSuccess = true;

    public bool $budgetRemaining = true;

    public bool $apifyConfigured = true;

    protected function sleepBetweenPolls(int $seconds): void {}

    /** @param  list<string>  $command */
    protected function runProcess(array $command, int $timeout): array
    {
        return [
            'successful' => $this->processSuccess,
            'output' => $this->processOutput,
            'error' => $this->processSuccess ? '' : 'Process error',
        ];
    }

    protected function hasDailyBudgetRemaining(): bool
    {
        return $this->budgetRemaining;
    }

    protected function isApifyConfigured(): bool
    {
        return $this->apifyConfigured;
    }
}

// ---------------------------------------------------------------------------
// Fixture helpers — Apify
// ---------------------------------------------------------------------------

/**
 * Build a single Apify Facebook Events actor result item.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function fbApifyEvent(array $overrides = []): array
{
    return array_merge([
        'name' => 'Concert Phoenix',
        'description' => 'Cea mai tare trupa rock din Romania revine la Timisoara.',
        'startDate' => '2026-05-10T19:00:00.000Z',
        'endDate' => '2026-05-10T22:00:00.000Z',
        'url' => 'https://www.facebook.com/events/123456789/',
        'organizerName' => 'Phoenix Official',
        'image' => 'https://scontent.facebook.com/concert-phoenix.jpg',
        'usersGoing' => 500,
        'usersInterested' => 2000,
        'location' => [
            'name' => 'Sala Capitol',
            'address' => 'Str. Mărășești 2, Timișoara, Romania',
            'city' => 'Timișoara',
        ],
    ], $overrides);
}

/** @return array<string, mixed> */
function apifyStartRunResponse(string $runId = 'run-abc-123'): array
{
    return ['data' => ['id' => $runId, 'status' => 'RUNNING']];
}

/**
 * @param  string  $status  RUNNING | SUCCEEDED | FAILED
 * @return array<string, mixed>
 */
function apifyRunStatusResponse(string $status = 'SUCCEEDED', string $runId = 'run-abc-123'): array
{
    return [
        'data' => [
            'id' => $runId,
            'status' => $status,
            'usageTotalCostUsd' => 0.075,
            'stats' => ['durationMillis' => 45000],
        ],
    ];
}

// ---------------------------------------------------------------------------
// Fixture helpers — npm package
// ---------------------------------------------------------------------------

/**
 * Build a single facebook-event-scraper npm package result.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function fbNpmEvent(array $overrides = []): array
{
    return array_merge([
        'id' => '987654321',
        'name' => 'Jazz Night Timișoara',
        'description' => 'O seară de jazz în centrul orașului.',
        'startTimestamp' => 1746982800, // 2025-05-11 19:00:00 UTC
        'endTimestamp' => 1746993600,
        'location' => [
            'name' => 'Piața Unirii',
            'description' => 'Timișoara, Romania',
            'url' => 'https://www.facebook.com/pages/Piata-Unirii/12345',
        ],
        'photo' => ['imageUri' => 'https://scontent.facebook.com/jazz.jpg'],
        'ticketUrl' => null,
        'hosts' => [['name' => 'Jazz Club TM', 'url' => 'https://www.facebook.com/jazzclubTM']],
        'usersGoing' => 142,
        'usersInterested' => 523,
    ], $overrides);
}

// ---------------------------------------------------------------------------
// Scrape helper
// ---------------------------------------------------------------------------

/**
 * @return Collection<int, RawEvent>
 */
function fbScrapeToCollection(
    FacebookEventsScraper $scraper,
    array $source = [],
    array $city = [],
): Collection {
    Http::preventStrayRequests();

    $source = array_merge([
        'adapter' => 'facebook_events',
        'enabled' => false,
        'interval_hours' => 12,
        'params' => [
            'apify_actor' => 'apify/facebook-events-scraper',
            'apify_queries' => ['events in Timisoara'],
            'facebook_pages' => ['https://www.facebook.com/evenimente.timis/events/'],
            'npm_scraper_enabled' => false,
        ],
    ], $source);

    $city = array_merge([
        'label' => 'Timișoara',
        'timezone' => 'Europe/Bucharest',
        'coordinates' => [45.7489, 21.2087],
        'radius_km' => 25,
    ], $city);

    $events = collect();
    $scraper->scrape($source, $city, fn (RawEvent $e) => $events->push($e));

    return $events;
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('FacebookEventsScraper', function (): void {

    // -----------------------------------------------------------------------
    // Identity
    // -----------------------------------------------------------------------

    it('returns the correct adapter key', function (): void {
        expect((new TestFacebookEventsScraper)->adapterKey())->toBe('facebook_events');
    });

    it('returns the correct source identifier', function (): void {
        expect((new TestFacebookEventsScraper)->sourceIdentifier([]))->toBe('facebook_events@facebook.com');
    });

    it('is registered in the adapter registry', function (): void {
        expect(config('eventpulse.adapter_registry'))->toHaveKey('facebook_events');
    });

    // -----------------------------------------------------------------------
    // Strategy A — Apify: happy path
    // -----------------------------------------------------------------------

    it('emits events when Apify returns valid results', function (): void {
        Http::fake([
            'api.apify.com/v2/acts/*/runs' => Http::response(apifyStartRunResponse()),
            'api.apify.com/v2/actor-runs/run-abc-123' => Http::response(apifyRunStatusResponse()),
            'api.apify.com/v2/actor-runs/run-abc-123/dataset/items' => Http::response([fbApifyEvent()]),
        ]);

        $scraper = new TestFacebookEventsScraper;
        $events = fbScrapeToCollection($scraper);

        expect($events)->toHaveCount(1);
    });

    it('maps all Apify fields to RawEvent correctly', function (): void {
        Http::fake([
            'api.apify.com/v2/acts/*/runs' => Http::response(apifyStartRunResponse()),
            'api.apify.com/v2/actor-runs/*/dataset/items' => Http::response([fbApifyEvent()]),
            'api.apify.com/v2/actor-runs/*' => Http::response(apifyRunStatusResponse()),
        ]);

        $scraper = new TestFacebookEventsScraper;
        $events = fbScrapeToCollection($scraper);
        $event = $events->first();

        expect($event->title)->toBe('Concert Phoenix');
        expect($event->source)->toBe('facebook_events');
        expect($event->sourceUrl)->toBe('https://www.facebook.com/events/123456789/');
        expect($event->sourceId)->toBeNull();
        expect($event->venue)->toBe('Sala Capitol');
        expect($event->address)->toBe('Str. Mărășești 2, Timișoara, Romania');
        expect($event->city)->toBe('Timișoara');
        expect($event->description)->toBe('Cea mai tare trupa rock din Romania revine la Timisoara.');
        expect($event->imageUrl)->toBe('https://scontent.facebook.com/concert-phoenix.jpg');
        expect($event->startsAt)->toBe('2026-05-10 19:00:00');
        expect($event->endsAt)->toBe('2026-05-10 22:00:00');
        expect($event->isFree)->toBeNull();
        expect($event->metadata['users_going'])->toBe(500);
        expect($event->metadata['users_interested'])->toBe(2000);
        expect($event->metadata['organizer'])->toBe('Phoenix Official');
        expect($event->metadata['source_strategy'])->toBe('apify');
    });

    it('polls until SUCCEEDED before fetching items', function (): void {
        Http::fake([
            'api.apify.com/v2/acts/*/runs' => Http::response(apifyStartRunResponse()),
            'api.apify.com/v2/actor-runs/run-abc-123' => Http::sequence()
                ->push(apifyRunStatusResponse('RUNNING'))
                ->push(apifyRunStatusResponse('RUNNING'))
                ->push(apifyRunStatusResponse('SUCCEEDED')),
            'api.apify.com/v2/actor-runs/run-abc-123/dataset/items' => Http::response([fbApifyEvent()]),
        ]);

        $scraper = new TestFacebookEventsScraper;
        $events = fbScrapeToCollection($scraper);

        expect($events)->toHaveCount(1);
        // Start + 3 polls + dataset = 5 requests
        Http::assertSentCount(5);
    });

    it('filters out Apify events whose location is outside the target city', function (): void {
        Http::fake([
            'api.apify.com/v2/acts/*/runs' => Http::response(apifyStartRunResponse()),
            'api.apify.com/v2/actor-runs/*/dataset/items' => Http::response([
                fbApifyEvent(['location' => ['name' => 'Venue', 'address' => 'Cluj-Napoca', 'city' => 'Cluj-Napoca']]),
                fbApifyEvent(['name' => 'Timisoara Event', 'url' => 'https://www.facebook.com/events/111/']),
            ]),
            'api.apify.com/v2/actor-runs/*' => Http::response(apifyRunStatusResponse()),
        ]);

        $scraper = new TestFacebookEventsScraper;
        $events = fbScrapeToCollection($scraper);

        expect($events)->toHaveCount(1);
        expect($events->first()->title)->toBe('Timisoara Event');
    });

    it('runs each configured query as a separate Apify actor run', function (): void {
        Http::fake([
            'api.apify.com/v2/acts/*/runs' => Http::response(apifyStartRunResponse()),
            'api.apify.com/v2/actor-runs/*' => Http::response(apifyRunStatusResponse()),
            'api.apify.com/v2/actor-runs/*/dataset/items' => Http::response([fbApifyEvent()]),
        ]);

        $scraper = new TestFacebookEventsScraper;
        fbScrapeToCollection($scraper, [
            'params' => [
                'apify_queries' => ['events in Timisoara', 'concerte Timisoara'],
                'npm_scraper_enabled' => false,
            ],
        ]);

        // 2 queries × (start + poll + dataset) = 6 requests
        Http::assertSentCount(6);
    });

    it('skips Apify when daily budget is exhausted', function (): void {
        Http::fake();

        $scraper = new TestFacebookEventsScraper;
        $scraper->budgetRemaining = false;

        $events = fbScrapeToCollection($scraper);

        expect($events)->toHaveCount(0);
        Http::assertNothingSent();
    });

    it('skips Apify and makes no HTTP calls when isApifyConfigured returns false', function (): void {
        Http::fake();

        $scraper = new TestFacebookEventsScraper;
        $scraper->apifyConfigured = false;

        $events = fbScrapeToCollection($scraper);

        expect($events)->toHaveCount(0);
        Http::assertNothingSent();
    });

    it('returns null from Apify run when start run fails with non-200', function (): void {
        Http::fake([
            'api.apify.com/v2/acts/*/runs' => Http::response('Unauthorized', 401),
        ]);

        $scraper = new TestFacebookEventsScraper;
        $events = fbScrapeToCollection($scraper);

        expect($events)->toHaveCount(0);
    });

    it('falls back gracefully when Apify run times out (all polls return RUNNING)', function (): void {
        Http::fake([
            'api.apify.com/v2/acts/*/runs' => Http::response(apifyStartRunResponse()),
            // Always returns RUNNING → poll timeout
            'api.apify.com/v2/actor-runs/*' => Http::response(apifyRunStatusResponse('RUNNING')),
        ]);

        $scraper = new TestFacebookEventsScraper;
        $events = fbScrapeToCollection($scraper);

        // Timeout means 0 events but no exception
        expect($events)->toHaveCount(0);
    });

    it('returns 0 events when Apify run status is FAILED', function (): void {
        Http::fake([
            'api.apify.com/v2/acts/*/runs' => Http::response(apifyStartRunResponse()),
            'api.apify.com/v2/actor-runs/*' => Http::response(apifyRunStatusResponse('FAILED')),
        ]);

        $scraper = new TestFacebookEventsScraper;
        $events = fbScrapeToCollection($scraper);

        expect($events)->toHaveCount(0);
    });

    // -----------------------------------------------------------------------
    // Strategy B — npm scraper
    // -----------------------------------------------------------------------

    it('emits events from npm scraper when process succeeds', function (): void {
        Http::fake([
            'api.apify.com/*' => Http::response('', 503),
        ]);

        $scraper = new TestFacebookEventsScraper;
        $scraper->processOutput = json_encode([fbNpmEvent()]);

        $events = fbScrapeToCollection($scraper, [
            'params' => [
                'apify_queries' => ['events in Timisoara'],
                'facebook_pages' => ['https://www.facebook.com/eventi/events/'],
                'npm_scraper_enabled' => true,
            ],
        ]);

        expect($events)->toHaveCount(1);
    });

    it('maps all npm event fields to RawEvent correctly', function (): void {
        Http::fake([
            'api.apify.com/*' => Http::response('', 503),
        ]);

        $scraper = new TestFacebookEventsScraper;
        $scraper->processOutput = json_encode([fbNpmEvent()]);

        $events = fbScrapeToCollection($scraper, [
            'params' => [
                'apify_queries' => ['events in Timisoara'],
                'facebook_pages' => ['https://www.facebook.com/events/'],
                'npm_scraper_enabled' => true,
            ],
        ]);

        $event = $events->first();

        expect($event->title)->toBe('Jazz Night Timișoara');
        expect($event->source)->toBe('facebook_events');
        expect($event->sourceUrl)->toBe('https://www.facebook.com/events/987654321/');
        expect($event->sourceId)->toBe('987654321');
        expect($event->venue)->toBe('Piața Unirii');
        expect($event->imageUrl)->toBe('https://scontent.facebook.com/jazz.jpg');
        expect($event->metadata['users_going'])->toBe(142);
        expect($event->metadata['users_interested'])->toBe(523);
        expect($event->metadata['organizer'])->toBe('Jazz Club TM');
        expect($event->metadata['source_strategy'])->toBe('npm');
    });

    it('converts npm Unix timestamps to UTC datetime strings', function (): void {
        Http::fake([
            'api.apify.com/*' => Http::response('', 503),
        ]);

        $scraper = new TestFacebookEventsScraper;
        // 2026-05-11 19:00:00 UTC = 1778526000, 2026-05-11 22:00:00 UTC = 1778536800
        $scraper->processOutput = json_encode([fbNpmEvent(['startTimestamp' => 1778526000, 'endTimestamp' => 1778536800])]);

        $events = fbScrapeToCollection($scraper, [
            'params' => [
                'apify_queries' => ['events in Timisoara'],
                'facebook_pages' => ['https://www.facebook.com/events/'],
                'npm_scraper_enabled' => true,
            ],
        ]);

        expect($events->first()->startsAt)->toBe('2026-05-11 19:00:00');
        expect($events->first()->endsAt)->toBe('2026-05-11 22:00:00');
    });

    it('returns 0 events when npm process fails', function (): void {
        Http::fake([
            'api.apify.com/*' => Http::response('', 503),
        ]);

        $scraper = new TestFacebookEventsScraper;
        $scraper->processSuccess = false;

        $events = fbScrapeToCollection($scraper, [
            'params' => [
                'apify_queries' => ['events in Timisoara'],
                'facebook_pages' => ['https://www.facebook.com/events/'],
                'npm_scraper_enabled' => true,
            ],
        ]);

        expect($events)->toHaveCount(0);
    });

    it('returns 0 events when npm output is not valid JSON', function (): void {
        Http::fake([
            'api.apify.com/*' => Http::response('', 503),
        ]);

        $scraper = new TestFacebookEventsScraper;
        $scraper->processOutput = 'not-json-at-all';

        $events = fbScrapeToCollection($scraper, [
            'params' => [
                'apify_queries' => ['events in Timisoara'],
                'facebook_pages' => ['https://www.facebook.com/events/'],
                'npm_scraper_enabled' => true,
            ],
        ]);

        expect($events)->toHaveCount(0);
    });

    it('skips npm strategy when npm_scraper_enabled is false', function (): void {
        Http::fake([
            'api.apify.com/*' => Http::response('', 503),
        ]);

        $scraper = new TestFacebookEventsScraper;
        // If npm were called, processOutput would give us events — but it shouldn't be called
        $scraper->processOutput = json_encode([fbNpmEvent()]);

        $events = fbScrapeToCollection($scraper, [
            'params' => [
                'apify_queries' => ['events in Timisoara'],
                'facebook_pages' => ['https://www.facebook.com/events/'],
                'npm_scraper_enabled' => false,
            ],
        ]);

        expect($events)->toHaveCount(0);
    });

    // -----------------------------------------------------------------------
    // Strategy orchestration
    // -----------------------------------------------------------------------

    it('merges events from both strategies when both succeed', function (): void {
        Http::fake([
            'api.apify.com/v2/acts/*/runs' => Http::response(apifyStartRunResponse()),
            'api.apify.com/v2/actor-runs/*/dataset/items' => Http::response([fbApifyEvent()]),
            'api.apify.com/v2/actor-runs/*' => Http::response(apifyRunStatusResponse()),
        ]);

        $scraper = new TestFacebookEventsScraper;
        $scraper->processOutput = json_encode([fbNpmEvent()]);

        $events = fbScrapeToCollection($scraper, [
            'params' => [
                'apify_queries' => ['events in Timisoara'],
                'facebook_pages' => ['https://www.facebook.com/events/'],
                'npm_scraper_enabled' => true,
            ],
        ]);

        expect($events)->toHaveCount(2);
    });

    it('deduplicates the same event found by both strategies', function (): void {
        // Same title + date + venue from both Apify and npm
        $sharedTitle = 'Shared Event';
        $sharedDate = '2026-05-10T19:00:00.000Z';
        $sharedVenue = 'Sala Capitol';

        Http::fake([
            'api.apify.com/v2/acts/*/runs' => Http::response(apifyStartRunResponse()),
            'api.apify.com/v2/actor-runs/*' => Http::response(apifyRunStatusResponse()),
            'api.apify.com/v2/actor-runs/*/dataset/items' => Http::response([
                fbApifyEvent([
                    'name' => $sharedTitle,
                    'startDate' => $sharedDate,
                    'location' => ['name' => $sharedVenue, 'address' => 'Timișoara', 'city' => 'Timișoara'],
                    'url' => 'https://www.facebook.com/events/111/',
                ]),
            ]),
        ]);

        $npmOutput = [
            fbNpmEvent([
                'id' => '111',
                'name' => $sharedTitle,
                'startTimestamp' => 1746900000, // same date approximately
                'location' => ['name' => $sharedVenue, 'description' => 'Timișoara'],
            ]),
        ];

        $scraper = new TestFacebookEventsScraper;
        $scraper->processOutput = json_encode($npmOutput);

        $events = fbScrapeToCollection($scraper, [
            'params' => [
                'apify_queries' => ['events in Timisoara'],
                'facebook_pages' => ['https://www.facebook.com/events/'],
                'npm_scraper_enabled' => true,
            ],
        ]);

        expect($events)->toHaveCount(1);
    });

    it('returns empty collection when both strategies fail without throwing', function (): void {
        Http::fake([
            'api.apify.com/*' => Http::response('', 503),
        ]);

        $scraper = new TestFacebookEventsScraper;
        $scraper->processSuccess = false;

        $events = fbScrapeToCollection($scraper, [
            'params' => [
                'apify_queries' => ['events in Timisoara'],
                'facebook_pages' => ['https://www.facebook.com/events/'],
                'npm_scraper_enabled' => true,
            ],
        ]);

        expect($events)->toHaveCount(0);
    });

    it('returns Strategy B results when Strategy A fails', function (): void {
        Http::fake([
            'api.apify.com/v2/acts/*/runs' => Http::response('', 500),
        ]);

        $scraper = new TestFacebookEventsScraper;
        $scraper->processOutput = json_encode([fbNpmEvent()]);

        $events = fbScrapeToCollection($scraper, [
            'params' => [
                'apify_queries' => ['events in Timisoara'],
                'facebook_pages' => ['https://www.facebook.com/events/'],
                'npm_scraper_enabled' => true,
            ],
        ]);

        expect($events)->toHaveCount(1);
        expect($events->first()->metadata['source_strategy'])->toBe('npm');
    });

    // -----------------------------------------------------------------------
    // Popularity score
    // -----------------------------------------------------------------------

    it('calculates popularity_score as log2(going + interested×0.5 + 1)', function (): void {
        Http::fake([
            'api.apify.com/v2/acts/*/runs' => Http::response(apifyStartRunResponse()),
            'api.apify.com/v2/actor-runs/*/dataset/items' => Http::response([
                fbApifyEvent(['usersGoing' => 500, 'usersInterested' => 2000]),
            ]),
            'api.apify.com/v2/actor-runs/*' => Http::response(apifyRunStatusResponse()),
        ]);

        $scraper = new TestFacebookEventsScraper;
        $events = fbScrapeToCollection($scraper);

        $expected = round(log(500 + 2000 * 0.5 + 1, 2), 4);
        expect($events->first()->metadata['popularity_score'])->toBe($expected);
    });

    it('sets popularity_score to 0.0 when both going and interested are 0', function (): void {
        Http::fake([
            'api.apify.com/v2/acts/*/runs' => Http::response(apifyStartRunResponse()),
            'api.apify.com/v2/actor-runs/*/dataset/items' => Http::response([
                fbApifyEvent(['usersGoing' => 0, 'usersInterested' => 0]),
            ]),
            'api.apify.com/v2/actor-runs/*' => Http::response(apifyRunStatusResponse()),
        ]);

        $scraper = new TestFacebookEventsScraper;
        $events = fbScrapeToCollection($scraper);

        // log2(0 + 0 + 1) = log2(1) = 0
        expect($events->first()->metadata['popularity_score'])->toBe(0.0);
    });

    // -----------------------------------------------------------------------
    // Apify daily budget cap — requires real DB
    // -----------------------------------------------------------------------

    it('stops running queries once the daily Apify budget is exceeded', function (): void {
        uses(LazilyRefreshDatabase::class);

        // Pre-fill usage log with $4.90 spent today (budget is $5.00)
        ApifyUsageLog::create([
            'actor_id' => 'apify/facebook-events-scraper',
            'run_id' => 'existing-run',
            'query' => 'previous query',
            'events_returned' => 10,
            'cost_usd' => 4.90,
            'duration_seconds' => 30,
            'status' => 'SUCCEEDED',
        ]);

        config(['eventpulse.apify_daily_budget_usd' => 5.00]);

        // hasDailyBudgetRemaining() in the real class reads from DB
        // Use the real class (not TestFacebookEventsScraper) so budget check hits the DB
        $scraper = new class extends FacebookEventsScraper
        {
            protected function sleepBetweenPolls(int $seconds): void {}
        };

        Http::fake([
            // If Apify were called, these would return events
            'api.apify.com/v2/acts/*/runs' => Http::response(apifyStartRunResponse()),
            'api.apify.com/v2/actor-runs/*' => Http::response(apifyRunStatusResponse()),
            'api.apify.com/v2/actor-runs/*/dataset/items' => Http::response([fbApifyEvent()]),
        ]);

        config(['eventpulse.apify_api_token' => 'real-token']);

        $events = collect();
        $scraper->scrape(
            [
                'adapter' => 'facebook_events',
                'enabled' => false,
                'interval_hours' => 12,
                'params' => [
                    'apify_queries' => ['events in Timisoara'],
                    'npm_scraper_enabled' => false,
                ],
            ],
            ['label' => 'Timișoara', 'timezone' => 'Europe/Bucharest', 'coordinates' => [45.7489, 21.2087], 'radius_km' => 25],
            fn (RawEvent $e) => $events->push($e),
        );

        // Budget exceeded → Apify skipped → 0 events, no HTTP calls to Apify
        expect($events)->toHaveCount(0);
        Http::assertNothingSent();
    })->skip('Requires database — run separately with RefreshDatabase');

});
