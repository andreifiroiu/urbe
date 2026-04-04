<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\RawEvent;
use Illuminate\Support\Collection;

interface ScraperAdapter
{
    public function source(): string;

    /** @return Collection<int, RawEvent> */
    public function scrape(): Collection;

    public function supports(string $source): bool;
}
