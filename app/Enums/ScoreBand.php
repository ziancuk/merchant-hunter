<?php

declare(strict_types=1);

namespace App\Enums;

enum ScoreBand: string
{
    case Hot = 'hot';
    case Warm = 'warm';
    case Medium = 'medium';
    case Cold = 'cold';

    public static function fromScore(int $score): self
    {
        return match (true) {
            $score >= 75 => self::Hot,
            $score >= 55 => self::Warm,
            $score >= 35 => self::Medium,
            default => self::Cold,
        };
    }
}
