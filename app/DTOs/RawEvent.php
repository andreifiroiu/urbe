<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class RawEvent
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $title,
        public ?string $description,
        public string $sourceUrl,
        public ?string $sourceId,
        public string $source,
        public ?string $venue,
        public ?string $address,
        public ?string $city,
        public ?string $startsAt,
        public ?string $endsAt,
        public ?float $priceMin,
        public ?float $priceMax,
        public ?string $currency,
        public ?bool $isFree,
        public ?string $imageUrl,
        public array $metadata = [],
    ) {}
}
