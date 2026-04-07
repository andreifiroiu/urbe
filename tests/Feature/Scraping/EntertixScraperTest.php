<?php

declare(strict_types=1);

use App\DTOs\RawEvent;
use App\Services\Scraping\Adapters\EntertixScraper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

// ---------------------------------------------------------------------------
// Test double — suppresses HTTP delays
// ---------------------------------------------------------------------------

class TestEntertixScraper extends EntertixScraper
{
    protected function sleepBetweenRequests(): void {}

    protected function sleepOnRetry(): void {}
}

// ---------------------------------------------------------------------------
// HTML fixture helpers
// ---------------------------------------------------------------------------

/**
 * Build a single Entertix event card.
 *
 * @param  array<string, mixed>  $overrides
 */
function etCard(array $overrides = []): string
{
    $title = $overrides['title'] ?? 'Dino + Egipt';
    $startDate = $overrides['start_date'] ?? '20 mar';
    $endDate = $overrides['end_date'] ?? '30 apr';
    $year = $overrides['year'] ?? '2026';
    $venue = $overrides['venue'] ?? 'MINA';
    $location = $overrides['location'] ?? 'Timisoara';
    $eventId = $overrides['event_id'] ?? '35468';
    $slug = $overrides['slug'] ?? 'dino-egipt-20-mar-30-apr-2026-mina-timisoara';

    $when = "{$venue}, {$location}, {$startDate} - {$endDate} {$year}";

    return <<<HTML
    <div class="egh">
      <div class="eghrow">
        <div class="eghrowdate">
          <div class="eghrowdatebox">
            <div class="eghrowdateboxrow" style="font-size:10px;">{$startDate}</div>
            <div class="eghrowdateboxrow" style="font-size:10px;">{$endDate} {$year}</div>
          </div>
        </div>
        <div class="eghrowinfo">
          <div class="eghtext eghtexttitle">{$title}</div>
          <div class="eghtext eghtextwhen">{$when}</div>
        </div>
        <div class="eghrowtickets">
          <a href="https://www.entertix.ro/evenimente/{$eventId}/{$slug}.html" class="eghrowticketsbutton">Bilete</a>
        </div>
      </div>
      <div class="eghrelease"></div>
    </div>
    HTML;
}

/**
 * Wrap one or more card HTML strings in a minimal full page.
 *
 * @param  list<string>  $cards
 */
function etPage(array $cards): string
{
    $body = implode("\n", $cards);

    return <<<HTML
    <!DOCTYPE html>
    <html><head><meta charset="utf-8"><title>Evenimente | Entertix</title></head>
    <body>
    <div class="container">
      <div class="row">
        <div class="col-sm-10 col-md-10 col-lg-10">
          {$body}
        </div>
      </div>
    </div>
    </body></html>
    HTML;
}

function etEmptyPage(): string
{
    return <<<'HTML'
    <!DOCTYPE html>
    <html><head><meta charset="utf-8"></head>
    <body>
    <div class="container"><div class="row"><div class="col-sm-10"></div></div></div>
    </body></html>
    HTML;
}

/**
 * Run a scrape and return all emitted RawEvents.
 *
 * @return Collection<int, RawEvent>
 */
