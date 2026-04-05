<?php

declare(strict_types=1);

use App\DTOs\RawEvent;
use App\Services\Scraping\Adapters\AbstractHtmlScraper;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Concrete implementation exposing protected helpers for testing.
 */
class ConcreteHtmlScraper extends AbstractHtmlScraper
{
    public function source(): string
    {
        return 'test_scraper';
    }

    protected function sourceUrl(): string
    {
        return 'https://example.com/events';
    }

    /** @return Collection<int, RawEvent> */
    public function scrape(): Collection
    {
        return collect();
    }

    // Proxy methods to expose protected helpers

    public function testNormalizeText(string $text): string
    {
        return $this->normalizeText($text);
    }

    public function testParseRomanianDate(string $text): ?Carbon
    {
        return $this->parseRomanianDate($text);
    }

    public function testParsePrice(string $text): ?float
    {
        return $this->parsePrice($text);
    }

    public function testStripHtml(string $html): string
    {
        return $this->stripHtml($html);
    }

    public function testGenerateFingerprint(string $title, ?string $date, ?string $venue): string
    {
        return $this->generateFingerprint($title, $date, $venue);
    }
}

// ---------------------------------------------------------------------------
// Shared instance
// ---------------------------------------------------------------------------

$scraper = new ConcreteHtmlScraper;

// ---------------------------------------------------------------------------
// normalizeText
// ---------------------------------------------------------------------------

describe('normalizeText', function () use ($scraper) {
    it('lowercases ASCII text', function () use ($scraper) {
        expect($scraper->testNormalizeText('Hello World'))->toBe('hello world');
    });

    it('strips ă diacritic', function () use ($scraper) {
        expect($scraper->testNormalizeText('Brăila'))->toBe('braila');
    });

    it('strips â diacritic', function () use ($scraper) {
        expect($scraper->testNormalizeText('Câmpina'))->toBe('campina');
    });

    it('strips î diacritic', function () use ($scraper) {
        expect($scraper->testNormalizeText('Târnăveni'))->toBe('tarnaveni');
    });

    it('strips ș comma-below diacritic', function () use ($scraper) {
        expect($scraper->testNormalizeText('Ștefan'))->toBe('stefan');
    });

    it('strips ş cedilla diacritic', function () use ($scraper) {
        expect($scraper->testNormalizeText('Ştiinţă'))->toBe('stiinta');
    });

    it('strips ț comma-below diacritic', function () use ($scraper) {
        expect($scraper->testNormalizeText('Craiţa'))->toBe('craita');
    });

    it('handles uppercase diacritics', function () use ($scraper) {
        expect($scraper->testNormalizeText('ĂÂÎȘȚ'))->toBe('aaist');
    });

    it('trims surrounding whitespace', function () use ($scraper) {
        expect($scraper->testNormalizeText('  event title  '))->toBe('event title');
    });

    it('handles mixed diacritics and ASCII', function () use ($scraper) {
        expect($scraper->testNormalizeText('Timișoara Concert'))->toBe('timisoara concert');
    });
});

// ---------------------------------------------------------------------------
// parseRomanianDate
// ---------------------------------------------------------------------------

describe('parseRomanianDate', function () use ($scraper) {
    it('parses full date "4 aprilie 2026"', function () use ($scraper) {
        $result = $scraper->testParseRomanianDate('4 aprilie 2026');

        expect($result)->toBeInstanceOf(Carbon::class)
            ->and($result->day)->toBe(4)
            ->and($result->month)->toBe(4)
            ->and($result->year)->toBe(2026);
    });

    it('parses abbreviated month "18 apr"', function () use ($scraper) {
        Carbon::setTestNow(Carbon::create(2026, 1, 1));

        $result = $scraper->testParseRomanianDate('18 apr');

        expect($result)->toBeInstanceOf(Carbon::class)
            ->and($result->day)->toBe(18)
            ->and($result->month)->toBe(4)
            ->and($result->year)->toBe(2026);

        Carbon::setTestNow();
    });

    it('parses "Sâ, 18 apr" stripping day-of-week prefix', function () use ($scraper) {
        Carbon::setTestNow(Carbon::create(2026, 1, 1));

        $result = $scraper->testParseRomanianDate('Sâ, 18 apr');

        expect($result)->toBeInstanceOf(Carbon::class)
            ->and($result->day)->toBe(18)
            ->and($result->month)->toBe(4);

        Carbon::setTestNow();
    });

    it('parses "Jo, 26 mar" stripping day-of-week prefix', function () use ($scraper) {
        Carbon::setTestNow(Carbon::create(2026, 1, 1));

        $result = $scraper->testParseRomanianDate('Jo, 26 mar');

        expect($result)->toBeInstanceOf(Carbon::class)
            ->and($result->day)->toBe(26)
            ->and($result->month)->toBe(3);

        Carbon::setTestNow();
    });

    it('parses "Vineri 03/04" as DD/MM', function () use ($scraper) {
        Carbon::setTestNow(Carbon::create(2026, 1, 1));

        $result = $scraper->testParseRomanianDate('Vineri 03/04');

        expect($result)->toBeInstanceOf(Carbon::class)
            ->and($result->day)->toBe(3)
            ->and($result->month)->toBe(4)
            ->and($result->year)->toBe(2026);

        Carbon::setTestNow();
    });

    it('parses "03/04/2026" as DD/MM/YYYY', function () use ($scraper) {
        $result = $scraper->testParseRomanianDate('03/04/2026');

        expect($result)->toBeInstanceOf(Carbon::class)
            ->and($result->day)->toBe(3)
            ->and($result->month)->toBe(4)
            ->and($result->year)->toBe(2026);
    });

    it('parses all Romanian month names', function () use ($scraper) {
        Carbon::setTestNow(Carbon::create(2026, 1, 1));

        $months = [
            'ianuarie' => 1, 'februarie' => 2, 'martie' => 3,
            'aprilie' => 4, 'mai' => 5, 'iunie' => 6,
            'iulie' => 7, 'august' => 8, 'septembrie' => 9,
            'octombrie' => 10, 'noiembrie' => 11, 'decembrie' => 12,
        ];

        foreach ($months as $name => $expected) {
            $result = $scraper->testParseRomanianDate("1 {$name}");
            expect($result?->month)->toBe($expected, "Failed for month: {$name}");
        }

        Carbon::setTestNow();
    });

    it('returns null for invalid input', function () use ($scraper) {
        expect($scraper->testParseRomanianDate('not a date'))->toBeNull();
    });

    it('returns null for empty string', function () use ($scraper) {
        expect($scraper->testParseRomanianDate(''))->toBeNull();
    });
});

