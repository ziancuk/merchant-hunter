<?php

declare(strict_types=1);

namespace App\Services\Providers;

use RuntimeException;

/** A provider could not answer (after retries). */
class ProviderException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $retryable = false, public readonly ?int $status = null)
    {
        parent::__construct($message);
    }
}
