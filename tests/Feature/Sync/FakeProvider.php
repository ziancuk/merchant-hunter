<?php

declare(strict_types=1);

namespace Tests\Feature\Sync;

use App\DTO\BusinessDTO;
use App\DTO\ConnectionStatus;
use App\DTO\OutletPoint;
use App\DTO\ProviderResult;
use App\DTO\UsageInfo;
use App\Services\Providers\DataProvider;
use Closure;
use Throwable;

/** Scriptable provider: set $answer to businesses, a ProviderResult, a Throwable or a closure. */
class FakeProvider implements DataProvider
{
    /** @var list<OutletPoint> */
    public array $calls = [];

    public mixed $answer = [];

    public bool $available = true;

    public function __construct(public string $providerKey = 'osm') {}

    public function key(): string
    {
        return $this->providerKey;
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function searchNearby(OutletPoint $center, int $radiusMeters, array $categories): ProviderResult
    {
        $this->calls[] = $center;
        $answer = $this->answer instanceof Closure ? ($this->answer)($center, $categories) : $this->answer;
        if ($answer instanceof Throwable) {
            throw $answer;
        }
        if ($answer instanceof ProviderResult) {
            return $answer;
        }

        /** @var list<BusinessDTO> $answer */
        return new ProviderResult($answer, false, true, 1);
    }

    public function testConnection(): ConnectionStatus
    {
        return new ConnectionStatus(true, 'ok');
    }

    public function usage(): UsageInfo
    {
        return new UsageInfo(0, null);
    }
}
