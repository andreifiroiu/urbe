<?php

declare(strict_types=1);

use App\DTOs\RawEvent;
use App\Services\Scraping\Adapters\MeetupScraper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

// ---------------------------------------------------------------------------
// Test double — suppresses HTTP delays
// ---------------------------------------------------------------------------

class TestMeetupScraper extends MeetupScraper
{
    protected function sleepBetweenRequests(): void {}

    protected function sleepOnRetry(): void {}
}

// ---------------------------------------------------------------------------
// Fixture helpers
// ---------------------------------------------------------------------------

/**
 * Build a single __NEXT_DATA__ event array.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function meetupEvent(array $overrides = []): array
{
    return array_merge([
        '__typename' => 'Event',
        'id' => '313614908',
        'title' => 'AWS Community Day Romania',
        'eventUrl' => 'https://www.meetup.com/aws-romania/events/313614908/',
        'eventType' => 'PHYSICAL',
        'dateTime' => '2026-04-23T10:00:00+03:00',
        'endTime' => '2026-04-23T18:00:00+03:00',
        'isOnline' => false,
        'going' => ['totalCount' => 142],
        'feeSettings' => null,
        'featuredEventPhoto' => ['highResUrl' => 'https://secure.meetupstatic.com/featured.jpg'],
        'displayPhoto' => ['highResUrl' => 'https://secure.meetupstatic.com/display.jpg'],
        'venue' => ['__typename' => 'Venue', 'name' => 'The Place', 'city' => 'Timisoara'],
        'group' => ['id' => '12345', 'name' => 'AWS Romania', 'urlname' => 'aws-romania', 'timezone' => 'Europe/Bucharest'],
    ], $overrides);
}

/**
 * Build a minimal HTML page containing __NEXT_DATA__ JSON with the given events.
 *
 * @param  list<array<string, mixed>>  $events
 */
function nextDataHtml(array $events): string
{
    $json = json_encode([
        'props' => [
            'pageProps' => [
                'eventsInLocation' => $events,
            ],
        ],
    ]);

    return <<<HTML
    <!DOCTYPE html>
    <html><head><meta charset="utf-8"><title>Find Events | Meetup</title></head>
    <body>
    <script id="__NEXT_DATA__" type="application/json">{$json}</script>
    </body></html>
    HTML;
}

/**
 * Build a valid GraphQL keywordSearch response JSON string.
 *
 * @param  list<array<string, mixed>>  $nodes
 */
function gqlResponse(array $nodes): string
{
    $edges = array_map(fn ($node) => ['node' => $node], $nodes);

    return (string) json_encode([
        'data' => [
            'keywordSearch' => [
                'edges' => $edges,
            ],
        ],
    ]);
}

/**
 * Build a GraphQL node (different shape from __NEXT_DATA__).
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function gqlNode(array $overrides = []): array
{
    return array_merge([
        'id' => '313614908',
        'title' => 'AWS Community Day Romania',
        'dateTime' => '2026-04-23T10:00:00+03:00',
        'endTime' => '2026-04-23T18:00:00+03:00',
        'eventUrl' => 'https://www.meetup.com/aws-romania/events/313614908/',
        'description' => ['truncatedDescription' => 'A great event for AWS folks.'],
        'images' => [['baseUrl' => 'https://secure.meetupstatic.com/gql-image.jpg']],
        'venue' => ['name' => 'The Place'],
        'group' => ['name' => 'AWS Romania', 'urlname' => 'aws-romania'],
        'going' => 142,
        'feeSettings' => null,
    ], $overrides);
}

/**
 * Run a scrape and return all emitted RawEvents.
 *
 * @return Collection<int, RawEvent>
 */
