<?php

use App\Services\Export\CsvExporter;
use App\Services\Impact\ImpactCalculator;

it('computes Google calls as outlets x syncs x groups x pages', function () {
    $r = (new ImpactCalculator)->calculate(['outlets' => 190, 'google_syncs_per_month' => 30, 'google_groups_per_outlet' => 12, 'avg_pages' => 1]);

    expect($r['google_calls_per_month'])->toBe(68400)
        ->and($r['osm_cost_idr'])->toBe(0);
});

it('converts calls to USD and IDR with the editable tariffs', function () {
    $r = (new ImpactCalculator)->calculate([
        'outlets' => 100, 'google_syncs_per_month' => 10, 'google_groups_per_outlet' => 10, 'avg_pages' => 1,
        'details_ratio' => 0, 'cost_per_1000_search' => 30, 'usd_idr' => 16000,
    ]);

    // 10,000 calls * 30 / 1000 = 300 USD
    expect($r['google_calls_per_month'])->toBe(10000)
        ->and($r['google_cost_usd'])->toBe(300.0)
        ->and($r['google_cost_idr'])->toBe(4800000);
});

it('adds place-details cost via the details ratio', function () {
    $r = (new ImpactCalculator)->calculate([
        'outlets' => 1, 'google_syncs_per_month' => 1000, 'google_groups_per_outlet' => 1, 'avg_pages' => 1,
        'details_ratio' => 0.5, 'cost_per_1000_search' => 0, 'cost_per_1000_details' => 20, 'usd_idr' => 10000,
    ]);

    // 1000 calls, 500 detail calls * 20 / 1000 = 10 USD
    expect($r['google_cost_usd'])->toBe(10.0)->and($r['google_cost_idr'])->toBe(100000);
});

it('chains prospects through the conversion funnel into deals and revenue', function () {
    $r = (new ImpactCalculator)->calculate([
        'outlets' => 100, 'prospects_per_outlet' => 100, 'conv_found_to_contact' => 0.1,
        'conv_contact_to_demo' => 0.5, 'conv_demo_to_won' => 0.2, 'deal_value_idr' => 1000000,
    ]);

    expect($r['prospects_found'])->toBe(10000)
        ->and($r['expected_deals'])->toBe(100.0)
        ->and($r['expected_revenue_idr'])->toBe(100000000);
});

it('converts weekly saved hours to monthly hours', function () {
    $r = (new ImpactCalculator)->calculate(['hours_saved_per_week' => 12, 'hourly_cost_idr' => 50000]);

    expect($r['hours_saved_per_month'])->toBe(52.0)
        ->and($r['hours_saved_value_idr'])->toBe(2600000);
});

it('scales to zero when assumptions are zero', function () {
    $r = (new ImpactCalculator)->calculate(['outlets' => 0]);

    expect($r['google_calls_per_month'])->toBe(0)->and($r['google_cost_idr'])->toBe(0)->and($r['expected_deals'])->toBe(0.0);
});

it('exposes every assumption key in the defaults', function () {
    expect(ImpactCalculator::defaults())->toHaveKeys([
        'outlets', 'google_syncs_per_month', 'google_groups_per_outlet', 'avg_pages', 'details_ratio',
        'cost_per_1000_search', 'cost_per_1000_details', 'usd_idr', 'prospects_per_outlet',
        'conv_contact_to_demo', 'conv_demo_to_won', 'deal_value_idr', 'hours_saved_per_week', 'hourly_cost_idr',
    ])->and(ImpactCalculator::defaults()['avg_pages'])->toBe(1);
});

it('prefixes spreadsheet formula cells with a quote', function (string $cell) {
    expect(CsvExporter::sanitize($cell))->toBe("'".$cell);
})->with(['=SUM(A1)', '+62812', '-1+1', '@cmd', "\t=1", '=HYPERLINK("http://x")']);

it('leaves normal values and numbers untouched', function () {
    expect(CsvExporter::sanitize('Kopi Senja'))->toBe('Kopi Senja')
        ->and(CsvExporter::sanitize(-5))->toBe(-5)
        ->and(CsvExporter::sanitize(4.5))->toBe(4.5)
        ->and(CsvExporter::sanitize(null))->toBe('')
        ->and(CsvExporter::sanitize(true))->toBe('1')
        ->and(CsvExporter::sanitize('a=b'))->toBe('a=b');
});