function etScrapeToCollection(
    EntertixScraper $scraper,
    array $source = [],
    array $city = [],
): Collection {
    Http::preventStrayRequests();

    $source = array_merge([
        'adapter' => 'entertix',
        'url' => 'https://www.entertix.ro/evenimente',
        'city_filter' => 'Timișoara',
        'enabled' => true,
        'interval_hours' => 8,
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

describe('EntertixScraper', function (): void {

    it('returns the correct adapter key', function (): void {
        expect((new TestEntertixScraper)->adapterKey())->toBe('entertix');
    });

    it('returns the correct source identifier', function (): void {
        $scraper = new TestEntertixScraper;
        expect($scraper->sourceIdentifier(['url' => 'https://www.entertix.ro/evenimente']))
            ->toBe('entertix@entertix.ro');
    });

    it('is registered in the adapter registry', function (): void {
        expect(config('eventpulse.adapter_registry'))->toHaveKey('entertix');
    });

    it('maps all fields from a single event card correctly', function (): void {
        Http::fake(['https://www.entertix.ro/*' => Http::response(etPage([etCard()]))]);

        $events = etScrapeToCollection(new TestEntertixScraper);

        expect($events)->toHaveCount(1);

        $event = $events->first();
        expect($event->title)->toBe('Dino + Egipt');
        expect($event->source)->toBe('entertix');
        expect($event->sourceUrl)->toBe('https://www.entertix.ro/evenimente/35468/dino-egipt-20-mar-30-apr-2026-mina-timisoara.html');
        expect($event->sourceId)->toBe('35468');
        expect($event->venue)->toBe('MINA');
        expect($event->city)->toBe('Timișoara');
        expect($event->isFree)->toBeNull();
        expect($event->imageUrl)->toBeNull();
        expect($event->description)->toBeNull();
        expect($event->priceMin)->toBeNull();
        expect($event->currency)->toBeNull();
        expect($event->endsAt)->toBeNull();
    });

    it('includes events matching the city filter', function (): void {
        Http::fake(['https://www.entertix.ro/*' => Http::response(etPage([
            etCard(['location' => 'Timisoara']),
        ]))]);

        $events = etScrapeToCollection(new TestEntertixScraper);

        expect($events)->toHaveCount(1);
    });

    it('excludes events not matching the city filter', function (): void {
        Http::fake(['https://www.entertix.ro/*' => Http::response(etPage([
            etCard(['location' => 'Cluj-Napoca']),
        ]))]);

        $events = etScrapeToCollection(new TestEntertixScraper);

        expect($events)->toHaveCount(0);
    });

    it('matches city filter case-insensitively and ignoring diacritics', function (): void {
        // city_filter = 'Timișoara', card location = 'Timisoara' (no diacritics)
        Http::fake(['https://www.entertix.ro/*' => Http::response(etPage([
            etCard(['location' => 'Timisoara']),
        ]))]);

        $events = etScrapeToCollection(new TestEntertixScraper, ['city_filter' => 'Timișoara']);

        expect($events)->toHaveCount(1);
    });

    it('strips @VENUE suffix from the title', function (): void {
        Http::fake(['https://www.entertix.ro/*' => Http::response(etPage([
            etCard(['title' => 'Concert @Filarmonica Banatul']),
        ]))]);

        $events = etScrapeToCollection(new TestEntertixScraper);

        expect($events->first()->title)->toBe('Concert');
    });

    it('preserves titles that have no @ suffix', function (): void {
        Http::fake(['https://www.entertix.ro/*' => Http::response(etPage([
            etCard(['title' => 'Dino + Egipt']),
        ]))]);

        $events = etScrapeToCollection(new TestEntertixScraper);

        expect($events->first()->title)->toBe('Dino + Egipt');
    });

    it('parses March date to UTC midnight correctly', function (): void {
        // 20 mar 2026 00:00 EET (UTC+2) → 2026-03-19 22:00:00 UTC
        Http::fake(['https://www.entertix.ro/*' => Http::response(etPage([
            etCard(['start_date' => '20 mar', 'end_date' => '30 apr', 'year' => '2026']),
        ]))]);

        $events = etScrapeToCollection(new TestEntertixScraper);

        expect($events->first()->startsAt)->toBe('2026-03-19 22:00:00');
    });

    it('propagates year from end date to start date', function (): void {
        // Start "07 apr" has no year; end "07 apr 2026" provides it → 2026-04-06 21:00:00 UTC (EEST)
        Http::fake(['https://www.entertix.ro/*' => Http::response(etPage([
            etCard(['start_date' => '07 apr', 'end_date' => '07 apr', 'year' => '2026']),
        ]))]);

        $events = etScrapeToCollection(new TestEntertixScraper);

        expect($events->first()->startsAt)->toBe('2026-04-06 21:00:00');
    });

    it('extracts venue from the eghtextwhen first segment', function (): void {
        Http::fake(['https://www.entertix.ro/*' => Http::response(etPage([
            etCard(['venue' => 'Filarmonica Banatul']),
        ]))]);

        $events = etScrapeToCollection(new TestEntertixScraper);

        expect($events->first()->venue)->toBe('Filarmonica Banatul');
    });

    it('sets sourceId to the numeric event ID from the URL', function (): void {
        Http::fake(['https://www.entertix.ro/*' => Http::response(etPage([
            etCard(['event_id' => '35468']),
        ]))]);

        $events = etScrapeToCollection(new TestEntertixScraper);

        expect($events->first()->sourceId)->toBe('35468');
    });

    it('sets category_hint to Entertainment in metadata', function (): void {
        Http::fake(['https://www.entertix.ro/*' => Http::response(etPage([etCard()]))]);

        $events = etScrapeToCollection(new TestEntertixScraper);

        expect($events->first()->metadata['category_hint'])->toBe('Entertainment');
    });

    it('parses multiple events and filters by city correctly', function (): void {
        Http::fake(['https://www.entertix.ro/*' => Http::response(etPage([
            etCard(['title' => 'Event A', 'event_id' => '1', 'slug' => 'event-a', 'location' => 'Timisoara']),
            etCard(['title' => 'Event B', 'event_id' => '2', 'slug' => 'event-b', 'location' => 'Cluj-Napoca']),
            etCard(['title' => 'Event C', 'event_id' => '3', 'slug' => 'event-c', 'location' => 'Timisoara']),
        ]))]);

        $events = etScrapeToCollection(new TestEntertixScraper);

        expect($events)->toHaveCount(2);
        expect($events->pluck('title')->all())->toBe(['Event A', 'Event C']);
    });

    it('emits 0 events when the page has no egh cards', function (): void {
        Http::fake(['https://www.entertix.ro/*' => Http::response(etEmptyPage())]);

        $events = etScrapeToCollection(new TestEntertixScraper);

        expect($events)->toHaveCount(0);
        Http::assertSentCount(1);
    });

    it('makes only one HTTP request per scrape (no pagination)', function (): void {
        Http::fake(['https://www.entertix.ro/*' => Http::response(etPage([etCard()]))]);

        etScrapeToCollection(new TestEntertixScraper);

        Http::assertSentCount(1);
    });

});
