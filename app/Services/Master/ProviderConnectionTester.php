<?php

declare(strict_types=1);

namespace App\Services\Master;

use App\DTO\ConnectionStatus;
use App\Models\DataSource;
use App\Services\Providers\ProviderRegistry;
use Throwable;

/** Real "Uji koneksi" implementation: delegates to the provider adapter (D-60 exception). */
final class ProviderConnectionTester implements ConnectionTester
{
    public function __construct(private readonly ProviderRegistry $registry) {}

    public function test(DataSource $source): ConnectionStatus
    {
        $provider = $this->registry->make($source->key);
        if ($provider === null) {
            return new ConnectionStatus(false, 'Penyedia tidak dikenal.');
        }

        try {
            return $provider->testConnection();
        } catch (Throwable $e) {
            return new ConnectionStatus(false, 'Uji koneksi gagal: '.$e->getMessage());
        }
    }
}
