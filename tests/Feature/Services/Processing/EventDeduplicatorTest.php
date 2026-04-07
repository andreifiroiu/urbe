<?php

declare(strict_types=1);

use App\DTOs\RawEvent;
use App\Models\Event;
use App\Services\Processing\EventDeduplicator;

beforeEach(function () {
    $this->deduplicator = new EventDeduplicator;
});

it('generates consistent fingerprints for the same event data', function () {
    $event = new RawEvent(
        title: 'Jazz Night at Control',
        description: 'Live jazz every Friday',
        sourceUrl: 'https://example.com/events/jazz-night',
        sourceId: 'evt-123',
        source: 'generic_html',
        venue: 'Control Club',
        address: 'Str. Constantin Mille 4',
        city: 'Bucharest',
        startsAt: '2026-04-10 20:00:00',
        endsAt: '2026-04-10 23:00:00',
        priceMin: 50.0,
        priceMax: 50.0,
        currency: 'RON',
        isFree: false,
        imageUrl: null,
        metadata: [],
    );

    $fingerprint1 = $this->deduplicator->generateFingerprint($event);
    $fingerprint2 = $this->deduplicator->generateFingerprint($event);

    expect($fingerprint1)->toBe($fingerprint2);
    expect($fingerprint1)->toBeString()->not->toBeEmpty();
});

it('generates different fingerprints for different events', function () {
    $event1 = new RawEvent(
        title: 'Jazz Night at Control',
        description: null,
        sourceUrl: 'https://example.com/events/jazz-night',
        sourceId: null,
        source: 'generic_html',
        venue: null,
        address: null,
        city: null,
        startsAt: '2026-04-10 20:00:00',
        endsAt: null,
        priceMin: null,
        priceMax: null,
        currency: null,
        isFree: null,
        imageUrl: null,
        metadata: [],
    );

    $event2 = new RawEvent(
        title: 'Rock Concert at Expirat',
        description: null,
        sourceUrl: 'https://example.com/events/rock-concert',
        sourceId: null,
        source: 'generic_html',
        venue: null,
        address: null,
        city: null,
        startsAt: '2026-04-11 21:00:00',
        endsAt: null,
        priceMin: null,
        priceMax: null,
        currency: null,
        isFree: null,
        imageUrl: null,
        metadata: [],
    );

    $fp1 = $this->deduplicator->generateFingerprint($event1);
    $fp2 = $this->deduplicator->generateFingerprint($event2);

    expect($fp1)->not->toBe($fp2);
});

it('detects duplicate events by fingerprint', function () {
    $event = Event::factory()->create([
        'fingerprint' => 'abc123hash',
    ]);

    expect($this->deduplicator->isDuplicate('abc123hash'))->toBeTrue();
    expect($this->deduplicator->isDuplicate('xyz789hash'))->toBeFalse();
});
