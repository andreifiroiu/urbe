<?php

declare(strict_types=1);

namespace App\Services\Processing;

use App\DTOs\RawEvent;
use App\Models\Event;

class EventDeduplicator
{
    /**
     * Detects and prevents duplicate events from entering the system.
     *
     * Uses both exact fingerprint matching (for identical events from the
     * same source) and fuzzy matching (for the same event listed on
     * different sources with slightly different titles or times).
     */
    public function __construct() {}

    /**
     * Generate a deterministic fingerprint from a raw event for exact dedup.
     *
     * Combines the event's title, source URL, and start time into a
     * normalized string and hashes it. Two RawEvents with the same title
     * from the same URL starting at the same time will always produce the
     * same fingerprint.
     */
    public function generateFingerprint(RawEvent $event): string
    {
        // TODO: Normalize the title: lowercase, trim whitespace, strip punctuation
        // TODO: Normalize the source URL: lowercase, remove trailing slashes and query params
        // TODO: Normalize startsAt: parse to Y-m-d H:i format (minute precision) or empty string if null
        // TODO: Concatenate: "{normalizedTitle}|{normalizedUrl}|{normalizedStartsAt}"
        // TODO: Return sha256 hash of the concatenated string
        return '';
    }

    /**
     * Check if an event with the given fingerprint already exists in the database.
     */
    public function isDuplicate(string $fingerprint): bool
    {
        // TODO: Query Event::where('fingerprint', $fingerprint)->exists()
        // TODO: Return the boolean result
        return false;
    }

    /**
     * Fuzzy duplicate check using title similarity and date proximity.
     *
     * Finds events that are likely the same real-world event but scraped from
     * a different source or with minor text variations. Uses title similarity
     * (Levenshtein distance or PostgreSQL trigram similarity), date proximity
     * (within 2 hours), and optional venue matching.
     *
     * @return Event|null The matching existing event, or null if no fuzzy match.
     */
    public function findFuzzyDuplicates(RawEvent $event): ?Event
    {
        // TODO: If startsAt is null, return null (cannot fuzzy match without date)
        // TODO: Parse startsAt into a Carbon instance
        // TODO: Query upcoming events within a 2-hour window: starts_at BETWEEN (startsAt - 2h) AND (startsAt + 2h)
        // TODO: For each candidate event:
        //   TODO: Calculate title similarity using similar_text() or levenshtein()
        //   TODO: Normalize both titles before comparison (lowercase, trim)
        //   TODO: If similarity > config('eventpulse.dedup.title_similarity_threshold', 0.8):
        //     TODO: If venue is provided and matches (case-insensitive), boost confidence
        //     TODO: Return the matching Event
        // TODO: Return null if no fuzzy match found
        return null;
    }
}
