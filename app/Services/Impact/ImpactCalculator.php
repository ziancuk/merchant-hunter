<?php

declare(strict_types=1);

namespace App\Services\Impact;

/**
 * Pure cost/impact estimator. Every assumption is explicit and editable (Estimasi Dampak & Biaya).
 * Tariffs are owner-editable defaults, not authoritative prices.
 */
final class ImpactCalculator
{
    /** @return array<string, float|int> */
    public static function defaults(): array
    {
        return [
            'outlets' => 190,
            'google_syncs_per_month' => 30,
            'google_groups_per_outlet' => 12,
            'avg_pages' => 1,
            'details_ratio' => 0.0,
            'cost_per_1000_search' => 32.0,
            'cost_per_1000_details' => 17.0,
            'usd_idr' => 16000,
            'prospects_per_outlet' => 75,
            'conv_found_to_contact' => 0.2,
            'conv_contact_to_demo' => 0.15,
            'conv_demo_to_won' => 0.25,
            'deal_value_idr' => 5000000,
            'hours_saved_per_week' => 10,
            'hourly_cost_idr' => 50000,
        ];
    }

    /**
     * @param  array<string, float|int>  $assumptions  merged over the defaults
     * @return array<string, float|int>
     */
    public function calculate(array $assumptions): array
    {
        $a = array_replace(self::defaults(), $assumptions);

        $calls = (int) round($a['outlets'] * $a['google_syncs_per_month'] * $a['google_groups_per_outlet'] * $a['avg_pages']);
        $detailCalls = $calls * max(0.0, (float) $a['details_ratio']);
        $costUsd = ($calls / 1000) * $a['cost_per_1000_search'] + ($detailCalls / 1000) * $a['cost_per_1000_details'];

        $found = (int) round($a['outlets'] * $a['prospects_per_outlet']);
        $deals = $found * $a['conv_found_to_contact'] * $a['conv_contact_to_demo'] * $a['conv_demo_to_won'];
        $hours = $a['hours_saved_per_week'] * 52 / 12;

        return [
            'google_calls_per_month' => $calls,
            'google_cost_usd' => round($costUsd, 2),
            'google_cost_idr' => (int) round($costUsd * $a['usd_idr']),
            'osm_cost_idr' => 0,
            'prospects_found' => $found,
            'expected_deals' => round($deals, 2),
            'expected_revenue_idr' => (int) round($deals * $a['deal_value_idr']),
            'hours_saved_per_month' => round($hours, 1),
            'hours_saved_value_idr' => (int) round($hours * $a['hourly_cost_idr']),
        ];
    }
}
