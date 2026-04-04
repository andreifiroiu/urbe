<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class ClassifiedEvent
{
    /**
     * @param array<int, string> $tags
     */
    public function __construct(
        public string $category,
        public array $tags,
        public float $confidence,
    ) {}
}
