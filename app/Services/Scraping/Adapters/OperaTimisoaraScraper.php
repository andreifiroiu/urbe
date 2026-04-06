<?php

declare(strict_types=1);

namespace App\Services\Scraping\Adapters;

use App\DTOs\RawEvent;
use Carbon\Carbon;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Log;

class OperaTimisoaraScraper extends AbstractHtmlScraper
{
    private const string SOURCE = 'opera_timisoara';

    private const string BASE_URL = 'https://www.ort.ro';

    private const string VENUE = 'Opera Națională Română Timișoara';

    private const string ADDRESS = 'Timișoara, B-dul Regele Carol I nr. 3';

    private const string TIMEZONE = 'Europe/Bucharest';

    /** @var array<string, int> */
    private const array MONTH_MAP = [
        'IANUARIE' => 1, 'FEBRUARIE' => 2, 'MARTIE' => 3, 'APRILIE' => 4,
        'MAI' => 5, 'IUNIE' => 6, 'IULIE' => 7, 'AUGUST' => 8,
        'SEPTEMBRIE' => 9, 'OCTOMBRIE' => 10, 'NOIEMBRIE' => 11, 'DECEMBRIE' => 12,
    ];

    public function adapterKey(): string
    {
        return self::SOURCE;
    }

    public function sourceIdentifier(array $sourceConfig): string
    {
        return self::SOURCE.'@ort.ro';
    }

    public function scrape(array $sourceConfig, array $cityConfig, callable $onEvent): void
    {
        $url = $this->getUrl($sourceConfig);
        $html = $this->fetchPage($url);

        if ($html === '') {
            Log::warning('OperaTimisoaraScraper: empty response', ['url' => $url]);

            return;
        }

        $emitted = 0;

        foreach ($this->parseEvents($html, $cityConfig['label']) as $event) {
            $onEvent($event);
            $emitted++;
        }

        Log::info('OperaTimisoaraScraper: scrape complete', ['emitted' => $emitted, 'url' => $url]);
    }

    /**
     * Parse all performance cards from the schedule page HTML.
     *
     * Each card has: show type label (Operă/Balet/Operetă), image, composer,
     * title, description (show type + librettist), date/time, and detail URL.
     *
     * @return list<RawEvent>
     */
    private function parseEvents(string $html, string $cityLabel): array
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        $xpath = new DOMXPath($dom);

        $cards = $xpath->query('//div[contains(@class,"carte-eveniment")]');

        if ($cards === false || $cards->length === 0) {
            return [];
        }

        $events = [];

        foreach ($cards as $card) {
            if (! $card instanceof DOMElement) {
                continue;
            }

            $event = $this->parseCard($card, $xpath, $cityLabel);
            if ($event !== null) {
                $events[] = $event;
            }
        }

