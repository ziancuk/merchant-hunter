<?php

use App\DTO\ScoreInput;
use App\Models\Setting;
use App\Services\Master\ActionProspectReconciler;
use App\Services\Master\ConnectionTester;
use App\Services\Master\ProspectReconciler;
use App\Services\Master\UnconfiguredConnectionTester;
use App\Services\Scoring\ScoreCalculator;
use App\Services\Settings\SettingsService;

function dm_input(array $o = []): ScoreInput
{
    return new ScoreInput(...array_merge([
        'categoryWeight' => 100, 'rating' => null, 'reviewCount' => null, 'ratingCounts' => true,
        'nearestOutletDistanceM' => 2000, 'neighborOutlets500m' => 0, 'areaDensity' => 0,
        'hasPhone' => false, 'hasWebsite' => false, 'hasAddress' => false, 'isNewOpen' => false, 'ageDays' => 0,
    ], $o));
}

it('reads scoring.params from settings when resolved from the container', function () {
    $default = app(ScoreCalculator::class)->score(dm_input())->score;

    Setting::write('scoring.params', ['weights' => ['category' => 50]]);
    $custom = app(ScoreCalculator::class)->score(dm_input())->score;

    expect($default)->toBe(30)   // 25 + neutral quality 5
        ->and($custom)->toBe(55); // 50 + 5
});

it('uses the code defaults when no scoring params are stored', function () {
    expect(app(SettingsService::class)->get('scoring.params'))->toBe([])
        ->and(app(ScoreCalculator::class)->params()['weights']['category'])->toBe(25);
});

it('falls back to config defaults for every setting key', function () {
    $s = app(SettingsService::class);

    expect($s->int('contact.warn_days'))->toBe(7)
        ->and($s->int('contact.min_interval_days'))->toBe(14)
        ->and($s->int('digest.hot_threshold'))->toBe(75)
        ->and($s->bool('demo.allow_simulated_whatsapp'))->toBeFalse()
        ->and($s->bool('enrichment.simulated'))->toBeFalse();
});

it('resolves the seam interfaces to safe defaults until the orchestrator binds the real classes', function () {
    expect(app(ConnectionTester::class))->toBeInstanceOf(UnconfiguredConnectionTester::class)
        ->and(app(ProspectReconciler::class))->toBeInstanceOf(ActionProspectReconciler::class);
});

it('reconciles nothing for an empty outlet list and survives a missing or failing action', function () {
    expect(app(ProspectReconciler::class)->reconcile([]))->toBe(0);

    if (! class_exists('App\\Actions\\ReconcileProspectsAction')) {
        expect(app(ProspectReconciler::class)->reconcile([1, 2]))->toBe(0);
    }
});
