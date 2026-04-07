<?php

declare(strict_types=1);

use App\DTOs\RawEvent;
use App\Services\Scraping\Adapters\TimisoreniScraper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

// ---------------------------------------------------------------------------
// Test double — suppresses HTTP delays
// ---------------------------------------------------------------------------

class TestTimisoreniScraper extends TimisoreniScraper
{
    protected function sleepBetweenRequests(): void {}

    protected function sleepOnRetry(): void {}
}

// ---------------------------------------------------------------------------
// HTML fixture helpers
// ---------------------------------------------------------------------------

/**
 * Build a single event card HTML block (ul.itemc + grid-view div).
 *
 * @param  array<string, mixed>  $overrides
 */
function trCard(array $overrides = []): string
{
    $title = $overrides['title'] ?? 'Concert Bosquito';
    $slug = $overrides['slug'] ?? 'concert-bosquito';
    $description = $overrides['description'] ?? 'Bosquito vine la Timișoara cu un recital extraordinar de muzică folk-rock românesc.';
    $image = $overrides['image'] ?? 'https://www.timisoreni.ro/upload/photo/2026-01/bosquito_thumb.jpg';
    $phone = $overrides['phone'] ?? '0256123456';

    /** @var list<array{startDate: string, endDate?: string, time: string, venue: string, address: string}> $rows */
    $rows = $overrides['rows'] ?? [
        [
            'startDate' => '2026-04-25T00:00:00+03:00',
            'time' => '19:00',
            'venue' => 'Filarmonica Banatul',
            'address' => 'Timișoara, Bulevardul C.D. Loga, nr. 2',
        ],
    ];

    $rowHtml = '';
    foreach ($rows as $row) {
        $endDateTd = '';
        if (isset($row['endDate'])) {
            $endDateTd = " - <span itemprop='endDate' content='{$row['endDate']}'>end date</span>";
        }
        $rowHtml .= <<<HTML

        <tr class="odd">
          <td><span itemprop='startDate' content='{$row['startDate']}'>text date</span>{$endDateTd}</td>
          <td>{$row['time']}</td>
          <td>
            <div itemprop='location' itemscope itemtype='http://schema.org/Place'>
              <a class='ctooltip' itemprop='url' href='/despre/{$slug}/'><span itemprop='name'>{$row['venue']}</span></a>
              <meta itemprop='address' itemscope itemtype='http://schema.org/Text' content='{$row['address']}' />
            </div>
          </td>
        </tr>
        HTML;
    }

    $noImageHtml = ($overrides['no_image'] ?? false) ? '' : <<<HTML
    <li>
      <div class="overlay-container overlay-visible pull-right">
        <img class="thumb pull-right" title="{$slug}" itemprop="image" src="{$image}" width="201" height="134" />
      </div>
    </li>
    HTML;

    $noDescHtml = ($overrides['no_description'] ?? false) ? '<li class="text" itemprop="description">   </li>' : <<<HTML
    <li class="text" itemprop="description">
      {$description}
    </li>
    HTML;

    return <<<HTML
    <ul class="itemc clearfix" itemscope itemtype="http://schema.org/Event">
      {$noImageHtml}
      <li>
        <h3>
          <a itemprop="url" href="/despre/{$slug}/">
            <span itemprop="name">{$title}</span>
          </a>
          <span class="label label-default badge">0</span>
        </h3>
      </li>
      <li class="buttons"></li>
      {$noDescHtml}
      <li class="phone"><i class="icon-phone"></i><b>{$phone}</b></li>
    </ul>
    <div id="yw1" class="grid-view">
      <table class="table table-bordered table-hover">
        <tbody>
          {$rowHtml}
        </tbody>
      </table>
      <div class="keys" style="display:none" title="/info/index/t--evenimente/"><span>12345</span></div>
    </div>
    HTML;
}

/**
 * Wrap one or more card HTML strings in a full page wrapper.
 *
 * @param  list<string>  $cards
 */
function trPage(array $cards): string
{
    $body = implode("\n", $cards);

    return <<<HTML
    <!DOCTYPE html>
    <html><head><meta charset="utf-8"></head>
    <body>
    <div class="items">
    {$body}
    </div>
    </body></html>
    HTML;
}

function trEmptyPage(): string
{
    return <<<'HTML'
    <!DOCTYPE html>
    <html><head><meta charset="utf-8"></head>
    <body><div class="items"></div></body></html>
    HTML;
}

/**
 * Run a scrape and return all emitted RawEvents.
 *
 * @return Collection<int, RawEvent>
 */
