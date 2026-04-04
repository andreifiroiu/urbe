<?php

declare(strict_types=1);

namespace App\Services\Processing;

use App\Models\Event;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Support\Facades\Log;

class EventEnricher
{
    /**
     * Enriches events with geocoding data and additional metadata.
     *
     * Calls external geocoding APIs (Nominatim or Google) to resolve
     * addresses into latitude/longitude coordinates, and optionally
     * fetches extra metadata from the event's source URL.
     */
    public function __construct(
        private readonly HttpClient $http,
    ) {}

    /**
     * Geocode an event's address to populate latitude, longitude, and city.
     *
     * Uses the configured geocoding provider (Nominatim or Google) to convert
     * the event's address or venue into geographic coordinates. Skips events
     * that are already geocoded.
     */
    public function enrichGeocoding(Event $event): Event
    {
        // TODO: If $event->is_geocoded is true, return $event unchanged
        // TODO: Build the geocoding query string from $event->address, $event->venue, $event->city
        // TODO: If no address/venue/city available, mark as geocoded with null coords and return
        // TODO: Determine provider from config('eventpulse.geocoding.provider')
        // TODO: If provider is 'nominatim':
        //   TODO: GET https://nominatim.openstreetmap.org/search with q=query, format=json, limit=1
        //   TODO: Include User-Agent header as required by Nominatim TOS
        //   TODO: Parse lat/lon from first result
        // TODO: If provider is 'google':
        //   TODO: GET https://maps.googleapis.com/maps/api/geocode/json with address=query, key=config key
        //   TODO: Parse lat/lng from results[0].geometry.location
        // TODO: Wrap API call in try/catch
        // TODO: On success: update $event->latitude, $event->longitude, $event->city (if not set)
        // TODO: Set $event->is_geocoded = true
        // TODO: Save the event
        // TODO: On failure: log warning, leave coordinates null, still mark as geocoded to avoid retry loops
        // TODO: Return the updated event
        return $event;
    }

    /**
     * Enrich an event with additional metadata fetched from its source URL.
     *
     * Re-fetches the event's source page to extract any additional data not
     * captured during the initial scrape (e.g., organizer info, full
     * description, ticket links, accessibility info).
     */
    public function enrichMetadata(Event $event): Event
    {
        // TODO: If $event->is_enriched is true, return $event unchanged
        // TODO: If $event->source_url is null, mark as enriched and return
        // TODO: Fetch the source URL using $this->http->get($event->source_url)
        // TODO: Wrap in try/catch with a timeout of config('eventpulse.enrichment.timeout_seconds', 10)
        // TODO: On success:
        //   TODO: Parse HTML with DOMDocument
        //   TODO: Extract Open Graph meta tags (og:image, og:description) if not already set
        //   TODO: Extract structured data (JSON-LD) if present for richer event details
        //   TODO: Update event metadata JSONB column with any new fields
        //   TODO: Update image_url if found and not already set
        // TODO: Set $event->is_enriched = true
        // TODO: Save the event
        // TODO: On failure: log warning, mark as enriched to prevent retry loops
        // TODO: Return the updated event
        return $event;
    }
}
