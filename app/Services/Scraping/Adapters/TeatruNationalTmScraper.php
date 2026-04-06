<?php

declare(strict_types=1);

namespace App\Services\Scraping\Adapters;

use App\DTOs\RawEvent;
use Carbon\Carbon;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Log;

class TeatruNationalTmScraper extends AbstractHtmlScraper
{
    private const string SOURCE = 'teatru_national_tm';

    private const string BASE_URL = 'https://www.tntm.ro';

    private const string VENUE = 'Teatrul Național Mihai Eminescu Timișoara';

    private const string ADDRESS = 'Timișoara, Str. Mărășești nr. 2';

    private const string TIMEZONE = 'Europe/Bucharest';

    public function adapterKey(): string
    {
        return self::SOURCE;
    }

    public function sourceIdentifier(array $sourceConfig): string
    {
        return self::SOURCE.'@tntm.ro';
    }

    public function scrape(array $sourceConfig, array $cityConfig, callable $onEvent): void
    {
        $url = $this->getUrl($sourceConfig);
        $html = $this->fetchPage($url);

        if ($html === '') {
            Log::warning('TeatruNationalTmScraper: empty response', ['url' => $url]);

            return;
        }

        $emitted = 0;

        foreach ($this->parseEvents($html, $cityConfig['label']) as $event) {
            $onEvent($event);
            $emitted++;
        }

        Log::info('TeatruNationalTmScraper: scrape complete', ['emitted' => $emitted, 'url' => $url]);
    }

    /**
     * Parse all performance cards from the monthly program page.
     *
     * Each card is an `<article class="post_item">` with a date/time/stage span,
     * poster image, and a "more-link" pointing to the detail page.
     *
     * @return list<RawEvent>
     */
    private function parseEvents(string $html, string $cityLabel): array
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        $xpath = new DOMXPath($dom);

        $articles = $xpath->query(
            '//article[contains(@class,"post_item")][.//span[contains(@class,"post_meta_item")]]'
        );

        if ($articles === false || $articles->length === 0) {
            return [];
        }

        $events = [];

        foreach ($articles as $article) {
            if (! $article instanceof DOMElement) {
                continue;
            }

            $event = $this->parseCard($article, $xpath, $cityLabel);
            if ($event !== null) {
                $events[] = $event;
            }
        }

        return $events;
    }

    /**
     * Parse a single `article.post_item` into a RawEvent.
     */
    private function parseCard(DOMElement $article, DOMXPath $xpath, string $cityLabel): ?RawEvent
    {
        // Title — h4.post_title
        $titleNodes = $xpath->query('.//h4[contains(@class,"post_title")]', $article);
        if ($titleNodes === false || $titleNodes->length === 0) {
            return null;
        }

        $title = trim($titleNodes->item(0)->textContent);
        if ($title === '') {
            return null;
        }

        // Detail URL + source ID — a.more-link
        $linkNodes = $xpath->query('.//a[contains(@class,"more-link")]', $article);
        if ($linkNodes === false || $linkNodes->length === 0) {
            return null;
        }

        $linkNode = $linkNodes->item(0);
        if (! $linkNode instanceof DOMElement) {
            return null;
        }

        $href = $linkNode->getAttribute('href');
        if ($href === '') {
            return null;
        }

        $sourceUrl = self::BASE_URL.'/'.ltrim($href, '/');
        $sourceId = basename(rtrim($href, '/'));

        // Date / time / stage — span.post_meta_item
        $spanNodes = $xpath->query('.//span[contains(@class,"post_meta_item")]', $article);
        $startsAt = null;
        $stage = null;

        if ($spanNodes !== false && $spanNodes->length > 0) {
            $spanHtml = $this->outerHtml($spanNodes->item(0));
            [$startsAt, $stage] = $this->parseDateSpan($spanHtml);
        }

        // Image — img.imgsizehome
        $imgNodes = $xpath->query('.//img[contains(@class,"imgsizehome")]', $article);
        $imageUrl = null;

        if ($imgNodes !== false && $imgNodes->length > 0) {
            $imgNode = $imgNodes->item(0);
            if ($imgNode instanceof DOMElement) {
                $src = $imgNode->getAttribute('src');
                $imageUrl = $src !== '' ? $src : null;
            }
        }

        // Age rating — a[href*="/events/categories/"]
        $ageNodes = $xpath->query('.//a[contains(@href,"/events/categories/")]', $article);
        $ageRating = null;

        if ($ageNodes !== false && $ageNodes->length > 0) {
            $raw = trim($ageNodes->item(0)->textContent);
            $ageRating = $raw !== '' ? $raw : null;
        }

        Log::debug('TeatruNationalTmScraper: parsed event', [
            'title' => $title,
            'starts_at' => $startsAt,
            'stage' => $stage,
        ]);

        /** @var array<string, string|null> $metadata */
        $metadata = array_filter([
            'category_hint' => 'Theatre',
            'stage' => $stage,
            'age_rating' => $ageRating,
        ], fn (mixed $v): bool => $v !== null);

        $metadata['category_hint'] = 'Theatre';

        return new RawEvent(
            title: $title,
            description: null,
            sourceUrl: $sourceUrl,
            sourceId: $sourceId !== '' ? $sourceId : null,
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
     * Parse the date/time/stage span HTML into a UTC datetime string and stage name.
     *
     * Span format: "<strong>Marți 07.04</strong> / <strong>19:00</strong> - Sala Mare"
     *
     * @return array{?string, ?string} [startsAt (UTC), stage]
     */
    protected function parseDateSpan(string $spanHtml): array
    {
        $text = $this->stripHtml($spanHtml);

        // Extract date: "07.04"
        if (! preg_match('/(\d{1,2})\.(\d{2})/', $text, $dateMatch)) {
            return [null, null];
        }

        $day = (int) $dateMatch[1];
        $month = (int) $dateMatch[2];

        // Extract time: "19:00"
        $hours = 20;
        $minutes = 0;
        if (preg_match('/(\d{2}):(\d{2})/', $text, $timeMatch)) {
            $hours = (int) $timeMatch[1];
            $minutes = (int) $timeMatch[2];
        }

        // Extract stage: everything after " - "
        $stage = null;
        $dashPos = mb_strpos($text, ' - ');
        if ($dashPos !== false) {
            $raw = trim(mb_substr($text, $dashPos + 3));
            $stage = $raw !== '' ? $raw : null;
        }

        // Infer year: if the date (with current year) is more than 60 days in the past,
        // assume it belongs to next year (handles December → January program rollover).
        $year = (int) now()->year;

        try {
            $candidate = Carbon::create($year, $month, $day, $hours, $minutes, 0, self::TIMEZONE);

            if ($candidate->diffInDays(now(), false) > 60) {
                $year++;
            }

            return [
                Carbon::create($year, $month, $day, $hours, $minutes, 0, self::TIMEZONE)
                    ->utc()
                    ->toDateTimeString(),
                $stage,
            ];
        } catch (\Throwable) {
            return [null, null];
        }
    }

    /**
     * Return the outer HTML of a DOM node by serializing it via the parent document.
     */
    private function outerHtml(\DOMNode $node): string
    {
        $doc = new DOMDocument;
        $doc->appendChild($doc->importNode($node, true));
        $html = $doc->saveHTML();

        return $html !== false ? $html : '';
    }
}
