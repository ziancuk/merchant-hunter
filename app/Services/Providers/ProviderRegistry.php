<?php

declare(strict_types=1);

namespace App\Services\Providers;

use App\Models\DataSource;
use App\Models\Outlet;

/**
 * Loads active data_sources by priority and hands the providers to try for an outlet (TRD §4).
 * Mock is inactive in production syncs (D-63): it only appears when its data_source is active, when forced
 * with --provider=mock, or as a last-resort fallback while HUNTER_DEMO_MODE=true.
 */
class ProviderRegistry
{
    /** @var array<string, class-string<DataProvider>> */
    private array $classes = [
        'osm' => OsmProvider::class,
        'google' => GoogleProvider::class,
        'mock' => MockProvider::class,
    ];

    /** @var array<string, DataProvider> */
    private array $overrides = [];

    /** Replace a provider implementation (tests, custom adapters). */
    public function extend(string $key, DataProvider $provider): static
    {
        $this->overrides[$key] = $provider;

        return $this;
    }

    public function make(string $key): ?DataProvider
    {
        if (isset($this->overrides[$key])) {
            return $this->overrides[$key];
        }

        return isset($this->classes[$key]) ? app()->make($this->classes[$key]) : null;
    }

    /**
     * Providers in priority order (small number first) that are active and usable.
     *
     * @return list<DataProvider>
     */
    public function active(): array
    {
        $providers = [];
        foreach (DataSource::query()->active()->get() as $source) {
            $provider = $this->make($source->key);
            if ($provider !== null && $provider->isAvailable()) {
                $providers[$source->key] = $provider;
            }
        }
        if (config('hunter.demo_mode') && ! isset($providers['mock']) && ($mock = $this->make('mock')) !== null) {
            $providers['mock'] = $mock;
        }

        return array_values($providers);
    }

    /**
     * Providers to try for an outlet, in order. With $only the operator forces one provider.
     *
     * @return list<DataProvider>
     */
    public function resolveFor(Outlet $outlet, ?string $only = null): array
    {
        if ($only !== null) {
            $provider = $this->make($only);
            if ($provider === null) {
                return [];
            }
            // Google must still be enabled explicitly (ToS, D-47); osm and mock may be forced by the operator.
            if ($only === 'google' && ! $provider->isAvailable()) {
                return [];
            }

            return [$provider];
        }

        return $this->active();
    }
}
