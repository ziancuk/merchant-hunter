<?php

declare(strict_types=1);

namespace App\Services\Providers;

/** Providers with a response cache implement this so `hunter:sync --fresh` can bypass it. */
interface SupportsCacheBypass
{
    public function ignoreCache(bool $ignore = true): static;
}
