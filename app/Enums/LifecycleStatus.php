<?php

declare(strict_types=1);

namespace App\Enums;

enum LifecycleStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
}
