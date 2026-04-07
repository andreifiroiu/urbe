<?php

declare(strict_types=1);

use App\DTOs\RawEvent;
use App\Services\Scraping\Adapters\VisitTimisoaraScraper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

// ---------------------------------------------------------------------------
// Test double — suppresses HTTP delays and overrides Browsershot
// ---------------------------------------------------------------------------

class TestVisitTimisoaraScraper extends VisitTimisoaraScraper
{
    /** @var list<string> */
    private array $browsershotFixtures = [];

    private int $browsershotCallCount = 0;

    protected function sleepBetweenRequests(): void {}

    protected function sleepOnRetry(): void {}

    /** @param list<string> $fixtures */
    public function setBrowsershotFixtures(array $fixtures): void
    {
        $this->browsershotFixtures = $fixtures;
        $this->browsershotCallCount = 0;
    }

    protected function fetchWithBrowsershot(string $url): string
    {
        return $this->browsershotFixtures[$this->browsershotCallCount++] ?? '';
    }
}

// ---------------------------------------------------------------------------
// Fixture helpers — TEC REST API
// ---------------------------------------------------------------------------

/**
 * Build a single TEC API event object.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function vtApiEvent(array $overrides = []): array
{
    return array_merge([
        'id' => 4751,
        'title' => 'Concert Jazz la Piața Unirii',
        'description' => '<p>Un concert extraordinar de jazz în centrul orașului.</p>',
        'url' => 'https://visit-timisoara.com/event/concert-jazz/',
        'utc_start_date' => '2026-05-10 16:00:00',
        'utc_end_date' => '2026-05-10 18:30:00',
        'cost' => '30 RON',
        'image' => ['url' => 'https://visit-timisoara.com/wp-content/uploads/jazz.jpg'],
        'venue' => [
            'venue' => 'Piața Unirii',
            'address' => 'Piața Unirii',
            'city' => 'Timișoara',
        ],
        'categories' => [['id' => 5, 'name' => 'Cultural', 'slug' => 'cultural']],
        'tags' => [['id' => 10, 'name' => 'jazz'], ['id' => 11, 'name' => 'outdoor']],
    ], $overrides);
}

/**
 * Build a TEC API page envelope.
 *
 * @param  list<array<string, mixed>>  $events
 * @return array<string, mixed>
 */
function vtApiPage(array $events, ?string $nextUrl = null): array
{
    return [
        'events' => $events,
        'total' => count($events),
        'total_pages' => $nextUrl !== null ? 2 : 1,
        'next_rest_url' => $nextUrl,
        'previous_rest_url' => null,
    ];
}

// ---------------------------------------------------------------------------
// Fixture helpers — Browsershot HTML
// ---------------------------------------------------------------------------

/**
 * Build a single TEC HTML event article card.
 *
 * @param  array<string, mixed>  $overrides
 */
function vtArticle(array $overrides = []): string
{
    $title = $overrides['title'] ?? 'Concert Jazz la Piața Unirii';
    $url = $overrides['url'] ?? 'https://visit-timisoara.com/event/concert-jazz/';
    $startIso = $overrides['start_iso'] ?? '2026-05-10T19:00:00+03:00';
    $endIso = $overrides['end_iso'] ?? '2026-05-10T21:30:00+03:00';
    $venue = $overrides['venue'] ?? 'Piața Unirii';
    $description = $overrides['description'] ?? 'Un concert extraordinar de jazz.';
    $image = $overrides['image'] ?? 'https://visit-timisoara.com/wp-content/uploads/jazz-300x200.jpg';
    $cost = $overrides['cost'] ?? '30 RON';

    return <<<HTML
    <article class="type-tribe_events post-4751 tribe_events status-publish">
      <div class="tribe-event-featured-image">
        <a href="{$url}"><img src="{$image}" alt="{$title}"></a>
      </div>
      <header class="tribe-events-calendar-list__event-header">
        <div class="tribe-events-calendar-list__event-datetime">
          <time class="tribe-events-schedule tribe-clearfix">
            <abbr class="tribe-events-abbr tribe-events-start-datetime" title="{$startIso}">May 10 @ 7:00 pm</abbr>
            <span class="tribe-divider" aria-hidden="true">-</span>
            <abbr class="tribe-events-abbr tribe-events-end-datetime" title="{$endIso}">9:30 pm</abbr>
          </time>
        </div>
        <h2 class="tribe-events-list-event-title entry-title">
          <a class="url" href="{$url}" title="{$title}">{$title}</a>
        </h2>
      </header>
      <div class="tribe-events-schedule tribe-events-content">
        <p>{$description}</p>
      </div>
      <address class="tribe-events-venue-location">
        <span class="tribe-venue">{$venue}</span>
      </address>
      <div class="tribe-events-event-cost">
        <span class="tribe-events-c-small-cta__price">{$cost}</span>
      </div>
    </article>
    HTML;
}