function trScrapeToCollection(
    TimisoreniScraper $scraper,
    array $source = [],
    array $city = [],
): Collection {
    Http::preventStrayRequests();

    $source = array_merge([
        'adapter' => 'timisoreni',
        'url' => 'https://www.timisoreni.ro/info/index/t--evenimente/',
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

describe('TimisoreniScraper', function (): void {

    it('returns the correct adapter key', function (): void {
        expect((new TestTimisoreniScraper)->adapterKey())->toBe('timisoreni');
    });

    it('returns the correct source identifier', function (): void {
        $scraper = new TestTimisoreniScraper;
        expect($scraper->sourceIdentifier(['url' => 'https://www.timisoreni.ro/info/index/t--evenimente/']))
            ->toBe('timisoreni@timisoreni.ro');
    });

    it('is registered in the adapter registry', function (): void {
        expect(config('eventpulse.adapter_registry'))->toHaveKey('timisoreni');
    });

    it('maps all fields from a single event correctly', function (): void {
        Http::fake([
            'https://www.timisoreni.ro/info/index/t--evenimente/' => Http::response(trPage([trCard()])),
            'https://www.timisoreni.ro/info/index/t--evenimente/2.htm' => Http::response(trEmptyPage()),
        ]);

        $events = trScrapeToCollection(new TestTimisoreniScraper);

        expect($events)->toHaveCount(1);

        $event = $events->first();
        expect($event->title)->toBe('Concert Bosquito');
        expect($event->source)->toBe('timisoreni');
        expect($event->sourceUrl)->toBe('https://www.timisoreni.ro/despre/concert-bosquito/');
        expect($event->sourceId)->toBe('concert-bosquito');
        expect($event->description)->toContain('Bosquito');
        expect($event->venue)->toBe('Filarmonica Banatul');
        expect($event->address)->toBe('Timișoara, Bulevardul C.D. Loga, nr. 2');
        expect($event->imageUrl)->toBe('https://www.timisoreni.ro/upload/photo/2026-01/bosquito_thumb.jpg');
        expect($event->city)->toBe('Timișoara');
        expect($event->isFree)->toBeFalse();
        expect($event->priceMin)->toBeNull();
    });

    it('converts startDate + time to UTC correctly', function (): void {
        // 2026-04-25T00:00:00+03:00 + 19:00 = 2026-04-25T19:00:00+03:00 = 2026-04-25T16:00:00Z
        Http::fake([
            'https://www.timisoreni.ro/info/index/t--evenimente/' => Http::response(trPage([
                trCard(['rows' => [
                    ['startDate' => '2026-04-25T00:00:00+03:00', 'time' => '19:00', 'venue' => 'Sala X', 'address' => 'Timișoara'],
                ]]),
            ])),
            'https://www.timisoreni.ro/info/index/t--evenimente/2.htm' => Http::response(trEmptyPage()),
        ]);

        $events = trScrapeToCollection(new TestTimisoreniScraper);
        expect($events->first()->startsAt)->toBe('2026-04-25 16:00:00');
    });

    it('falls back to midnight UTC when time cell is missing or invalid', function (): void {
        // "2026-04-10T00:00:00+03:00" at midnight = "2026-04-09 21:00:00" UTC
        Http::fake([
            'https://www.timisoreni.ro/info/index/t--evenimente/' => Http::response(trPage([
                trCard(['rows' => [
                    ['startDate' => '2026-04-10T00:00:00+03:00', 'time' => '', 'venue' => 'X', 'address' => 'Y'],
                ]]),
            ])),
            'https://www.timisoreni.ro/info/index/t--evenimente/2.htm' => Http::response(trEmptyPage()),
        ]);

        $events = trScrapeToCollection(new TestTimisoreniScraper);
        expect($events->first()->startsAt)->toBe('2026-04-09 21:00:00');
    });

    it('emits one RawEvent per performance date row for multi-date events', function (): void {
        // "Bal la Savoy" plays April 17 and April 19
        Http::fake([
            'https://www.timisoreni.ro/info/index/t--evenimente/' => Http::response(trPage([
                trCard([
                    'title' => 'Bal la Savoy',
                    'slug' => 'bal-la-savoy',
                    'description' => 'Operetă în trei acte de Paul Abraham.',
                    'rows' => [
                        ['startDate' => '2026-04-17T00:00:00+03:00', 'time' => '19:00', 'venue' => 'Opera Națională Română', 'address' => 'Timișoara, Strada Marasesti, nr. 2'],
                        ['startDate' => '2026-04-19T00:00:00+03:00', 'time' => '18:00', 'venue' => 'Opera Națională Română', 'address' => 'Timișoara, Strada Marasesti, nr. 2'],
                    ],
                ]),
            ])),
            'https://www.timisoreni.ro/info/index/t--evenimente/2.htm' => Http::response(trEmptyPage()),
        ]);

        $events = trScrapeToCollection(new TestTimisoreniScraper);

        expect($events)->toHaveCount(2);

        // Same title and sourceUrl on both
        expect($events->first()->title)->toBe('Bal la Savoy');
        expect($events->last()->title)->toBe('Bal la Savoy');
        expect($events->first()->sourceUrl)->toBe($events->last()->sourceUrl);

        // Different dates
        expect($events->first()->startsAt)->toBe('2026-04-17 16:00:00');
        expect($events->last()->startsAt)->toBe('2026-04-19 15:00:00');
    });

    it('parses endDate when present in the row', function (): void {
        Http::fake([
            'https://www.timisoreni.ro/info/index/t--evenimente/' => Http::response(trPage([
                trCard(['rows' => [
                    [
                        'startDate' => '2026-04-18T00:00:00+03:00',
                        'endDate' => '2026-04-19T00:00:00+03:00',
                        'time' => '20:30',
                        'venue' => 'Sala Capitol',
                        'address' => 'Timisoara',
                    ],
                ]]),
            ])),
            'https://www.timisoreni.ro/info/index/t--evenimente/2.htm' => Http::response(trEmptyPage()),
        ]);

        $events = trScrapeToCollection(new TestTimisoreniScraper);
        $event = $events->first();

        expect($event->startsAt)->toBe('2026-04-18 17:30:00');
        expect($event->endsAt)->not->toBeNull();
    });

    it('sets description to null when the description cell is blank', function (): void {
        Http::fake([
            'https://www.timisoreni.ro/info/index/t--evenimente/' => Http::response(trPage([
                trCard(['no_description' => true]),
            ])),
            'https://www.timisoreni.ro/info/index/t--evenimente/2.htm' => Http::response(trEmptyPage()),
        ]);

        $events = trScrapeToCollection(new TestTimisoreniScraper);
        expect($events->first()->description)->toBeNull();
    });

    it('sets imageUrl to null when no thumbnail is present', function (): void {
        Http::fake([
            'https://www.timisoreni.ro/info/index/t--evenimente/' => Http::response(trPage([
                trCard(['no_image' => true]),
            ])),
            'https://www.timisoreni.ro/info/index/t--evenimente/2.htm' => Http::response(trEmptyPage()),
        ]);

        $events = trScrapeToCollection(new TestTimisoreniScraper);
        expect($events->first()->imageUrl)->toBeNull();
    });

    it('paginates with the .htm suffix and stops when a page returns no events', function (): void {
        Http::fake([
            'https://www.timisoreni.ro/info/index/t--evenimente/' => Http::response(trPage([trCard()])),
            'https://www.timisoreni.ro/info/index/t--evenimente/2.htm' => Http::response(trPage([
                trCard(['title' => 'Concert 2', 'slug' => 'concert-2']),
            ])),
            'https://www.timisoreni.ro/info/index/t--evenimente/3.htm' => Http::response(trEmptyPage()),
        ]);

        $events = trScrapeToCollection(new TestTimisoreniScraper);

        expect($events)->toHaveCount(2);
        Http::assertSentCount(3);
    });

    it('deduplicates events appearing on both main and extra_urls pages', function (): void {
        // "Romanian Psycho" appears on both the eventos and spectacole pages
        $sharedCard = trCard(['title' => 'Romanian Psycho', 'slug' => 'romanian-psycho', 'description' => 'Cu Florin Piersic JR.']);
        $uniqueCard = trCard(['title' => 'Concert Jazz', 'slug' => 'concert-jazz', 'description' => 'Jazz la Filarmonica.', 'rows' => [
            ['startDate' => '2026-05-10T00:00:00+03:00', 'time' => '20:00', 'venue' => 'Filarmonica', 'address' => 'Timișoara'],
        ]]);

        Http::fake([
            // Main events page
            'https://www.timisoreni.ro/info/index/t--evenimente/' => Http::response(trPage([$sharedCard])),
            'https://www.timisoreni.ro/info/index/t--evenimente/2.htm' => Http::response(trEmptyPage()),
            // Spectacole extra URL — same "Romanian Psycho" card
            'https://www.timisoreni.ro/info/spectacole/' => Http::response(trPage([$sharedCard, $uniqueCard])),
            'https://www.timisoreni.ro/info/spectacole/2.htm' => Http::response(trEmptyPage()),
        ]);

        $source = [
            'adapter' => 'timisoreni',
            'url' => 'https://www.timisoreni.ro/info/index/t--evenimente/',
            'extra_urls' => ['https://www.timisoreni.ro/info/spectacole/'],
            'enabled' => true,
            'interval_hours' => 8,
        ];

        $events = trScrapeToCollection(new TestTimisoreniScraper, source: $source);

        // Should emit Romanian Psycho once + Concert Jazz once = 2 events
        expect($events)->toHaveCount(2);
        $titles = $events->pluck('title')->all();
        expect($titles)->toContain('Romanian Psycho');
        expect($titles)->toContain('Concert Jazz');
    });

    it('scrapes extra_urls pages independently from the main URL', function (): void {
        Http::fake([
            'https://www.timisoreni.ro/info/index/t--evenimente/' => Http::response(trPage([
                trCard(['title' => 'Main Event', 'slug' => 'main-event']),
            ])),
            'https://www.timisoreni.ro/info/index/t--evenimente/2.htm' => Http::response(trEmptyPage()),
            'https://www.timisoreni.ro/info/spectacole/' => Http::response(trPage([
                trCard(['title' => 'Spectacol Opera', 'slug' => 'spectacol-opera', 'rows' => [
                    ['startDate' => '2026-04-30T00:00:00+03:00', 'time' => '19:00', 'venue' => 'Opera', 'address' => 'Timișoara'],
                ]]),
            ])),
            'https://www.timisoreni.ro/info/spectacole/2.htm' => Http::response(trEmptyPage()),
        ]);

        $source = [
            'adapter' => 'timisoreni',
            'url' => 'https://www.timisoreni.ro/info/index/t--evenimente/',
            'extra_urls' => ['https://www.timisoreni.ro/info/spectacole/'],
            'enabled' => true,
            'interval_hours' => 8,
        ];

        $events = trScrapeToCollection(new TestTimisoreniScraper, source: $source);

        expect($events)->toHaveCount(2);
    });

    it('emits 0 events and makes only one request when the first page is empty', function (): void {
        Http::fake([
            'https://www.timisoreni.ro/info/index/t--evenimente/' => Http::response(trEmptyPage()),
        ]);

        $events = trScrapeToCollection(new TestTimisoreniScraper);

        expect($events)->toHaveCount(0);
        Http::assertSentCount(1);
    });

    it('parses rich Romanian descriptions correctly', function (): void {
        $description = 'Stand-up comedy cu Mihai Bobonete — spectacol de o oră și jumătate cu cele mai bune glume din 2025. Râsul garantat pentru toți cei care iubesc umorul inteligent.';

        Http::fake([
            'https://www.timisoreni.ro/info/index/t--evenimente/' => Http::response(trPage([
                trCard(['description' => $description]),
            ])),
            'https://www.timisoreni.ro/info/index/t--evenimente/2.htm' => Http::response(trEmptyPage()),
        ]);

        $events = trScrapeToCollection(new TestTimisoreniScraper);
        expect($events->first()->description)->toContain('Bobonete');
        expect($events->first()->description)->toContain('umorul inteligent');
    });

    it('caps pagination at 5 pages regardless of global max_pages setting', function (): void {
        // Configure global max_pages to a high number
        config(['eventpulse.scrapers.max_pages' => 20]);

        // Only provide 3 pages of content, rest empty
        Http::fake([
            'https://www.timisoreni.ro/info/index/t--evenimente/' => Http::response(trPage([trCard()])),
            'https://www.timisoreni.ro/info/index/t--evenimente/2.htm' => Http::response(trPage([trCard(['title' => 'Event 2', 'slug' => 'event-2'])])),
            'https://www.timisoreni.ro/info/index/t--evenimente/3.htm' => Http::response(trPage([trCard(['title' => 'Event 3', 'slug' => 'event-3'])])),
            'https://www.timisoreni.ro/info/index/t--evenimente/4.htm' => Http::response(trPage([trCard(['title' => 'Event 4', 'slug' => 'event-4'])])),
            'https://www.timisoreni.ro/info/index/t--evenimente/5.htm' => Http::response(trPage([trCard(['title' => 'Event 5', 'slug' => 'event-5'])])),
        ]);

        $events = trScrapeToCollection(new TestTimisoreniScraper);

        // Scraper caps at 5 pages and stops — no attempt at page 6
        expect($events)->toHaveCount(5);
        Http::assertSentCount(5);
    });

});
