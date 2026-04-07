<?php

declare(strict_types=1);

use App\DTOs\RawEvent;
use App\Services\Scraping\Adapters\GoogleEventsScraper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

// ---------------------------------------------------------------------------
// Fixture helpers
// ---------------------------------------------------------------------------

/**
 * Build a single SerpApi `events_results` item.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function geEvent(array $overrides = []): array
{
    return array_replace_recursive([
        'title' => 'AWS Community Day Romania',
        'date' => ['when' => 'Thu, Apr 23, 10:00 AM – 6:00 PM', 'start_date' => 'Apr 23'],
        'address' => ['The Place', 'Str. Ionescu 1, Timișoara, Romania'],
        'link' => 'https://www.meetup.com/aws-romania/events/313614908/',
        'description' => 'A great event for cloud enthusiasts.',
        'ticket_info' => [
            ['source' => 'Meetup', 'link' => 'https://www.meetup.com/...', 'link_type' => 'tickets'],
            ['source' => 'Eventbrite', 'link' => 'https://www.eventbrite.com/...', 'link_type' => 'order'],
        ],
        'venue' => ['name' => 'The Place', 'rating' => 4.5, 'reviews' => 100],
        'thumbnail' => 'https://lh3.googleusercontent.com/photo.jpg',
        'image' => 'https://lh3.googleusercontent.com/photo-large.jpg',
    ], $overrides);
}

/**
 * Build a full SerpApi response envelope.
 *
 * @param  list<array<string, mixed>>  $events
 * @return array<string, mixed>
 */
function geApiResponse(array $events): array
{
    return [
        'search_metadata' => ['status' => 'Success'],
        'search_parameters' => ['engine' => 'google_events', 'q' => 'Events in Timisoara'],
        'events_results' => $events,
    ];
}

/**
 * Run a scrape and return all emitted RawEvents.
 *
 * @return Collection<int, RawEvent>
 */
