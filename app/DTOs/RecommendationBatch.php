<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class RecommendationBatch
{
    /**
     * @param array<int, string> $recommendedEventIds
     * @param array<int, string> $discoveryEventIds
     */
    public function __construct(
        public string $userId,
        public array $recommendedEventIds,
        public array $discoveryEventIds,
        public float $totalScore,
        public \DateTimeImmutable $generatedAt,
    ) {}
}
