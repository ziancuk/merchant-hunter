<?php

declare(strict_types=1);

namespace App\Enums;

enum ScoreBand: string
{
    case Hot = 'hot';
    case Warm = 'warm';
    case Medium = 'medium';
    case Cold = 'cold';

    /** Single source of truth for the "hot" threshold (CONTRACTS: hot >= 75). */
    public const HOT_MIN = 75;

    public static function fromScore(int $score): self
    {
        return match (true) {
            $score >= self::HOT_MIN => self::Hot,
            $score >= 55 => self::Warm,
            $score >= 35 => self::Medium,
            default => self::Cold,
        };
    }

    /** Display name in the request language (hot = Bidik Utama / Prime Target, ...). */
    public function label(): string
    {
        return __('scoring.bands.'.$this->value);
    }
}