function geScrapeToCollection(
    GoogleEventsScraper $scraper,
    array $source = [],
    array $city = [],
): Collection {
    Http::preventStrayRequests();

    $source = array_merge([
        'adapter' => 'google_events',
        'params' => ['q' => 'Events in Timisoara'],
        'enabled' => false,
        'interval_hours' => 12,
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

describe('GoogleEventsScraper', function (): void {

    it('returns the correct adapter key', function (): void {
        expect((new GoogleEventsScraper)->adapterKey())->toBe('google_events');
    });

    it('returns the correct source identifier', function (): void {
        expect((new GoogleEventsScraper)->sourceIdentifier([]))->toBe('google_events@serpapi.com');
    });

    it('is registered in the adapter registry', function (): void {
        expect(config('eventpulse.adapter_registry'))->toHaveKey('google_events');
    });

    // -----------------------------------------------------------------------
    // Field mapping
    // -----------------------------------------------------------------------

    it('maps all fields from a SerpApi event correctly', function (): void {
        Http::fake(['serpapi.com/*' => Http::response(geApiResponse([geEvent()]))]);
        config(['eventpulse.serpapi_api_key' => 'test-key']);

        $events = geScrapeToCollection(new GoogleEventsScraper);

        expect($events)->toHaveCount(1);

        $event = $events->first();
        expect($event->title)->toBe('AWS Community Day Romania');
        expect($event->source)->toBe('google_events');
        expect($event->sourceUrl)->toBe('https://www.meetup.com/aws-romania/events/313614908/');
        expect($event->sourceId)->toBeNull();
        expect($event->venue)->toBe('The Place');
        expect($event->address)->toBe('Str. Ionescu 1, Timișoara, Romania');
        expect($event->city)->toBe('Timișoara');
        expect($event->description)->toBe('A great event for cloud enthusiasts.');
        expect($event->imageUrl)->toBe('https://lh3.googleusercontent.com/photo.jpg');
        expect($event->isFree)->toBeNull();
        expect($event->priceMin)->toBeNull();
        expect($event->currency)->toBeNull();
        expect($event->endsAt)->toBeNull();
        expect($event->metadata['ticket_sources'])->toBe(['Meetup', 'Eventbrite']);
    });

    it('uses venue.name over address[0] for the venue field', function (): void {
        Http::fake(['serpapi.com/*' => Http::response(geApiResponse([
            geEvent(['venue' => ['name' => 'Victoria Concert Hall'], 'address' => ['Some Place', 'City']]),
        ]))]);
        config(['eventpulse.serpapi_api_key' => 'test-key']);

        $events = geScrapeToCollection(new GoogleEventsScraper);

        expect($events->first()->venue)->toBe('Victoria Concert Hall');
        expect($events->first()->address)->toBe('City');
    });

    it('falls back to address[0] as venue when venue.name is absent', function (): void {
        Http::fake(['serpapi.com/*' => Http::response(geApiResponse([
            geEvent(['venue' => null, 'address' => ['Piața Unirii', 'Timișoara, Romania']]),
        ]))]);
        config(['eventpulse.serpapi_api_key' => 'test-key']);

        $events = geScrapeToCollection(new GoogleEventsScraper);

        expect($events->first()->venue)->toBe('Piața Unirii');
        expect($events->first()->address)->toBe('Timișoara, Romania');
    });

    it('prefers thumbnail over image for imageUrl', function (): void {
        Http::fake(['serpapi.com/*' => Http::response(geApiResponse([
            geEvent(['thumbnail' => 'https://example.com/thumb.jpg', 'image' => 'https://example.com/large.jpg']),
        ]))]);
        config(['eventpulse.serpapi_api_key' => 'test-key']);

        $events = geScrapeToCollection(new GoogleEventsScraper);

        expect($events->first()->imageUrl)->toBe('https://example.com/thumb.jpg');
    });

    it('falls back to image when thumbnail is absent', function (): void {
        Http::fake(['serpapi.com/*' => Http::response(geApiResponse([
            geEvent(['thumbnail' => null, 'image' => 'https://example.com/large.jpg']),
        ]))]);
        config(['eventpulse.serpapi_api_key' => 'test-key']);

        $events = geScrapeToCollection(new GoogleEventsScraper);

        expect($events->first()->imageUrl)->toBe('https://example.com/large.jpg');
    });

    it('sets imageUrl to null when both thumbnail and image are absent', function (): void {
        Http::fake(['serpapi.com/*' => Http::response(geApiResponse([
            geEvent(['thumbnail' => null, 'image' => null]),
        ]))]);
        config(['eventpulse.serpapi_api_key' => 'test-key']);

        $events = geScrapeToCollection(new GoogleEventsScraper);

        expect($events->first()->imageUrl)->toBeNull();
    });

    // -----------------------------------------------------------------------
    // Date parsing
    // -----------------------------------------------------------------------

    it('parses a full "when" string with day, date and time to UTC', function (): void {
        // "Thu, Apr 23, 10:00 AM" in UTC should stay Apr 23 10:00 UTC (Carbon parses without tz)
        Http::fake(['serpapi.com/*' => Http::response(geApiResponse([
            geEvent(['date' => ['when' => 'Thu, Apr 23, 10:00 AM', 'start_date' => 'Apr 23']]),
        ]))]);
        config(['eventpulse.serpapi_api_key' => 'test-key']);

        $events = geScrapeToCollection(new GoogleEventsScraper);

        expect($events->first()->startsAt)->not->toBeNull();
    });

    it('strips the range end from "when" and uses only the start date/time', function (): void {
        // "Sat, Apr 5, 10:00 AM – 3:00 PM" → start is "Sat, Apr 5, 10:00 AM"
        Http::fake(['serpapi.com/*' => Http::response(geApiResponse([
            geEvent(['date' => ['when' => 'Sat, Apr 5, 10:00 AM – 3:00 PM']]),
        ]))]);
        config(['eventpulse.serpapi_api_key' => 'test-key']);

        $events = geScrapeToCollection(new GoogleEventsScraper);
        $startsAt = $events->first()->startsAt;

        // Should be a valid datetime, not null
        expect($startsAt)->not->toBeNull();
        // Should not include the end time (3 PM)
        expect($startsAt)->not->toContain('15:00');
    });

    it('sets startsAt to null when the when field is absent', function (): void {
        // Explicitly clear 'when' so array_replace_recursive does not inherit it from the default fixture
        Http::fake(['serpapi.com/*' => Http::response(geApiResponse([
            geEvent(['date' => ['when' => '', 'start_date' => 'Apr 23']]),
        ]))]);
        config(['eventpulse.serpapi_api_key' => 'test-key']);

        $events = geScrapeToCollection(new GoogleEventsScraper);

        expect($events->first()->startsAt)->toBeNull();
    });

    // -----------------------------------------------------------------------
    // Multiple events
    // -----------------------------------------------------------------------

    it('emits multiple events in order', function (): void {
        Http::fake(['serpapi.com/*' => Http::response(geApiResponse([
            geEvent(['title' => 'Event Alpha', 'link' => 'https://example.com/alpha']),
            geEvent(['title' => 'Event Beta', 'link' => 'https://example.com/beta']),
            geEvent(['title' => 'Event Gamma', 'link' => 'https://example.com/gamma']),
        ]))]);
        config(['eventpulse.serpapi_api_key' => 'test-key']);

        $events = geScrapeToCollection(new GoogleEventsScraper);

        expect($events)->toHaveCount(3);
        expect($events->pluck('title')->all())->toBe(['Event Alpha', 'Event Beta', 'Event Gamma']);
    });

    it('emits 0 events when events_results is an empty array', function (): void {
        Http::fake(['serpapi.com/*' => Http::response(geApiResponse([]))]);
        config(['eventpulse.serpapi_api_key' => 'test-key']);

        $events = geScrapeToCollection(new GoogleEventsScraper);

        expect($events)->toHaveCount(0);
        Http::assertSentCount(1);
    });

    it('skips events with an empty title', function (): void {
        Http::fake(['serpapi.com/*' => Http::response(geApiResponse([
            geEvent(['title' => '']),
            geEvent(['title' => 'Valid Event', 'link' => 'https://example.com/valid']),
        ]))]);
        config(['eventpulse.serpapi_api_key' => 'test-key']);

        $events = geScrapeToCollection(new GoogleEventsScraper);

        expect($events)->toHaveCount(1);
        expect($events->first()->title)->toBe('Valid Event');
    });

    // -----------------------------------------------------------------------
    // Error handling
    // -----------------------------------------------------------------------

    it('emits 0 events and makes no HTTP call when API key is missing', function (): void {
        Http::fake();
        config(['eventpulse.serpapi_api_key' => null]);

        $events = geScrapeToCollection(new GoogleEventsScraper);

        expect($events)->toHaveCount(0);
        Http::assertNothingSent();
    });

    it('emits 0 events and makes no HTTP call when query param is missing', function (): void {
        Http::fake();
        config(['eventpulse.serpapi_api_key' => 'test-key']);

        $events = geScrapeToCollection(new GoogleEventsScraper, ['params' => []]);

        expect($events)->toHaveCount(0);
        Http::assertNothingSent();
    });

    it('emits 0 events when API returns 401', function (): void {
        Http::fake(['serpapi.com/*' => Http::response('Unauthorized', 401)]);
        config(['eventpulse.serpapi_api_key' => 'bad-key']);

        $events = geScrapeToCollection(new GoogleEventsScraper);

        expect($events)->toHaveCount(0);
    });

    it('emits 0 events when API returns 500', function (): void {
        Http::fake(['serpapi.com/*' => Http::response('Server Error', 500)]);
        config(['eventpulse.serpapi_api_key' => 'test-key']);

        $events = geScrapeToCollection(new GoogleEventsScraper);

        expect($events)->toHaveCount(0);
    });

    it('emits 0 events when response has no events_results key', function (): void {
        Http::fake(['serpapi.com/*' => Http::response(json_encode(['error' => 'quota exceeded']))]);
        config(['eventpulse.serpapi_api_key' => 'test-key']);

        $events = geScrapeToCollection(new GoogleEventsScraper);

        expect($events)->toHaveCount(0);
    });

    // -----------------------------------------------------------------------
    // HTTP contract
    // -----------------------------------------------------------------------

    it('sends engine, q, and api_key query parameters', function (): void {
        Http::fake(['serpapi.com/*' => Http::response(geApiResponse([]))]);
        config(['eventpulse.serpapi_api_key' => 'my-secret-key']);

        geScrapeToCollection(new GoogleEventsScraper, ['params' => ['q' => 'Events in Timisoara']]);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return ($data['engine'] ?? '') === 'google_events'
                && ($data['q'] ?? '') === 'Events in Timisoara'
                && ($data['api_key'] ?? '') === 'my-secret-key';
        });
    });

    it('makes exactly one HTTP request per scrape run', function (): void {
        Http::fake(['serpapi.com/*' => Http::response(geApiResponse([geEvent()]))]);
        config(['eventpulse.serpapi_api_key' => 'test-key']);

        geScrapeToCollection(new GoogleEventsScraper);

        Http::assertSentCount(1);
    });

});