/**
 * Wrap article cards in a full rendered page with an optional next-page link.
 *
 * @param  list<string>  $articles
 */
function vtBrowsershotPage(array $articles, ?string $nextUrl = null): string
{
    $body = implode("\n", $articles);
    $nextLink = $nextUrl !== null
        ? "<a class=\"tribe-events-nav-next\" href=\"{$nextUrl}\">Next Events &rarr;</a>"
        : '';

    return <<<HTML
    <!DOCTYPE html>
    <html><head><meta charset="utf-8"><title>Events &amp; Activities | Visit Timișoara</title></head>
    <body>
    <div id="tribe-events" class="tribe-events-pg-template">
      <div class="tribe-events-loop">
        {$body}
      </div>
      {$nextLink}
    </div>
    </body></html>
    HTML;
}

function vtEmptyBrowsershotPage(): string
{
    return <<<'HTML'
    <!DOCTYPE html>
    <html><head><meta charset="utf-8"></head>
    <body>
    <div id="tribe-events" class="tribe-events-pg-template">
      <div class="tribe-events-loop"></div>
    </div>
    </body></html>
    HTML;
}

/**
 * Run a scrape and return all emitted RawEvents.
 *
 * @return Collection<int, RawEvent>
 */
function vtScrapeToCollection(
    VisitTimisoaraScraper $scraper,
    array $source = [],
    array $city = [],
): Collection {
    Http::preventStrayRequests();

    $source = array_merge([
        'adapter' => 'visit_timisoara',
        'url' => 'https://visit-timisoara.com/events-activities/',
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

describe('VisitTimisoaraScraper', function (): void {

    // -----------------------------------------------------------------------
    // Identity
    // -----------------------------------------------------------------------

    it('returns the correct adapter key', function (): void {
        expect((new TestVisitTimisoaraScraper)->adapterKey())->toBe('visit_timisoara');
    });

    it('returns the correct source identifier', function (): void {
        $scraper = new TestVisitTimisoaraScraper;
        expect($scraper->sourceIdentifier(['url' => 'https://visit-timisoara.com/events-activities/']))
            ->toBe('visit_timisoara@visit-timisoara.com');
    });

    it('is registered in the adapter registry', function (): void {
        expect(config('eventpulse.adapter_registry'))->toHaveKey('visit_timisoara');
    });

    // -----------------------------------------------------------------------
    // Path A — TEC REST API success
    // -----------------------------------------------------------------------

    it('emits events from TEC API when endpoint returns valid JSON', function (): void {
        Http::fake([
            'visit-timisoara.com/wp-json/*' => Http::response(vtApiPage([vtApiEvent()])),
        ]);

        $events = vtScrapeToCollection(new TestVisitTimisoaraScraper);

        expect($events)->toHaveCount(1);
        Http::assertSentCount(1);
    });

    it('maps all TEC API fields to RawEvent correctly', function (): void {
        Http::fake([
            'visit-timisoara.com/wp-json/*' => Http::response(vtApiPage([vtApiEvent()])),
        ]);

        $events = vtScrapeToCollection(new TestVisitTimisoaraScraper);
        $event = $events->first();

        expect($event->title)->toBe('Concert Jazz la Piața Unirii');
        expect($event->source)->toBe('visit_timisoara');
        expect($event->sourceUrl)->toBe('https://visit-timisoara.com/event/concert-jazz/');
        expect($event->sourceId)->toBe('4751');
        expect($event->venue)->toBe('Piața Unirii');
        expect($event->address)->toBe('Piața Unirii');
        expect($event->city)->toBe('Timișoara');
        expect($event->imageUrl)->toBe('https://visit-timisoara.com/wp-content/uploads/jazz.jpg');
        expect($event->isFree)->toBeFalse();
        expect($event->priceMin)->toBe(30.0);
        expect($event->currency)->toBe('RON');
        expect($event->metadata['category_hint'])->toBe('Cultural');
        expect($event->metadata['tags'])->toBe(['jazz', 'outdoor']);
    });

    it('uses utc_start_date directly for startsAt without re-converting timezone', function (): void {
        // utc_start_date is already UTC — just parse as-is
        Http::fake([
            'visit-timisoara.com/wp-json/*' => Http::response(vtApiPage([
                vtApiEvent(['utc_start_date' => '2026-05-10 16:00:00', 'utc_end_date' => '2026-05-10 18:30:00']),
            ])),
        ]);

        $events = vtScrapeToCollection(new TestVisitTimisoaraScraper);

        expect($events->first()->startsAt)->toBe('2026-05-10 16:00:00');
        expect($events->first()->endsAt)->toBe('2026-05-10 18:30:00');
    });

    it('sets isFree = true and priceMin = 0.0 when cost contains "Intrare liberă"', function (): void {
        Http::fake([
            'visit-timisoara.com/wp-json/*' => Http::response(vtApiPage([
                vtApiEvent(['cost' => 'Intrare liberă']),
            ])),
        ]);

        $events = vtScrapeToCollection(new TestVisitTimisoaraScraper);
        $event = $events->first();

        expect($event->isFree)->toBeTrue();
        expect($event->priceMin)->toBe(0.0);
    });

    it('paginates via next_rest_url and emits events from all pages', function (): void {
        $page2Url = 'https://visit-timisoara.com/wp-json/tribe/events/v1/events?page=2&per_page=50';

        Http::fake([
            'visit-timisoara.com/wp-json/tribe/events/v1/events?page=1*' => Http::response(
                vtApiPage([vtApiEvent(['id' => 1, 'title' => 'Event Page 1'])], $page2Url)
            ),
            'visit-timisoara.com/wp-json/tribe/events/v1/events?page=2*' => Http::response(
                vtApiPage([vtApiEvent(['id' => 2, 'title' => 'Event Page 2'])])
            ),
        ]);

        $events = vtScrapeToCollection(new TestVisitTimisoaraScraper);

        expect($events)->toHaveCount(2);
        expect($events->pluck('title')->all())->toBe(['Event Page 1', 'Event Page 2']);
        Http::assertSentCount(2);
    });

    it('returns empty list when API returns 200 with empty events array', function (): void {
        Http::fake([
            'visit-timisoara.com/wp-json/*' => Http::response(vtApiPage([])),
        ]);

        $scraper = new TestVisitTimisoaraScraper;
        $scraper->setBrowsershotFixtures([]);

        $events = vtScrapeToCollection($scraper);

        expect($events)->toHaveCount(0);
        Http::assertSentCount(1); // API hit, no Browsershot needed
    });

    // -----------------------------------------------------------------------
    // Path A — fallback triggers
    // -----------------------------------------------------------------------

    it('falls back to Browsershot when API returns 404', function (): void {
        Http::fake([
            'visit-timisoara.com/wp-json/*' => Http::response('', 404),
        ]);

        $scraper = new TestVisitTimisoaraScraper;
        $scraper->setBrowsershotFixtures([vtBrowsershotPage([vtArticle()])]);

        $events = vtScrapeToCollection($scraper);

        expect($events)->toHaveCount(1);
        Http::assertSentCount(1); // Only the failed API attempt
    });

    it('falls back to Browsershot when API response has no "events" key', function (): void {
        Http::fake([
            'visit-timisoara.com/wp-json/*' => Http::response(json_encode(['error' => 'not found'])),
        ]);

        $scraper = new TestVisitTimisoaraScraper;
        $scraper->setBrowsershotFixtures([vtBrowsershotPage([vtArticle()])]);

        $events = vtScrapeToCollection($scraper);

        expect($events)->toHaveCount(1);
    });

    // -----------------------------------------------------------------------
    // Path B — Browsershot HTML
    // -----------------------------------------------------------------------

    it('maps all HTML fields from Browsershot article correctly', function (): void {
        Http::fake([
            'visit-timisoara.com/wp-json/*' => Http::response('', 404),
        ]);

        $scraper = new TestVisitTimisoaraScraper;
        $scraper->setBrowsershotFixtures([vtBrowsershotPage([vtArticle()])]);

        $events = vtScrapeToCollection($scraper);
        $event = $events->first();

        expect($event->title)->toBe('Concert Jazz la Piața Unirii');
        expect($event->source)->toBe('visit_timisoara');
        expect($event->sourceUrl)->toBe('https://visit-timisoara.com/event/concert-jazz/');
        expect($event->sourceId)->toBe('concert-jazz');
        expect($event->venue)->toBe('Piața Unirii');
        expect($event->description)->toBe('Un concert extraordinar de jazz.');
        expect($event->city)->toBe('Timișoara');
        expect($event->imageUrl)->toBe('https://visit-timisoara.com/wp-content/uploads/jazz-300x200.jpg');
        expect($event->isFree)->toBeFalse();
    });

    it('parses ISO 8601 datetime with offset from abbr/@title attribute to UTC', function (): void {
        // 2026-05-10T19:00:00+03:00 → UTC 16:00:00
        Http::fake([
            'visit-timisoara.com/wp-json/*' => Http::response('', 404),
        ]);

        $scraper = new TestVisitTimisoaraScraper;
        $scraper->setBrowsershotFixtures([vtBrowsershotPage([
            vtArticle(['start_iso' => '2026-05-10T19:00:00+03:00', 'end_iso' => '2026-05-10T21:30:00+03:00']),
        ])]);

        $events = vtScrapeToCollection($scraper);

        expect($events->first()->startsAt)->toBe('2026-05-10 16:00:00');
        expect($events->first()->endsAt)->toBe('2026-05-10 18:30:00');
    });

    it('follows tribe-events-nav-next pagination in Browsershot path', function (): void {
        Http::fake([
            'visit-timisoara.com/wp-json/*' => Http::response('', 404),
        ]);

        $page2Url = 'https://visit-timisoara.com/events-activities/?tribe_paged=2';

        $scraper = new TestVisitTimisoaraScraper;
        $scraper->setBrowsershotFixtures([
            vtBrowsershotPage([vtArticle(['title' => 'Event Page 1', 'url' => 'https://visit-timisoara.com/event/event-1/'])], $page2Url),
            vtBrowsershotPage([vtArticle(['title' => 'Event Page 2', 'url' => 'https://visit-timisoara.com/event/event-2/'])]),
        ]);

        $events = vtScrapeToCollection($scraper);

        expect($events)->toHaveCount(2);
        expect($events->pluck('title')->all())->toBe(['Event Page 1', 'Event Page 2']);
    });

    it('emits 0 events when both API fails and Browsershot returns empty HTML', function (): void {
        Http::fake([
            'visit-timisoara.com/wp-json/*' => Http::response('', 503),
        ]);

        $scraper = new TestVisitTimisoaraScraper;
        $scraper->setBrowsershotFixtures([vtEmptyBrowsershotPage()]);

        $events = vtScrapeToCollection($scraper);

        expect($events)->toHaveCount(0);
    });

    it('skips Browsershot articles with an empty title', function (): void {
        Http::fake([
            'visit-timisoara.com/wp-json/*' => Http::response('', 404),
        ]);

        $scraper = new TestVisitTimisoaraScraper;
        $scraper->setBrowsershotFixtures([vtBrowsershotPage([
            vtArticle(['title' => '']),
            vtArticle(['title' => 'Valid Event', 'url' => 'https://visit-timisoara.com/event/valid/']),
        ])]);

        $events = vtScrapeToCollection($scraper);

        expect($events)->toHaveCount(1);
        expect($events->first()->title)->toBe('Valid Event');
    });

});
