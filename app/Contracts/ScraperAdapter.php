<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\RawEvent;
use Illuminate\Support\Collection;

interface ScraperAdapter
{
    /** Unique key that identifies this adapter class (matches adapter_registry key). */
    public function adapterKey(): string;

    /**
     * Scrape events for a specific city/source combination.
     *
     * @param  array{adapter: string, url: string, extra_urls?: list<string>, enabled: bool, interval_hours: int}  $sourceConfig
     * @param  array{label: string, timezone: string, coordinates: list<float>, radius_km: int}  $cityConfig
     * @return Collection<int, RawEvent>
     */
    public function scrape(array $sourceConfig, array $cityConfig): Collection;

    /**
     * Human-readable identifier for logging and audit records.
     * Convention: "adapterKey@hostname" e.g. "zilesinopti@zilesinopti.ro".
     *
     * @param  array{adapter: string, url: string}  $sourceConfig
     */
    public function sourceIdentifier(array $sourceConfig): string;
}
