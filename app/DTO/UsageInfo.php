<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class UsageInfo
{
    public function __construct(
        public int $callsThisMonth,
        public ?int $monthlyQuota,
        public ?float $estimatedCostUsd = null,
    ) {}
}
