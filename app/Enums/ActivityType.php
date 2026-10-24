<?php

declare(strict_types=1);

namespace App\Enums;

enum ActivityType: string
{
    case Created = 'created';
    case StatusChange = 'status_change';
    case Contacted = 'contacted';
    case Note = 'note';
    case OptOut = 'opt_out';
    case OptIn = 'opt_in';
    case Rejected = 'rejected';
    case System = 'system';
}
