<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Repositories\DashboardQuery;

/** Dashboard CSV rows: KPI, funnel and per-province penetration sections. */
final class DashboardCsvExport
{
    /** @return list<string> */
    public static function headers(): array
    {
        return array_values((array) __('export.dashboard.columns'));
    }

    public function __construct(private readonly DashboardQuery $dashboard) {}

    public function filename(): string
    {
        return __('export.dashboard.filename').'-'.now()->format('Ymd').'.csv';
    }

    /** @return list<list<mixed>> */
    public function rows(): array
    {
        $rows = [];
        $summary = $this->dashboard->kpisAndFunnel();
        foreach ($summary['kpis'] as $key => $value) {
            $rows[] = [__('export.dashboard.section_kpi'), $key, $value, '', '', ''];
        }
        foreach ($summary['funnel'] as $stage => $value) {
            $rows[] = [__('export.dashboard.section_funnel'), $stage, $value, '', '', ''];
        }
        foreach ($this->dashboard->penetration() as $p) {
            $rows[] = [__('export.dashboard.section_penetration'), $p['province'], $p['outlets'], $p['prospects'], $p['won'], $p['penetration']];
        }

        return $rows;
    }
}
