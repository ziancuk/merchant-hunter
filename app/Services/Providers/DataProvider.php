<?php

declare(strict_types=1);

namespace App\Services\Providers;

use App\DTO\ConnectionStatus;
use App\DTO\OutletPoint;
use App\DTO\ProviderResult;
use App\DTO\UsageInfo;
use App\Models\Category;

interface DataProvider
{
    /** osm | mock | google */
    public function key(): string;

    /** Active and fully configured. */
    public function isAvailable(): bool;

    /**
     * @param  list<Category>  $categories
     */
    public function searchNearby(OutletPoint $center, int $radiusMeters, array $categories): ProviderResult;

    public function testConnection(): ConnectionStatus;

    public function usage(): UsageInfo;
}