function mkScrapeToCollection(
    MeetupScraper $scraper,
    array $source = [],
    array $city = [],
): Collection {
    Http::preventStrayRequests();

    $source = array_merge([
        'adapter' => 'meetup',
        'params' => ['location' => 'ro--timisoara'],
        'enabled' => true,
        'interval_hours' => 6,
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

describe('MeetupScraper', function (): void {

    it('returns the correct adapter key', function (): void {
        expect((new TestMeetupScraper)->adapterKey())->toBe('meetup');
    });

    it('returns the correct source identifier', function (): void {
        $scraper = new TestMeetupScraper;
        expect($scraper->sourceIdentifier(['params' => ['location' => 'ro--timisoara']]))->toBe('meetup@meetup.com');
    });

    it('is registered in the adapter registry', function (): void {
        expect(config('eventpulse.adapter_registry'))->toHaveKey('meetup');
    });

    // -----------------------------------------------------------------------
    // GraphQL path (Path A)
    // -----------------------------------------------------------------------

    it('emits events from GraphQL when the endpoint returns valid data', function (): void {
        Http::fake([
            'www.meetup.com/gql' => Http::response(gqlResponse([gqlNode()])),
            'www.meetup.com/find/*' => Http::response('should not be called'),
        ]);

        $events = mkScrapeToCollection(new TestMeetupScraper);

        expect($events)->toHaveCount(1);
        Http::assertSentCount(1); // Only GQL, no HTML fetch
    });

    it('falls back to __NEXT_DATA__ when GraphQL returns 404', function (): void {
        Http::fake([
            'www.meetup.com/gql' => Http::response('Not Found', 404),
            'www.meetup.com/find/*' => Http::response(nextDataHtml([meetupEvent()])),
        ]);

        $events = mkScrapeToCollection(new TestMeetupScraper);

        expect($events)->toHaveCount(1);
        Http::assertSentCount(2); // GQL attempt + HTML fetch
    });

    it('falls back to __NEXT_DATA__ when GraphQL returns 200 but no edges', function (): void {
        Http::fake([
            'www.meetup.com/gql' => Http::response(json_encode(['data' => ['keywordSearch' => ['edges' => []]]])),
            'www.meetup.com/find/*' => Http::response(nextDataHtml([meetupEvent()])),
        ]);

        $events = mkScrapeToCollection(new TestMeetupScraper);

        expect($events)->toHaveCount(1);
    });

    it('maps GraphQL node fields correctly', function (): void {
        Http::fake([
            'www.meetup.com/gql' => Http::response(gqlResponse([gqlNode()])),
        ]);

        $events = mkScrapeToCollection(new TestMeetupScraper);
        $event = $events->first();

        expect($event->title)->toBe('AWS Community Day Romania');
        expect($event->source)->toBe('meetup');
        expect($event->sourceUrl)->toBe('https://www.meetup.com/aws-romania/events/313614908/');
        expect($event->sourceId)->toBe('313614908');
        expect($event->venue)->toBe('The Place');
        expect($event->city)->toBe('Timișoara');
        expect($event->description)->toBe('A great event for AWS folks.');
        expect($event->imageUrl)->toBe('https://secure.meetupstatic.com/gql-image.jpg');
        expect($event->isFree)->toBeTrue();
        expect($event->metadata['group_name'])->toBe('AWS Romania');
        expect($event->metadata['rsvp_count'])->toBe(142);
    });

    // -----------------------------------------------------------------------
    // __NEXT_DATA__ path (Path B)
    // -----------------------------------------------------------------------

    it('maps all fields from a __NEXT_DATA__ event correctly', function (): void {
        Http::fake([
            'www.meetup.com/gql' => Http::response('', 404),
            'www.meetup.com/find/*' => Http::response(nextDataHtml([meetupEvent()])),
        ]);

        $events = mkScrapeToCollection(new TestMeetupScraper);

        expect($events)->toHaveCount(1);

        $event = $events->first();
        expect($event->title)->toBe('AWS Community Day Romania');
        expect($event->source)->toBe('meetup');
        expect($event->sourceUrl)->toBe('https://www.meetup.com/aws-romania/events/313614908/');
        expect($event->sourceId)->toBe('313614908');
        expect($event->venue)->toBe('The Place');
        expect($event->city)->toBe('Timișoara');
        expect($event->address)->toBeNull();
        expect($event->description)->toBeNull();
        expect($event->isFree)->toBeTrue();
        expect($event->priceMin)->toBeNull();
        expect($event->currency)->toBeNull();
    });

    it('parses startsAt from ISO 8601 with offset to UTC', function (): void {
        // 2026-04-23T10:00:00+03:00 → UTC 07:00:00
        Http::fake([
            'www.meetup.com/gql' => Http::response('', 404),
            'www.meetup.com/find/*' => Http::response(nextDataHtml([meetupEvent(['dateTime' => '2026-04-23T10:00:00+03:00'])])),
        ]);

        $events = mkScrapeToCollection(new TestMeetupScraper);

        expect($events->first()->startsAt)->toBe('2026-04-23 07:00:00');
    });

    it('parses endsAt from ISO 8601 to UTC', function (): void {
        // 2026-04-23T18:00:00+03:00 → UTC 15:00:00
        Http::fake([
            'www.meetup.com/gql' => Http::response('', 404),
            'www.meetup.com/find/*' => Http::response(nextDataHtml([meetupEvent(['endTime' => '2026-04-23T18:00:00+03:00'])])),
        ]);

        $events = mkScrapeToCollection(new TestMeetupScraper);

        expect($events->first()->endsAt)->toBe('2026-04-23 15:00:00');
    });

    it('sets isFree to true when feeSettings is null', function (): void {
        Http::fake([
            'www.meetup.com/gql' => Http::response('', 404),
            'www.meetup.com/find/*' => Http::response(nextDataHtml([meetupEvent(['feeSettings' => null])])),
        ]);

        $events = mkScrapeToCollection(new TestMeetupScraper);

        expect($events->first()->isFree)->toBeTrue();
    });

    it('sets isFree to false when feeSettings is non-null', function (): void {
        Http::fake([
            'www.meetup.com/gql' => Http::response('', 404),
            'www.meetup.com/find/*' => Http::response(nextDataHtml([meetupEvent(['feeSettings' => ['amount' => ['amount' => 10, 'currency' => 'RON']]])])),
        ]);

        $events = mkScrapeToCollection(new TestMeetupScraper);

        expect($events->first()->isFree)->toBeFalse();
    });

    it('uses featuredEventPhoto as primary imageUrl', function (): void {
        Http::fake([
            'www.meetup.com/gql' => Http::response('', 404),
            'www.meetup.com/find/*' => Http::response(nextDataHtml([meetupEvent([
                'featuredEventPhoto' => ['highResUrl' => 'https://secure.meetupstatic.com/featured.jpg'],
                'displayPhoto' => ['highResUrl' => 'https://secure.meetupstatic.com/display.jpg'],
            ])])),
        ]);

        $events = mkScrapeToCollection(new TestMeetupScraper);

        expect($events->first()->imageUrl)->toBe('https://secure.meetupstatic.com/featured.jpg');
    });

    it('falls back to displayPhoto when featuredEventPhoto is absent', function (): void {
        Http::fake([
            'www.meetup.com/gql' => Http::response('', 404),
            'www.meetup.com/find/*' => Http::response(nextDataHtml([meetupEvent([
                'featuredEventPhoto' => null,
                'displayPhoto' => ['highResUrl' => 'https://secure.meetupstatic.com/display.jpg'],
            ])])),
        ]);

        $events = mkScrapeToCollection(new TestMeetupScraper);

        expect($events->first()->imageUrl)->toBe('https://secure.meetupstatic.com/display.jpg');
    });

    it('stores group_name, rsvp_count, and event_type in metadata', function (): void {
        Http::fake([
            'www.meetup.com/gql' => Http::response('', 404),
            'www.meetup.com/find/*' => Http::response(nextDataHtml([meetupEvent()])),
        ]);

        $events = mkScrapeToCollection(new TestMeetupScraper);
        $meta = $events->first()->metadata;

        expect($meta['group_name'])->toBe('AWS Romania');
        expect($meta['rsvp_count'])->toBe(142);
        expect($meta['event_type'])->toBe('PHYSICAL');
    });

    it('emits all events from eventsInLocation in order', function (): void {
        Http::fake([
            'www.meetup.com/gql' => Http::response('', 404),
            'www.meetup.com/find/*' => Http::response(nextDataHtml([
                meetupEvent(['id' => '1', 'title' => 'Event Alpha']),
                meetupEvent(['id' => '2', 'title' => 'Event Beta']),
                meetupEvent(['id' => '3', 'title' => 'Event Gamma']),
            ])),
        ]);

        $events = mkScrapeToCollection(new TestMeetupScraper);

        expect($events)->toHaveCount(3);
        expect($events->pluck('title')->all())->toBe(['Event Alpha', 'Event Beta', 'Event Gamma']);
    });

    it('emits 0 events when eventsInLocation is empty', function (): void {
        Http::fake([
            'www.meetup.com/gql' => Http::response('', 404),
            'www.meetup.com/find/*' => Http::response(nextDataHtml([])),
        ]);

        $events = mkScrapeToCollection(new TestMeetupScraper);

        expect($events)->toHaveCount(0);
        Http::assertSentCount(2);
    });

    it('emits 0 events when HTML has no __NEXT_DATA__ script', function (): void {
        Http::fake([
            'www.meetup.com/gql' => Http::response('', 404),
            'www.meetup.com/find/*' => Http::response('<html><body>No data here</body></html>'),
        ]);

        $events = mkScrapeToCollection(new TestMeetupScraper);

        expect($events)->toHaveCount(0);
    });

    it('skips events with an empty title', function (): void {
        Http::fake([
            'www.meetup.com/gql' => Http::response('', 404),
            'www.meetup.com/find/*' => Http::response(nextDataHtml([
                meetupEvent(['title' => '']),
                meetupEvent(['id' => '2', 'title' => 'Valid Event']),
            ])),
        ]);

        $events = mkScrapeToCollection(new TestMeetupScraper);

        expect($events)->toHaveCount(1);
        expect($events->first()->title)->toBe('Valid Event');
    });

});
