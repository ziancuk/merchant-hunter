<?php

use App\Models\Setting;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\DB;

function sm_settings_queries(callable $fn): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $fn();
    $count = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'settings'))->count();
    DB::disableQueryLog();

    return $count;
}

it('reads the settings table once per instance regardless of how many keys are asked', function () {
    $s = new SettingsService;

    $queries = sm_settings_queries(function () use ($s) {
        $s->contactRules();
        $s->contactRules();
        $s->structured();
        $s->int('digest.hot_threshold');
        $s->bool('demo.allow_simulated_whatsapp');
        $s->impactAssumptions();
    });

    expect($queries)->toBe(1);
});

it('resolves one shared instance per request scope', function () {
    expect(app(SettingsService::class))->toBe(app(SettingsService::class));
});

it('reflects writes made through the service and through the Setting model', function () {
    $s = app(SettingsService::class);
    expect($s->int('contact.min_interval_days'))->toBe(14);

    $s->set('contact.min_interval_days', 30);
    expect($s->int('contact.min_interval_days'))->toBe(30);

    Setting::write('contact.min_interval_days', 45);
    expect($s->contactRules()['min_interval_days'])->toBe(45);

    $s->updateStructured(['contact' => ['warn_days' => 3]]);
    expect($s->contactRules())->toBe(['warn_days' => 3, 'min_interval_days' => 45, 'max_overrides' => 2]);
});
