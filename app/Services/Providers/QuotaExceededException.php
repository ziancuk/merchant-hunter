<?php

declare(strict_types=1);

namespace App\Services\Providers;

/** Monthly quota of a paid provider is used up: the call is refused, never sent. */
class QuotaExceededException extends ProviderException
{
    public function __construct(string $message = 'Monthly quota exceeded')
    {
        parent::__construct($message, false, null);
    }
}
