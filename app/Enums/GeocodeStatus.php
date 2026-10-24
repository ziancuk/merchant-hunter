<?php

declare(strict_types=1);

namespace App\Enums;

enum GeocodeStatus: string
{
    case Ok = 'ok';
    case NeedsReview = 'needs_review';
    case Failed = 'failed';
    case Manual = 'manual';
}