// ---------------------------------------------------------------------------
// parsePrice
// ---------------------------------------------------------------------------

describe('parsePrice', function () use ($scraper) {
    it('returns 0.0 for "Gratuit"', function () use ($scraper) {
        expect($scraper->testParsePrice('Gratuit'))->toBe(0.0);
    });

    it('returns 0.0 for "Free"', function () use ($scraper) {
        expect($scraper->testParsePrice('Free'))->toBe(0.0);
    });

    it('is case-insensitive for free variants', function () use ($scraper) {
        expect($scraper->testParsePrice('GRATUIT'))->toBe(0.0)
            ->and($scraper->testParsePrice('free'))->toBe(0.0);
    });

    it('divides lei vechi by 100', function () use ($scraper) {
        expect($scraper->testParsePrice('15000 lei vechi'))->toBe(150.0);
    });

    it('handles dot thousands separator in lei vechi', function () use ($scraper) {
        expect($scraper->testParsePrice('150.000 lei vechi'))->toBe(1500.0);
    });

    it('parses "de la 20 lei"', function () use ($scraper) {
        expect($scraper->testParsePrice('de la 20 lei'))->toBe(20.0);
    });

    it('parses "de la 35 RON"', function () use ($scraper) {
        expect($scraper->testParsePrice('de la 35 RON'))->toBe(35.0);
    });

    it('parses "150 RON"', function () use ($scraper) {
        expect($scraper->testParsePrice('150 RON'))->toBe(150.0);
    });

    it('parses "30 lei"', function () use ($scraper) {
        expect($scraper->testParsePrice('30 lei'))->toBe(30.0);
    });

    it('parses decimal price "49,99 RON"', function () use ($scraper) {
        expect($scraper->testParsePrice('49,99 RON'))->toBe(49.99);
    });

    it('returns null for unrecognised input', function () use ($scraper) {
        expect($scraper->testParsePrice('contact organizer'))->toBeNull();
    });

    it('returns null for empty string', function () use ($scraper) {
        expect($scraper->testParsePrice(''))->toBeNull();
    });
});

// ---------------------------------------------------------------------------
// generateFingerprint
// ---------------------------------------------------------------------------

describe('generateFingerprint', function () use ($scraper) {
    it('produces a 64-character sha256 hex string', function () use ($scraper) {
        $fp = $scraper->testGenerateFingerprint('Concert Jazz', '2026-04-10', 'Filarmonica');

        expect($fp)->toBeString()->toHaveLength(64);
    });

    it('returns the same hash for identical inputs', function () use ($scraper) {
        $fp1 = $scraper->testGenerateFingerprint('Concert Jazz', '2026-04-10', 'Filarmonica');
        $fp2 = $scraper->testGenerateFingerprint('Concert Jazz', '2026-04-10', 'Filarmonica');

        expect($fp1)->toBe($fp2);
    });

    it('returns a different hash when title differs', function () use ($scraper) {
        $fp1 = $scraper->testGenerateFingerprint('Concert Jazz', '2026-04-10', 'Filarmonica');
        $fp2 = $scraper->testGenerateFingerprint('Concert Rock', '2026-04-10', 'Filarmonica');

        expect($fp1)->not->toBe($fp2);
    });

    it('normalises diacritics before hashing for consistent fingerprints', function () use ($scraper) {
        $fp1 = $scraper->testGenerateFingerprint('Concertul de Primăvară', null, 'Timișoara');
        $fp2 = $scraper->testGenerateFingerprint('concertul de primavara', null, 'timisoara');

        expect($fp1)->toBe($fp2);
    });

    it('handles null date and venue gracefully', function () use ($scraper) {
        $fp = $scraper->testGenerateFingerprint('Some Event', null, null);

        expect($fp)->toBeString()->toHaveLength(64);
    });
});
