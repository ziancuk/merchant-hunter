<?php

declare(strict_types=1);

namespace App\DTO;

/** Pure input of the scoring formula (TRD §7). */
final readonly class ScoreInput
{
    public function __construct(
        public int $categoryWeight,
        public ?float $rating,
        public ?int $reviewCount,
        public bool $ratingCounts,
        public ?int $nearestOutletDistanceM,
        public int $neighborOutlets500m,
        public int $areaDensity,
        public bool $hasPhone,
        public bool $hasWebsite,
        public bool $hasAddress,
        public bool $isNewOpen,
        public int $ageDays,
        public bool $isClosed = false,
    ) {}
}
