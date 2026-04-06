<?php

declare(strict_types=1);

use App\DTOs\RawEvent;
use App\Services\Scraping\Adapters\OperaTimisoaraScraper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

// ---------------------------------------------------------------------------
// Test double — suppresses HTTP delays
// ---------------------------------------------------------------------------

class TestOperaTimisoaraScraper extends OperaTimisoaraScraper
{
    protected function sleepBetweenRequests(): void {}

    protected function sleepOnRetry(): void {}
}

// ---------------------------------------------------------------------------
// HTML fixture helpers
// ---------------------------------------------------------------------------

/**
 * Build a single opera performance card HTML block.
 *
 * @param  array<string, mixed>  $overrides
 */
function orCard(array $overrides = []): string
{
    $showType = $overrides['show_type'] ?? 'Operă';
    $image = $overrides['image'] ?? '/imagini/1920x860/29529DSC_4503.JPG';
    $composer = $overrides['composer'] ?? 'Giuseppe Verdi';
    $title = $overrides['title'] ?? 'Aida';
    $eventId = $overrides['event_id'] ?? '883';
    $slug = $overrides['slug'] ?? 'Aida';
    $description = $overrides['description'] ?? 'Operă în patru acte <br /><span><strong>Libretul:</strong> </span>Antonio Ghislanzoni';
    $dateLine = $overrides['date_line'] ?? '<span class="galben">DUMINICĂ</span>                    26 APRILIE 2026, <span class="galben"><span style="text-transform: uppercase">Ora</span>: 18:00</span>';
    $festivalNote = $overrides['festival_note'] ?? '';

    $festivalHtml = $festivalNote !== ''
        ? '<br /><span style="text-transform: uppercase">'.$festivalNote.'</span>'
        : '';

    return <<<HTML
    <div style="margin-bottom: 50px; overflow: hidden;">
      <div style="position: relative">
        <div style="position: relative">
          <div class="nume-tip-eveniment">
            <div class="nume-tip-eveniment2 rotate">{$showType}</div>
          </div>
          <img src="{$image}" alt="" style="margin:0; display: block; padding:0; width: 100%;" class="pozaload"/>
        </div>
        <div class="carte-eveniment">
          <div class="titlu-eveniment3">
            {$composer}
          </div>
          <div class="separator20"></div>
          <div class="titlu-eveniment2">
            <a href="/eveniment/{$eventId}/ro/{$slug}.html">{$title}</a>
          </div>
          <div class="titlu-eveniment1">
            {$description}
          </div>
          <div class="separator20"></div>
          <div class="data-banner">
            {$dateLine}
            {$festivalHtml}
          </div>
          <div class="text-carte"></div>
        </div>
        <div class="butoane-eveniment">
          <div class="buton-detalii2">
            <a href="/eveniment/{$eventId}/ro/{$slug}.html">Vezi detalii</a>
          </div>
        </div>
      </div>
    </div>
    HTML;
}

/**
 * Wrap one or more card HTML strings in a full page wrapper.
 *
 * @param  list<string>  $cards
 */
function orPage(array $cards): string
{
    $body = implode("\n", $cards);

    return <<<HTML
    <!DOCTYPE html>
    <html><head><meta charset="utf-8"><title>Spectacole | Opera Nationala Romana Timisoara</title></head>
    <body>
    <div class="linie-titlu"><div class="titlu-pagina">Spectacole</div></div>
    <div style="margin-bottom: 50px; overflow: hidden;">
    {$body}
    </div>
    </body></html>
    HTML;
}

function orEmptyPage(): string
{
    return <<<'HTML'
    <!DOCTYPE html>
    <html><head><meta charset="utf-8"></head>
    <body><div class="linie-titlu"><div class="titlu-pagina">Spectacole</div></div></body></html>
    HTML;
}

/**
 * Run a scrape and return all emitted RawEvents.
 *
 * @return Collection<int, RawEvent>
 */
