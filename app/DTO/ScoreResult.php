<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class ScoreResult
{
    /** @param list<array{key: string, label: string, value: float, max: int, points: float, reason: string}> $breakdown */
    public function __construct(
        public int $score,
        public array $breakdown,
    ) {}
}
