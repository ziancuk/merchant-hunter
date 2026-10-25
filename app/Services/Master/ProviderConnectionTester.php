<?php

declare(strict_types=1);

namespace App\Services\Master;

use App\DTO\ConnectionStatus;
use App\Models\DataSource;
use App\Services\Providers\ProviderRegistry;
use App\Support\Redactor;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Real "Uji koneksi" implementation: delegates to the provider adapter (D-60 exception). */
final class ProviderConnectionTester implements ConnectionTester
{
    public function __construct(private readonly ProviderRegistry $registry) {}

    public function test(DataSource $source): ConnectionStatus
    {
        $provider = $this->registry->make($source->key);
        if ($provider === null) {
            return new ConnectionStatus(false, __('api.connection.unknown_provider'));
        }

        try {
            return $provider->testConnection();
        } catch (Throwable $e) {
            // Never echo exception text to the client: it may carry URLs or upstream error bodies.
            Log::warning('Provider connection test crashed', ['source' => $source->key, 'exception' => $e::class, 'reason' => Redactor::exception($e)]);

            return new ConnectionStatus(false, __('api.connection.test_failed'));
        }
    }
}
