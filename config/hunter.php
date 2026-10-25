<?php

return [
    'radius_m' => 2000,
    'demo_mode' => env('HUNTER_DEMO_MODE', false),
    'demo_user_password' => env('DEMO_USER_PASSWORD'),
    'basemap' => env('HUNTER_BASEMAP', 'openfreemap'),
    'schedule_timezone' => 'Asia/Jakarta',

    'overpass' => [
        'endpoint' => env('HUNTER_OVERPASS_ENDPOINT', 'https://overpass-api.de/api/interpreter'),
        'mirror' => env('HUNTER_OVERPASS_MIRROR', 'https://overpass.private.coffee/api/interpreter'),
        'contact_email' => env('HUNTER_CONTACT_EMAIL', 'admin@example.com'),
        'delay_seconds' => 5,
        'timeout_seconds' => 90,
        'cache_ttl_days' => 7,
        'backoff_seconds' => [30, 60, 120],
        'circuit_breaker_failures' => 5,
    ],

    'geocoding' => [
        'enabled' => env('GEOCODING_ENABLED', true),
        'driver' => env('HUNTER_GEOCODER', 'nominatim_public'),
        'nominatim_url' => env('HUNTER_NOMINATIM_URL', 'https://nominatim.openstreetmap.org'),
        'requests_per_minute' => 4,
        'max_per_run' => 100,
    ],

    'ai' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-5-5'),
    ],

    'settings_defaults' => [
        'contact.warn_days' => 7,
        'contact.min_interval_days' => 14,
        'contact.max_overrides' => 2,
        'digest.email_to' => null,
        'digest.hot_threshold' => 75,
        'digest.follow_up_days' => 7,
        'sync.max_outlets_per_run' => 200,
        'sync.multi_provider' => false,
        'scoring.params' => [],
        'enrichment.simulated' => false,
        'demo.allow_simulated_whatsapp' => false,
    ],
];
