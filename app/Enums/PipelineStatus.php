<?php

declare(strict_types=1);

namespace App\Enums;

enum PipelineStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Demo = 'demo';
    case Won = 'won';
    case Lost = 'lost';

    /** Index used by the columnar map payload (CONTRACTS §3). */
    public function index(): int
    {
        return array_search($this, self::cases(), true);
    }
}
