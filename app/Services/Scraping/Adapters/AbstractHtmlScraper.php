<?php

declare(strict_types=1);

namespace App\Services\Scraping\Adapters;

use App\Contracts\ScraperAdapter;
use App\DTOs\RawEvent;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class AbstractHtmlScraper implements ScraperAdapter
{
    /** @var array<int, string> */
    private const array USER_AGENTS = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:123.0) Gecko/20100101 Firefox/123.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_3_1) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.3.1 Safari/605.1.15',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36 Edg/121.0.0.0',
    ];

    abstract public function source(): string;

    /** @return Collection<int, RawEvent> */
    abstract public function scrape(): Collection;

    abstract protected function sourceUrl(): string;

    public function supports(string $source): bool
    {
        return $source === $this->source();
    }

    public function isAvailable(): bool
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => $this->randomUserAgent(),
            ])->head($this->sourceUrl());

            return $response->status() < 400;
        } catch (\Throwable $e) {
            Log::warning("Scraper availability check failed for {$this->source()}", [
                'url' => $this->sourceUrl(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Fetch the HTML content of a single page.
     *
     * Adds a random delay, sets realistic browser headers, and retries once
     * on 429/503 responses with a 10-second backoff. Returns an empty string
     * on any failure so callers can skip gracefully.
     */
    protected function fetchPage(string $url): string
    {
        sleep(rand(2, 5));

        try {
            $response = Http::withHeaders([
                'User-Agent' => $this->randomUserAgent(),
                'Accept-Language' => 'ro-RO,ro;q=0.9,en;q=0.8',
            ])->get($url);

            if ($response->status() === 429 || $response->status() === 503) {
                sleep(10);

                $response = Http::withHeaders([
                    'User-Agent' => $this->randomUserAgent(),
                    'Accept-Language' => 'ro-RO,ro;q=0.9,en;q=0.8',
                ])->get($url);
            }

            if ($response->failed()) {
                Log::warning("Failed to fetch page for {$this->source()}", [
                    'url' => $url,
                    'status' => $response->status(),
                ]);

                return '';
            }

            return $response->body();
        } catch (\Throwable $e) {
            Log::warning("Exception fetching page for {$this->source()}", [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }

    /**
     * Fetch all pages of a paginated source up to $maxPages.
     *
     * @return Collection<int, string>
     */
    protected function fetchAllPages(string $baseUrl, int $maxPages = 10): Collection
    {
        $pages = collect();

        for ($page = 1; $page <= $maxPages; $page++) {
            $html = $this->fetchPage($this->buildPageUrl($baseUrl, $page));

            if ($html === '') {
                break;
            }

            $pages->push($html);
        }

        return $pages;
    }

    /**
     * Build the URL for a given page number. Subclasses may override for non-standard pagination.
     */
    protected function buildPageUrl(string $baseUrl, int $page): string
    {
        if ($page === 1) {
            return $baseUrl;
        }

        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return "{$baseUrl}{$separator}page={$page}";
    }

    /**
     * Lowercase, strip Romanian diacritics, and trim a string.
     */
    protected function normalizeText(string $text): string
    {
        $diacritics = [
            // Comma-below forms (official Unicode)
            'ș' => 's', 'Ș' => 's',
            'ț' => 't', 'Ț' => 't',
            // Cedilla forms (common legacy encoding)
            'ş' => 's', 'Ş' => 's',
            'ţ' => 't', 'Ţ' => 't',
            // Other Romanian vowels
            'ă' => 'a', 'Ă' => 'a',
            'â' => 'a', 'Â' => 'a',
            'î' => 'i', 'Î' => 'i',
        ];

        return trim(mb_strtolower(strtr($text, $diacritics)));
    }

    /**
     * Parse a Romanian date string into a Carbon instance.
     *
     * Supported formats:
     *   - "4 aprilie 2026" / "18 apr"
     *   - "Sâ, 18 apr" / "Jo, 26 mar"  (day-of-week prefix, ignored)
     *   - "Vineri 03/04"               (DD/MM, current year assumed)
     *   - "03/04/2026"                 (DD/MM/YYYY)
     */
    protected function parseRomanianDate(string $text): ?Carbon
    {
        $text = trim($text);

        /** @var array<string, string> $monthMap */
        $monthMap = [
            'ianuarie' => 'January',  'ian' => 'January',
            'februarie' => 'February', 'feb' => 'February',
            'martie' => 'March',       'mar' => 'March',
            'aprilie' => 'April',      'apr' => 'April',
            'mai' => 'May',
            'iunie' => 'June',         'iun' => 'June',
            'iulie' => 'July',         'iul' => 'July',
            'august' => 'August',      'aug' => 'August',
            'septembrie' => 'September', 'sep' => 'September',
            'octombrie' => 'October',  'oct' => 'October',
            'noiembrie' => 'November', 'noi' => 'November',
            'decembrie' => 'December', 'dec' => 'December',
        ];

        // Strip optional day-of-week prefix (e.g. "Vineri ", "Jo, ", "Sâ, ")
        $text = (string) preg_replace(
            '/^(luni|mar[tț]i|miercuri|joi|vineri|s[aâ]mb[aă]t[aă]|duminic[aă]|lu|ma|mi|jo|vi|s[aâ]|du)[,.\s]+/iu',
            '',
            $text,
        );
        $text = trim($text);

        // DD/MM or DD/MM/YYYY
        if (preg_match('/^(\d{1,2})\/(\d{1,2})(?:\/(\d{4}))?$/', $text, $m)) {
            $year = isset($m[3]) ? (int) $m[3] : now()->year;

            try {
                return Carbon::createFromDate($year, (int) $m[2], (int) $m[1]);
            } catch (\Throwable) {
                return null;
            }
        }

        // DD MONTH [YYYY]  —  e.g. "4 aprilie 2026" or "18 apr"
        if (preg_match('/^(\d{1,2})\s+([a-z\x{0100}-\x{024F}]+)(?:\s+(\d{4}))?$/iu', $text, $m)) {
            $roMonth = mb_strtolower(trim($m[2]));
            $enMonth = $monthMap[$roMonth] ?? null;

            if ($enMonth === null) {
                return null;
            }

            $year = isset($m[3]) ? (int) $m[3] : now()->year;

            try {
                return Carbon::parse("{$m[1]} {$enMonth} {$year}");
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * Parse a Romanian price string into a float (RON).
     *
     * Handles:
     *   - "Gratuit" / "Free"              → 0.0
     *   - "150.000 lei vechi"             → 1500.0  (÷100)
     *   - "de la 20 lei" / "de la 20 RON" → 20.0
     *   - "30 RON" / "30 lei"             → 30.0
     */
    protected function parsePrice(string $text): ?float
    {
        $trimmed = trim($text);

        if (preg_match('/^(gratuit|free)$/iu', $trimmed)) {
            return 0.0;
        }

        // "de la X lei/RON"
        if (preg_match('/de\s+la\s+([\d.,]+)\s*(?:lei|ron)/iu', $trimmed, $m)) {
            return $this->parseNumericPrice($m[1]);
        }

        // "X lei vechi"
        if (preg_match('/([\d.,]+)\s*lei\s+vechi/iu', $trimmed, $m)) {
            $value = $this->parseNumericPrice($m[1]);

            return $value !== null ? round($value / 100.0, 2) : null;
        }

        // "X RON" or "X lei"
        if (preg_match('/([\d.,]+)\s*(?:ron|lei)/iu', $trimmed, $m)) {
            return $this->parseNumericPrice($m[1]);
        }

        return null;
    }

    /**
     * Strip HTML tags, decode entities, and normalise whitespace.
     */
    protected function stripHtml(string $html): string
    {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    /**
     * Generate a deduplication fingerprint from normalised event fields.
     */
    protected function generateFingerprint(string $title, ?string $date, ?string $venue): string
    {
        $normalized = implode('|', [
            $this->normalizeText($title),
            $this->normalizeText($date ?? ''),
            $this->normalizeText($venue ?? ''),
        ]);

        return hash('sha256', $normalized);
    }

    private function randomUserAgent(): string
    {
        return Arr::random(self::USER_AGENTS);
    }

    /**
     * Normalise a numeric price string handling both European and US formats.
     */
    private function parseNumericPrice(string $value): ?float
    {
        // Remove dots used as thousands separators (e.g. "1.500" → "1500")
        $value = (string) preg_replace('/\.(\d{3})(?=[^\d]|$)/', '$1', $value);
        // Replace comma decimal separator with dot
        $value = str_replace(',', '.', $value);

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
