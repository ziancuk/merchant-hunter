<?php

use App\DTO\ScoreInput;
use App\Enums\ScoreBand;
use App\Services\Scoring\ProspectScorer;
use App\Services\Scoring\ScoreCalculator;

function scoreInput(array $o = []): ScoreInput
{
    $d = array_merge([
        'categoryWeight' => 0, 'rating' => null, 'reviewCount' => null, 'ratingCounts' => true,
        'nearestOutletDistanceM' => 2000, 'neighborOutlets500m' => 0, 'areaDensity' => 0,
        'hasPhone' => false, 'hasWebsite' => false, 'hasAddress' => false,
        'isNewOpen' => false, 'ageDays' => 0, 'isClosed' => false,
    ], $o);

    return new ScoreInput(...$d);
}

/** @return array<string, array{points: float, value: float, reason: string}> */
function byKey(ScoreInput $in, ?ScoreCalculator $calc = null): array
{
    $result = ($calc ?? new ScoreCalculator(pure: true))->score($in);
    $out = [];
    foreach ($result->breakdown as $c) {
        $out[$c['key']] = $c;
    }

    return $out;
}

it('implements the ProspectScorer contract', function () {
    expect(new ScoreCalculator(pure: true))->toBeInstanceOf(ProspectScorer::class);
});

it('scores 0 for the worst possible inputs (rating exactly 3.0 counts as zero quality)', function () {
    $r = (new ScoreCalculator(pure: true))->score(scoreInput(['rating' => 3.0, 'reviewCount' => 0]));

    expect($r->score)->toBe(0)
        ->and(collect($r->breakdown)->sum('points'))->toBe(0.0);
});

it('scores 100 when every component is at its maximum', function () {
    $r = (new ScoreCalculator(pure: true))->score(scoreInput([
        'categoryWeight' => 100, 'rating' => 5.0, 'reviewCount' => 500, 'nearestOutletDistanceM' => 0,
        'neighborOutlets500m' => 3, 'areaDensity' => 25, 'hasPhone' => true, 'hasWebsite' => true,
        'hasAddress' => true, 'isNewOpen' => true, 'ageDays' => 0,
    ]));

    expect($r->score)->toBe(100)
        ->and(collect($r->breakdown)->sum('points'))->toBe(100.0);
});

it('returns six breakdown entries with key, label, value, max, points and an Indonesian reason', function () {
    $r = (new ScoreCalculator(pure: true))->score(scoreInput(['categoryWeight' => 80, 'neighborOutlets500m' => 3, 'nearestOutletDistanceM' => 640]));

    expect($r->breakdown)->toHaveCount(6)
        ->and(array_column($r->breakdown, 'key'))->toBe(['category', 'quality', 'proximity', 'density', 'contact', 'new'])
        ->and(array_column($r->breakdown, 'max'))->toBe([25, 20, 15, 15, 10, 15]);
    foreach ($r->breakdown as $c) {
        expect($c)->toHaveKeys(['key', 'label', 'value', 'max', 'points', 'reason'])
            ->and($c['reason'])->not->toBe('');
    }
    expect(byKey(scoreInput(['neighborOutlets500m' => 3, 'nearestOutletDistanceM' => 640]))['proximity']['reason'])
        ->toContain('3 outlet Hitachi dalam 500 m');
});

it('weights the category component as weight/100 * 25', function () {
    expect(byKey(scoreInput(['categoryWeight' => 80]))['category']['points'])->toBe(20.0)
        ->and(byKey(scoreInput(['categoryWeight' => 0]))['category']['points'])->toBe(0.0)
        ->and(byKey(scoreInput(['categoryWeight' => 150]))['category']['points'])->toBe(25.0); // clamped
});

it('computes quality at the rating boundaries 3.0 and 5.0', function () {
    expect(byKey(scoreInput(['rating' => 3.0, 'reviewCount' => 0]))['quality']['value'])->toBe(0.0)
        ->and(byKey(scoreInput(['rating' => 2.0, 'reviewCount' => 0]))['quality']['value'])->toBe(0.0) // clamp low
        ->and(byKey(scoreInput(['rating' => 5.0, 'reviewCount' => 0]))['quality']['value'])->toBe(0.6)
        ->and(byKey(scoreInput(['rating' => 4.0, 'reviewCount' => 0]))['quality']['value'])->toBe(0.3);
});

it('computes quality at the review boundaries 0 and 500+', function () {
    expect(byKey(scoreInput(['rating' => 3.0, 'reviewCount' => 500]))['quality']['value'])->toBe(0.4)
        ->and(byKey(scoreInput(['rating' => 3.0, 'reviewCount' => 5000]))['quality']['value'])->toBe(0.4) // capped
        ->and(byKey(scoreInput(['rating' => 3.0, 'reviewCount' => null]))['quality']['value'])->toBe(0.0);
});

it('uses the neutral 0.25 quality without a rating or when the rating does not count', function () {
    expect(byKey(scoreInput(['rating' => null]))['quality']['value'])->toBe(0.25)
        ->and(byKey(scoreInput(['rating' => null]))['quality']['points'])->toBe(5.0)
        ->and(byKey(scoreInput(['rating' => 5.0, 'reviewCount' => 900, 'ratingCounts' => false]))['quality']['value'])->toBe(0.25);
});

