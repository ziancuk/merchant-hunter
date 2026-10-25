<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Repositories\DashboardQuery;

/** Dashboard CSV rows: KPI, funnel and per-province penetration sections. */
final class DashboardCsvExport
{
    public const HEADERS = ['bagian', 'nama', 'nilai', 'prospek', 'menang', 'penetrasi'];

    public function __construct(private readonly DashboardQuery $dashboard) {}

    public function filename(): string
    {
        return 'dashboard-'.now()->format('Ymd').'.csv';
    }

    /** @return list<list<mixed>> */
    public function rows(): array
    {
        $rows = [];
        $summary = $this->dashboard->kpisAndFunnel();
        foreach ($summary['kpis'] as $key => $value) {
            $rows[] = ['KPI', $key, $value, '', '', ''];
        }
        foreach ($summary['funnel'] as $stage => $value) {
            $rows[] = ['Funnel', $stage, $value, '', '', ''];
        }
        foreach ($this->dashboard->penetration() as $p) {
            $rows[] = ['Penetrasi', $p['province'], $p['outlets'], $p['prospects'], $p['won'], $p['penetration']];
        }

        return $rows;
    }
}
