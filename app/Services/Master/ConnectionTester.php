<?php

declare(strict_types=1);

namespace App\Services\Master;

use App\DTO\ConnectionStatus;
use App\Models\DataSource;
use Illuminate\Container\Attributes\Bind;

/**
 * Seam for the "Uji koneksi" action (D-60). The orchestrator binds the real implementation
 * (ProviderRegistry -> DataProvider::testConnection with a 10 s timeout); tests fake it.
 */
#[Bind(UnconfiguredConnectionTester::class)]
interface ConnectionTester
{
    public function test(DataSource $source): ConnectionStatus;
}
