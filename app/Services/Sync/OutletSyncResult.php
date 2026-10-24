<?php

declare(strict_types=1);

namespace App\Services\Sync;

final class OutletSyncResult
{
    public function __construct(
        public string $status = 'ok',
        public string $provider = '',
        public int $fetched = 0,
        public int $new = 0,
        public int $updated = 0,
        public int $closed = 0,
        public int $excluded = 0,
        public int $apiCalls = 0,
        public ?string $error = null,
        public int $durationMs = 0,
    ) {}

    public function isFailure(): bool
    {
        return $this->status === 'failed';
    }

    public function add(self $other): void
    {
        $this->fetched += $other->fetched;
        $this->new += $other->new;
        $this->updated += $other->updated;
        $this->closed += $other->closed;
        $this->excluded += $other->excluded;
        $this->apiCalls += $other->apiCalls;
    }
}
