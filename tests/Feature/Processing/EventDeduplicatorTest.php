<?php

declare(strict_types=1);

use App\DTOs\RawEvent;
use App\Enums\EventCategory;
use App\Models\Event;
use App\Services\Processing\EventDeduplicator;

// ---------------------------------------------------------------------------
// Fixture helper — inserts a minimal Event row directly (bypasses EventPipeline)
// ---------------------------------------------------------------------------

/**
 * @param  array<string, mixed>  $overrides
 */
function dedupEvent(array $overrides = []): Event
{
    return Event::withoutSyncingToSearch(fn () => Event::create([
        'title' => $overrides['title'] ?? 'Concert Phoenix',
        'source' => $overrides['source'] ?? 'iabilet',
        'source_url' => $overrides['source_url'] ?? 'https://iabilet.ro/concert-phoenix/',
        'fingerprint' => $overrides['fingerprint'] ?? 'fp-'.uniqid(),
        'category' => EventCategory::Other,
        'tags' => [],
        'city' => $overrides['city'] ?? 'Timișoara',
        'starts_at' => $overrides['starts_at'] ?? '2026-05-10 19:00:00',
        'currency' => 'RON',
        'is_free' => false,
        'is_classified' => false,
        'is_geocoded' => false,
        'is_enriched' => false,
    ]));
}

/**
 * @param  array<string, mixed>  $overrides
 */
