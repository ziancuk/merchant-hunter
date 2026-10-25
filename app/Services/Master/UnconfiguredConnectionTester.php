<?php

declare(strict_types=1);

namespace App\Services\Master;

use App\DTO\ConnectionStatus;
use App\Models\DataSource;

/** Fallback used until the orchestrator binds the real ConnectionTester. */
final class UnconfiguredConnectionTester implements ConnectionTester
{
    public function test(DataSource $source): ConnectionStatus
    {
        return new ConnectionStatus(false, __('api.connection.unconfigured'));
    }
}
