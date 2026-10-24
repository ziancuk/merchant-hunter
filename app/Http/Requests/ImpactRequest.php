<?php

declare(strict_types=1);

namespace App\Http\Requests;

final class ImpactRequest extends ApiRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $count = ['sometimes', 'integer', 'between:0,1000000'];
        $money = ['sometimes', 'numeric', 'between:0,1000000000000'];
        $ratio = ['sometimes', 'numeric', 'between:0,1'];

        return [
            'outlets' => $count,
            'google_syncs_per_month' => ['sometimes', 'integer', 'between:0,100'],
            'google_groups_per_outlet' => ['sometimes', 'integer', 'between:0,100'],
            'avg_pages' => ['sometimes', 'numeric', 'between:0,10'],
            'details_ratio' => ['sometimes', 'numeric', 'between:0,10'],
            'cost_per_1000_search' => ['sometimes', 'numeric', 'between:0,10000'],
            'cost_per_1000_details' => ['sometimes', 'numeric', 'between:0,10000'],
            'usd_idr' => ['sometimes', 'numeric', 'between:1,1000000'],
            'prospects_per_outlet' => $count,
            'conv_found_to_contact' => $ratio,
            'conv_contact_to_demo' => $ratio,
            'conv_demo_to_won' => $ratio,
            'deal_value_idr' => $money,
            'hours_saved_per_week' => ['sometimes', 'numeric', 'between:0,168'],
            'hourly_cost_idr' => $money,
        ];
    }
}