        return $events;
    }

    /**
     * Parse a single `div.carte-eveniment` into a RawEvent.
     */
    private function parseCard(DOMElement $card, DOMXPath $xpath, string $cityLabel): ?RawEvent
    {
        // Title + source URL — inside div.titlu-eveniment2 > a
        $titleLinks = $xpath->query('.//div[contains(@class,"titlu-eveniment2")]//a', $card);

        if ($titleLinks === false || $titleLinks->length === 0) {
            return null;
        }

        $titleLink = $titleLinks->item(0);
        if (! $titleLink instanceof DOMElement) {
            return null;
        }

        $title = trim($titleLink->textContent);
        if ($title === '') {
            return null;
        }

        $relativeUrl = $titleLink->getAttribute('href');
        if ($relativeUrl === '') {
            return null;
        }

        $sourceUrl = $this->absoluteUrl($relativeUrl);
        $sourceId = $this->extractEventId($relativeUrl);

        // Composer/creator — div.titlu-eveniment3
        $composerNodes = $xpath->query('.//div[contains(@class,"titlu-eveniment3")]', $card);
        $composer = null;
        if ($composerNodes !== false && $composerNodes->length > 0) {
            $raw = trim($composerNodes->item(0)->textContent);
            $composer = $raw !== '' ? $raw : null;
        }

        // Description — div.titlu-eveniment1 (show type + librettist info)
        $descNodes = $xpath->query('.//div[contains(@class,"titlu-eveniment1")]', $card);
        $description = null;
        if ($descNodes !== false && $descNodes->length > 0) {
            $rawDesc = $this->stripHtml($descNodes->item(0)->textContent);
            if ($rawDesc !== '') {
                $description = $composer !== null
                    ? $composer.' — '.$rawDesc
                    : $rawDesc;
            }
        }

        // Date + time — div.data-banner
        $dateNodes = $xpath->query('.//div[contains(@class,"data-banner")]', $card);
        $startsAt = null;
        if ($dateNodes !== false && $dateNodes->length > 0) {
            $dateBanner = $dateNodes->item(0)->textContent;
            $startsAt = $this->parseDateBanner($dateBanner);
        }

        // Image and show-type label — in the parent node, before this card
        $parent = $card->parentNode;
        $imageUrl = null;
        $showType = null;

        if ($parent instanceof DOMElement) {
            $imgNodes = $xpath->query('.//img[contains(@class,"pozaload")]', $parent);
            if ($imgNodes !== false && $imgNodes->length > 0) {
                $imgNode = $imgNodes->item(0);
                if ($imgNode instanceof DOMElement) {
                    $src = $imgNode->getAttribute('src');
                    $imageUrl = $src !== '' ? $this->absoluteUrl($src) : null;
                }
            }

            $typeNodes = $xpath->query('.//div[contains(@class,"nume-tip-eveniment2")]', $parent);
            if ($typeNodes !== false && $typeNodes->length > 0) {
                $raw = trim($typeNodes->item(0)->textContent);
                $showType = $raw !== '' ? $raw : null;
            }
        }

        $metadata = $this->buildMetadata($showType, $description);

        Log::debug('OperaTimisoaraScraper: parsed event', [
            'title' => $title,
            'starts_at' => $startsAt,
            'show_type' => $showType,
        ]);

        return new RawEvent(
            title: $title,
            description: $description,
            sourceUrl: $sourceUrl,
            sourceId: $sourceId,
            source: self::SOURCE,
            venue: self::VENUE,
            address: self::ADDRESS,
            city: $cityLabel,
            startsAt: $startsAt,
            endsAt: null,
            priceMin: null,
            priceMax: null,
            currency: null,
            isFree: false,
            imageUrl: $imageUrl,
            metadata: $metadata,
        );
    }

    /**
     * Parse the date banner text into a UTC datetime string.
     *
     * Examples:
     *   "MIERCURI                    08 APRILIE 2026, Ora: 19:00"
     *   "DUMINICĂ                    26 APRILIE 2026, Ora: 18:00\nFestivalul..."
     */
    protected function parseDateBanner(string $text): ?string
    {
        $text = $this->stripHtml($text);

        // Extract date: "08 APRILIE 2026"
        if (! preg_match('/(\d{1,2})\s+([A-ZĂÂÎȘȚ]+)\s+(\d{4})/u', $text, $dateMatch)) {
            return null;
        }

        $day = (int) $dateMatch[1];
        $monthName = $dateMatch[2];
        $year = (int) $dateMatch[3];
        $month = self::MONTH_MAP[$monthName] ?? null;

        if ($month === null) {
            return null;
        }

        // Extract time: "Ora: 19:00" or "ORA: 19:00"
        $hours = 20;
        $minutes = 0;
        if (preg_match('/Ora\s*:\s*(\d{2}):(\d{2})/iu', $text, $timeMatch)) {
            $hours = (int) $timeMatch[1];
            $minutes = (int) $timeMatch[2];
        }

        try {
            return Carbon::create($year, $month, $day, $hours, $minutes, 0, self::TIMEZONE)
                ->utc()
                ->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Determine category hint and genre from the show-type label and description.
     *
     * @return array<string, string>
     */
    private function buildMetadata(?string $showType, ?string $description): array
    {
        $needle = mb_strtolower($showType ?? '');

        if (str_contains($needle, 'balet')) {
            return ['category_hint' => 'Ballet', 'genre' => 'ballet'];
        }

        if (str_contains($needle, 'concert')) {
            return ['category_hint' => 'Classical', 'genre' => 'concert'];
        }

        // Fallback check in description (e.g., "Balet - rock în două acte")
        $descNeedle = mb_strtolower($description ?? '');
        if (str_contains($descNeedle, 'balet')) {
            return ['category_hint' => 'Ballet', 'genre' => 'ballet'];
        }

        // Operă, Operetă, and everything else → Opera
        return ['category_hint' => 'Opera', 'genre' => mb_strtolower($showType ?? 'opera')];
    }

    /**
     * Prepend the site host to a relative path.
     */
    private function absoluteUrl(string $path): string
    {
        if (str_starts_with($path, 'http')) {
            return $path;
        }

        return self::BASE_URL.'/'.ltrim($path, '/');
    }

    /**
     * Extract the numeric event ID from a URL like "/eveniment/882/ro/Cavalleria-rusticana.html".
     */
    private function extractEventId(string $relativeUrl): ?string
    {
        if (preg_match('#/eveniment/(\d+)/#', $relativeUrl, $m)) {
            return $m[1];
        }

        return null;
    }
}