function orScrapeToCollection(
    OperaTimisoaraScraper $scraper,
    array $source = [],
    array $city = [],
): Collection {
    Http::preventStrayRequests();

    $source = array_merge([
        'adapter' => 'opera_timisoara',
        'url' => 'https://www.ort.ro/ro/Spectacole.html',
        'enabled' => true,
        'interval_hours' => 24,
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

describe('OperaTimisoaraScraper', function (): void {

    it('returns the correct adapter key', function (): void {
        expect((new TestOperaTimisoaraScraper)->adapterKey())->toBe('opera_timisoara');
    });

    it('returns the correct source identifier', function (): void {
        $scraper = new TestOperaTimisoaraScraper;
        expect($scraper->sourceIdentifier(['url' => 'https://www.ort.ro/ro/Spectacole.html']))
            ->toBe('opera_timisoara@ort.ro');
    });

    it('is registered in the adapter registry', function (): void {
        expect(config('eventpulse.adapter_registry'))->toHaveKey('opera_timisoara');
    });

    it('maps all fields from a single performance card correctly', function (): void {
        Http::fake(['https://www.ort.ro/*' => Http::response(orPage([orCard()]))]);

        $events = orScrapeToCollection(new TestOperaTimisoaraScraper);

        expect($events)->toHaveCount(1);

        $event = $events->first();
        expect($event->title)->toBe('Aida');
        expect($event->source)->toBe('opera_timisoara');
        expect($event->sourceUrl)->toBe('https://www.ort.ro/eveniment/883/ro/Aida.html');
        expect($event->sourceId)->toBe('883');
        expect($event->venue)->toBe('Opera Națională Română Timișoara');
        expect($event->address)->toBe('Timișoara, B-dul Regele Carol I nr. 3');
        expect($event->city)->toBe('Timișoara');
        expect($event->imageUrl)->toBe('https://www.ort.ro/imagini/1920x860/29529DSC_4503.JPG');
        expect($event->isFree)->toBeFalse();
        expect($event->priceMin)->toBeNull();
    });

    it('builds description from composer and show-type line', function (): void {
        Http::fake(['https://www.ort.ro/*' => Http::response(orPage([
            orCard([
                'composer' => 'Pietro Mascagni',
                'description' => 'Operă într-un act <br /><span><strong>Libretul:</strong> </span>Giovanni Targioni-Tozzetti și Guido Menasci',
            ]),
        ]))]);

        $events = orScrapeToCollection(new TestOperaTimisoaraScraper);
        $desc = $events->first()->description;

        expect($desc)->toContain('Pietro Mascagni');
        expect($desc)->toContain('Operă într-un act');
        expect($desc)->toContain('Giovanni Targioni-Tozzetti');
    });

    it('parses date and time to UTC correctly', function (): void {
        // 26 APRILIE 2026, Ora: 18:00 Europe/Bucharest (EEST = UTC+3) → UTC 15:00
        Http::fake(['https://www.ort.ro/*' => Http::response(orPage([orCard()]))]);

        $events = orScrapeToCollection(new TestOperaTimisoaraScraper);
        expect($events->first()->startsAt)->toBe('2026-04-26 15:00:00');
    });

    it('parses evening performance time correctly', function (): void {
        // 08 APRILIE 2026, Ora: 19:00 → UTC 16:00
        Http::fake(['https://www.ort.ro/*' => Http::response(orPage([
            orCard([
                'title' => 'Cavalleria rusticana',
                'event_id' => '882',
                'slug' => 'Cavalleria-rusticana',
                'date_line' => '<span class="galben">MIERCURI</span> 08 APRILIE 2026, <span class="galben"><span style="text-transform: uppercase">Ora</span>: 19:00</span>',
            ]),
        ]))]);

        $events = orScrapeToCollection(new TestOperaTimisoaraScraper);
        expect($events->first()->startsAt)->toBe('2026-04-08 16:00:00');
    });

    it('classifies Operă show type as Opera category', function (): void {
        Http::fake(['https://www.ort.ro/*' => Http::response(orPage([orCard(['show_type' => 'Operă'])]))]);

        $events = orScrapeToCollection(new TestOperaTimisoaraScraper);
        $metadata = $events->first()->metadata;

        expect($metadata['category_hint'])->toBe('Opera');
        expect($metadata['genre'])->toBe('operă');
    });

    it('classifies Operetă show type as Opera category', function (): void {
        Http::fake(['https://www.ort.ro/*' => Http::response(orPage([
            orCard([
                'show_type' => 'Operetă',
                'title' => 'Bal la Savoy',
                'composer' => 'Paul Abraham',
                'description' => 'Operetă în trei acte <br /><span><strong>Libretul:</strong> </span>Alfred Grünwald și Fritz Löhner - Beda',
                'event_id' => '860',
                'slug' => 'Bal-la-Savoy',
                'date_line' => '<span class="galben">VINERI</span> 17 APRILIE 2026, <span class="galben"><span>Ora</span>: 19:00</span>',
            ]),
        ]))]);

        $events = orScrapeToCollection(new TestOperaTimisoaraScraper);
        expect($events->first()->metadata['category_hint'])->toBe('Opera');
        expect($events->first()->metadata['genre'])->toBe('operetă');
    });

    it('classifies Balet show type as Ballet category', function (): void {
        Http::fake(['https://www.ort.ro/*' => Http::response(orPage([
            orCard([
                'show_type' => 'Balet',
                'title' => 'Lacul Lebedelor',
                'composer' => 'Piotr Ilici Ceaikovski',
                'description' => 'Balet în două acte <br /><span><strong>Libretul:</strong> </span>...',
                'event_id' => '800',
                'slug' => 'Lacul-Lebedelor',
            ]),
        ]))]);

        $events = orScrapeToCollection(new TestOperaTimisoaraScraper);
        expect($events->first()->metadata['category_hint'])->toBe('Ballet');
        expect($events->first()->metadata['genre'])->toBe('ballet');
    });

    it('classifies as Ballet via description when show_type does not say Balet', function (): void {
        // "Dancing Queen" is listed as "Balet" type but description also confirms
        Http::fake(['https://www.ort.ro/*' => Http::response(orPage([
            orCard([
                'show_type' => 'Balet',
                'title' => 'Dancing Queen',
                'description' => 'Balet - rock în două acte. Povestea unei cariere.',
                'event_id' => '884',
                'slug' => 'Dancing-Queen',
            ]),
        ]))]);

        $events = orScrapeToCollection(new TestOperaTimisoaraScraper);
        expect($events->first()->metadata['category_hint'])->toBe('Ballet');
    });

    it('classifies Concert show type as Classical category', function (): void {
        Http::fake(['https://www.ort.ro/*' => Http::response(orPage([
            orCard([
                'show_type' => 'Concert',
                'title' => 'Concert de Gală',
                'event_id' => '900',
                'slug' => 'Concert-de-Gala',
            ]),
        ]))]);

        $events = orScrapeToCollection(new TestOperaTimisoaraScraper);
        expect($events->first()->metadata['category_hint'])->toBe('Classical');
    });

    it('parses multiple performances correctly', function (): void {
        Http::fake(['https://www.ort.ro/*' => Http::response(orPage([
            orCard([
                'title' => 'Cavalleria rusticana',
                'event_id' => '882',
                'slug' => 'Cavalleria-rusticana',
                'date_line' => '<span class="galben">MIERCURI</span> 08 APRILIE 2026, <span class="galben"><span>Ora</span>: 19:00</span>',
            ]),
            orCard([
                'title' => 'Bal la Savoy',
                'event_id' => '860',
                'slug' => 'Bal-la-Savoy',
                'show_type' => 'Operetă',
                'date_line' => '<span class="galben">VINERI</span> 17 APRILIE 2026, <span class="galben"><span>Ora</span>: 19:00</span>',
            ]),
            orCard([
                'title' => 'Aida',
                'event_id' => '883',
                'slug' => 'Aida',
                'date_line' => '<span class="galben">DUMINICĂ</span> 26 APRILIE 2026, <span class="galben"><span>Ora</span>: 18:00</span>',
            ]),
        ]))]);

        $events = orScrapeToCollection(new TestOperaTimisoaraScraper);

        expect($events)->toHaveCount(3);
        expect($events->pluck('title')->all())->toBe(['Cavalleria rusticana', 'Bal la Savoy', 'Aida']);
        expect($events->pluck('sourceId')->all())->toBe(['882', '860', '883']);
    });

    it('emits 0 events when the page has no carte-eveniment cards', function (): void {
        Http::fake(['https://www.ort.ro/*' => Http::response(orEmptyPage())]);

        $events = orScrapeToCollection(new TestOperaTimisoaraScraper);

        expect($events)->toHaveCount(0);
        Http::assertSentCount(1);
    });

    it('handles a festival note in the data-banner without breaking date parsing', function (): void {
        Http::fake(['https://www.ort.ro/*' => Http::response(orPage([
            orCard([
                'date_line' => '<span class="galben">DUMINICĂ</span> 26 APRILIE 2026, <span class="galben"><span>Ora</span>: 18:00</span>',
                'festival_note' => 'Festivalul Internațional „Timișoara Muzicală", ediția a L - a',
            ]),
        ]))]);

        $events = orScrapeToCollection(new TestOperaTimisoaraScraper);

        expect($events)->toHaveCount(1);
        expect($events->first()->startsAt)->toBe('2026-04-26 15:00:00');
    });

    it('makes only one HTTP request per scrape (no pagination)', function (): void {
        Http::fake(['https://www.ort.ro/*' => Http::response(orPage([orCard()]))]);

        orScrapeToCollection(new TestOperaTimisoaraScraper);

        Http::assertSentCount(1);
    });

    it('sets startsAt to null when the date banner cannot be parsed', function (): void {
        Http::fake(['https://www.ort.ro/*' => Http::response(orPage([
            orCard(['date_line' => 'Data TBD']),
        ]))]);

        $events = orScrapeToCollection(new TestOperaTimisoaraScraper);
        expect($events->first()->startsAt)->toBeNull();
    });

    it('all events share the same hardcoded venue and address', function (): void {
        Http::fake(['https://www.ort.ro/*' => Http::response(orPage([
            orCard(['title' => 'Show A', 'event_id' => '1']),
            orCard(['title' => 'Show B', 'event_id' => '2']),
        ]))]);

        $events = orScrapeToCollection(new TestOperaTimisoaraScraper);

        foreach ($events as $event) {
            expect($event->venue)->toBe('Opera Națională Română Timișoara');
            expect($event->address)->toBe('Timișoara, B-dul Regele Carol I nr. 3');
        }
    });

    it('parses winter month dates correctly', function (): void {
        // January performance — UTC+2 (EET, not EEST)
        Http::fake(['https://www.ort.ro/*' => Http::response(orPage([
            orCard([
                'date_line' => '<span class="galben">VINERI</span> 16 IANUARIE 2026, <span class="galben"><span>Ora</span>: 19:00</span>',
            ]),
        ]))]);

        $events = orScrapeToCollection(new TestOperaTimisoaraScraper);
        // Europe/Bucharest in January is UTC+2 (EET), so 19:00 → 17:00 UTC
        expect($events->first()->startsAt)->toBe('2026-01-16 17:00:00');
    });

});
