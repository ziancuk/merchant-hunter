<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Geocoding\GeocoderInterface;
use App\Services\Geocoding\NominatimGeocoder;
use App\Services\Master\ConnectionTester;
use App\Services\Master\ProviderConnectionTester;
use App\Services\Scoring\ProspectScorer;
use App\Services\Scoring\ScoreCalculator;
use App\Services\Settings\SettingsService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(SettingsService::class);
        $this->app->bind(ProspectScorer::class, ScoreCalculator::class);
        $this->app->bind(GeocoderInterface::class, NominatimGeocoder::class);
        $this->app->bind(ConnectionTester::class, ProviderConnectionTester::class);
    }

    public function boot(): void
    {
        //
    }
}
