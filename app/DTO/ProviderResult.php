<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class ProviderResult
{
    /**
     * @param  list<BusinessDTO>  $businesses
     * @param  bool  $cached  true when served from api_cache (possibly stale)
     * @param  bool  $fresh  true when the provider answered live and successfully
     */
    public function __construct(
        public array $businesses,
        public bool $cached = false,
        public bool $fresh = true,
        public int $apiCalls = 0,
    ) {}
}
