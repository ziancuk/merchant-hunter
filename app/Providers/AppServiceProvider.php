<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Geocoding\GeocoderInterface;
use App\Services\Geocoding\NullGeocoder;
use App\Services\Scoring\ProspectScorer;
use App\Services\Scoring\StubProspectScorer;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Placeholders; real implementations are bound in M2.1/M2.2.
        $this->app->bind(ProspectScorer::class, StubProspectScorer::class);
        $this->app->bind(GeocoderInterface::class, NullGeocoder::class);
    }

    public function boot(): void
    {
        //
    }
}