it('computes proximity at distance 0, 2000 and beyond', function () {
    // d only (no neighbours): 0.5 * (1 - d/2000)
    expect(byKey(scoreInput(['nearestOutletDistanceM' => 0]))['proximity']['value'])->toBe(0.5)
        ->and(byKey(scoreInput(['nearestOutletDistanceM' => 1000]))['proximity']['value'])->toBe(0.25)
        ->and(byKey(scoreInput(['nearestOutletDistanceM' => 2000]))['proximity']['value'])->toBe(0.0)
        ->and(byKey(scoreInput(['nearestOutletDistanceM' => 5000]))['proximity']['value'])->toBe(0.0)
        ->and(byKey(scoreInput(['nearestOutletDistanceM' => null]))['proximity']['value'])->toBe(0.0);
});

it('maps neighbour outlet counts to 0 / 0.6 / 0.85 / 1', function () {
    $t = fn (int $n) => byKey(scoreInput(['nearestOutletDistanceM' => 2000, 'neighborOutlets500m' => $n]))['proximity']['value'];

    expect($t(0))->toBe(0.0)->and($t(1))->toBe(0.3)->and($t(2))->toBe(0.425)->and($t(3))->toBe(0.5)->and($t(9))->toBe(0.5);
});

it('computes density as min(1, n/25)', function () {
    expect(byKey(scoreInput(['areaDensity' => 0]))['density']['points'])->toBe(0.0)
        ->and(byKey(scoreInput(['areaDensity' => 5]))['density']['value'])->toBe(0.2)
        ->and(byKey(scoreInput(['areaDensity' => 25]))['density']['points'])->toBe(15.0)
        ->and(byKey(scoreInput(['areaDensity' => 400]))['density']['points'])->toBe(15.0);
});

it('computes contact completeness as 0.5 phone + 0.25 website + 0.25 address', function () {
    expect(byKey(scoreInput())['contact']['points'])->toBe(0.0)
        ->and(byKey(scoreInput(['hasPhone' => true]))['contact']['points'])->toBe(5.0)
        ->and(byKey(scoreInput(['hasWebsite' => true]))['contact']['points'])->toBe(2.5)
        ->and(byKey(scoreInput(['hasAddress' => true]))['contact']['points'])->toBe(2.5)
        ->and(byKey(scoreInput(['hasPhone' => true, 'hasWebsite' => true, 'hasAddress' => true]))['contact']['points'])->toBe(10.0);
});

it('decays the new-open component linearly over 30 days', function () {
    $new = fn (int $age, bool $flag = true) => byKey(scoreInput(['isNewOpen' => $flag, 'ageDays' => $age]))['new'];

    expect($new(0)['points'])->toBe(15.0)
        ->and($new(15)['points'])->toBe(7.5)
        ->and($new(30)['points'])->toBe(0.0)
        ->and($new(90)['points'])->toBe(0.0)
        ->and($new(0, false)['points'])->toBe(0.0);
});

it('forces the score to 0 for closed businesses', function () {
    $r = (new ScoreCalculator(pure: true))->score(scoreInput([
        'categoryWeight' => 100, 'rating' => 5.0, 'reviewCount' => 500, 'nearestOutletDistanceM' => 0,
        'neighborOutlets500m' => 3, 'areaDensity' => 25, 'hasPhone' => true, 'isClosed' => true,
    ]));

    expect($r->score)->toBe(0)->and(collect($r->breakdown)->sum('points'))->toBe(0.0);
});

it('rounds the total to the nearest integer', function () {
    // category 85 -> 21.25 ; quality unrated 0.25 -> 5 ; total 26.25 -> 26
    expect((new ScoreCalculator(pure: true))->score(scoreInput(['categoryWeight' => 85]))->score)->toBe(26)
        // 21.25 + 5 + 0.5*... craft .5: category 90 -> 22.5 + 5 = 27.5 -> 28 (half away from zero)
        ->and((new ScoreCalculator(pure: true))->score(scoreInput(['categoryWeight' => 90]))->score)->toBe(28);
});

it('never leaves the 0..100 range', function () {
    $r = (new ScoreCalculator(pure: true))->score(scoreInput([
        'categoryWeight' => 500, 'rating' => 9.0, 'reviewCount' => 999999, 'nearestOutletDistanceM' => -50,
        'neighborOutlets500m' => 99, 'areaDensity' => 9999, 'hasPhone' => true, 'hasWebsite' => true,
        'hasAddress' => true, 'isNewOpen' => true, 'ageDays' => -10,
    ]));

    expect($r->score)->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
});

it('lets scoring.params override the defaults', function () {
    $calc = new ScoreCalculator(null, ['weights' => ['category' => 50], 'density_cap' => 10, 'new_days' => 10], true);

    expect(byKey(scoreInput(['categoryWeight' => 100]), $calc)['category']['points'])->toBe(50.0)
        ->and(byKey(scoreInput(['areaDensity' => 5]), $calc)['density']['value'])->toBe(0.5)
        ->and(byKey(scoreInput(['isNewOpen' => true, 'ageDays' => 5]), $calc)['new']['value'])->toBe(0.5);
});

it('maps scores to the contract bands', function (int $score, ScoreBand $band) {
    expect(ScoreBand::fromScore($score))->toBe($band);
})->with([
    [100, ScoreBand::Hot], [75, ScoreBand::Hot], [74, ScoreBand::Warm], [55, ScoreBand::Warm],
    [54, ScoreBand::Medium], [35, ScoreBand::Medium], [34, ScoreBand::Cold], [0, ScoreBand::Cold],
]);