function dedupRawEvent(array $overrides = []): RawEvent
{
    return new RawEvent(
        title: $overrides['title'] ?? 'Concert Phoenix',
        description: null,
        sourceUrl: $overrides['sourceUrl'] ?? 'https://iabilet.ro/concert-phoenix/',
        sourceId: null,
        source: $overrides['source'] ?? 'iabilet',
        venue: null,
        address: null,
        city: $overrides['city'] ?? 'Timișoara',
        // Use array_key_exists so callers can explicitly pass null without ?? swallowing it
        startsAt: array_key_exists('startsAt', $overrides) ? $overrides['startsAt'] : '2026-05-10 19:00:00',
        endsAt: null,
        priceMin: null,
        priceMax: null,
        currency: null,
        isFree: null,
        imageUrl: null,
        metadata: [],
    );
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('EventDeduplicator', function (): void {

    // -----------------------------------------------------------------------
    // generateFingerprint()
    // -----------------------------------------------------------------------

    it('returns a 64-character SHA-256 hex string', function (): void {
        $deduplicator = app(EventDeduplicator::class);
        $fingerprint = $deduplicator->generateFingerprint(dedupRawEvent());

        expect($fingerprint)->toBeString()->toHaveLength(64);
    });

    it('returns the same hash for identical inputs', function (): void {
        $deduplicator = app(EventDeduplicator::class);
        $event = dedupRawEvent();

        expect($deduplicator->generateFingerprint($event))
            ->toBe($deduplicator->generateFingerprint($event));
    });

    it('strips punctuation and special characters before hashing', function (): void {
        // generateFingerprint uses preg_replace('/[^\p{L}\p{N}\s]/u', '') which removes punctuation
        // but keeps Unicode letters (including diacritics). Two titles that differ only in
        // punctuation produce the same fingerprint; diacritics vs plain letters do not.
        $deduplicator = app(EventDeduplicator::class);

        $withPunct = dedupRawEvent(['title' => 'Concert Phoenix!']);
        $withoutPunct = dedupRawEvent(['title' => 'Concert Phoenix']);

        expect($deduplicator->generateFingerprint($withPunct))
            ->toBe($deduplicator->generateFingerprint($withoutPunct));
    });

    it('produces a valid consistent fingerprint when startsAt is null', function (): void {
        $deduplicator = app(EventDeduplicator::class);
        $event = dedupRawEvent(['startsAt' => null]);

        $fp1 = $deduplicator->generateFingerprint($event);
        $fp2 = $deduplicator->generateFingerprint($event);

        expect($fp1)->toBeString()->toHaveLength(64)->toBe($fp2);
    });

    // -----------------------------------------------------------------------
    // isDuplicate()
    // -----------------------------------------------------------------------

    it('returns false when no event with that fingerprint is in the DB', function (): void {
        $deduplicator = app(EventDeduplicator::class);

        expect($deduplicator->isDuplicate('nonexistent-fingerprint-abc123'))->toBeFalse();
    });

    it('returns true after an event with the matching fingerprint is stored', function (): void {
        $deduplicator = app(EventDeduplicator::class);
        $fp = 'known-fingerprint-xyz';

        dedupEvent(['fingerprint' => $fp]);

        expect($deduplicator->isDuplicate($fp))->toBeTrue();
    });

    it('returns false for a fingerprint that belongs to a different event', function (): void {
        $deduplicator = app(EventDeduplicator::class);

        dedupEvent(['fingerprint' => 'fp-event-a']);

        expect($deduplicator->isDuplicate('fp-event-b'))->toBeFalse();
    });

    // -----------------------------------------------------------------------
    // findFuzzyDuplicates()
    // -----------------------------------------------------------------------

    it('returns null when the raw event has no startsAt', function (): void {
        $deduplicator = app(EventDeduplicator::class);
        dedupEvent();

        expect($deduplicator->findFuzzyDuplicates(dedupRawEvent(['startsAt' => null])))->toBeNull();
    });

    it('returns null when no events in the DB are within the ±2-hour window', function (): void {
        $deduplicator = app(EventDeduplicator::class);

        // Stored at 19:00, incoming at 22:01 — 3 h 1 m ahead, beyond addHours(2)
        dedupEvent(['starts_at' => '2026-05-10 19:00:00']);

        $incoming = dedupRawEvent(['startsAt' => '2026-05-10 22:01:00']);

        expect($deduplicator->findFuzzyDuplicates($incoming))->toBeNull();
    });

    it('returns the matching event when title similarity is ≥80% and time is within ±2 hours', function (): void {
        $deduplicator = app(EventDeduplicator::class);

        $stored = dedupEvent([
            'title' => 'Concert Phoenix la Sala Capitol',
            'starts_at' => '2026-05-10 19:00:00',
        ]);

        // '@' vs 'la' — same core title, high similarity
        $incoming = dedupRawEvent([
            'title' => 'Concert Phoenix @ Sala Capitol',
            'startsAt' => '2026-05-10 19:00:00',
        ]);

        expect($deduplicator->findFuzzyDuplicates($incoming))->not->toBeNull()
            ->and($deduplicator->findFuzzyDuplicates($incoming)?->id)->toBe($stored->id);
    });

    it('returns null for dissimilar titles at the same time', function (): void {
        $deduplicator = app(EventDeduplicator::class);

        dedupEvent([
            'title' => 'Stand-up Comedy cu Costel',
            'starts_at' => '2026-05-10 20:00:00',
        ]);

        $incoming = dedupRawEvent([
            'title' => 'Opera Aida',
            'startsAt' => '2026-05-10 20:00:00',
        ]);

        expect($deduplicator->findFuzzyDuplicates($incoming))->toBeNull();
    });

    it('returns null for events outside the time window even with identical titles', function (): void {
        $deduplicator = app(EventDeduplicator::class);

        dedupEvent([
            'title' => 'Concert Phoenix',
            'starts_at' => '2026-05-10 16:59:00', // 2h 1m before 19:00
        ]);

        $incoming = dedupRawEvent(['startsAt' => '2026-05-10 19:00:00']);

        expect($deduplicator->findFuzzyDuplicates($incoming))->toBeNull();
    });

    it('does not match events from a different city (cross-city isolation)', function (): void {
        $deduplicator = app(EventDeduplicator::class);

        dedupEvent([
            'title' => 'Concert Phoenix',
            'starts_at' => '2026-05-10 19:00:00',
            'city' => 'Cluj-Napoca',
        ]);

        $incoming = dedupRawEvent([
            'title' => 'Concert Phoenix',
            'startsAt' => '2026-05-10 19:00:00',
            'city' => 'Timișoara',
        ]);

        expect($deduplicator->findFuzzyDuplicates($incoming))->toBeNull();
    });

    it('matches when stored title has diacritics and incoming does not', function (): void {
        $deduplicator = app(EventDeduplicator::class);

        $stored = dedupEvent([
            'title' => 'Concert la Timișoara',
            'starts_at' => '2026-05-10 19:00:00',
        ]);

        $incoming = dedupRawEvent([
            'title' => 'Concert la Timisoara',
            'startsAt' => '2026-05-10 19:00:00',
        ]);

        // similar_text() is byte-based; ș (2 UTF-8 bytes) vs s (1 byte) gives ≥90% similarity
        expect($deduplicator->findFuzzyDuplicates($incoming))->not->toBeNull()
            ->and($deduplicator->findFuzzyDuplicates($incoming)?->id)->toBe($stored->id);
    });

});
