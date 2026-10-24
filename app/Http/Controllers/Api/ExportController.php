<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProspectExportRequest;
use App\Models\Prospect;
use App\Repositories\DashboardQuery;
use App\Services\Export\CsvExporter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function prospects(ProspectExportRequest $request, CsvExporter $csv): StreamedResponse
    {
        $query = $request->filter()->apply(Prospect::query())
            ->with(['category:id,name', 'province:id,name', 'handler:id,name'])
            ->select('prospects.*')
            ->orderBy('prospects.id');

        $rows = (function () use ($query) {
            foreach ($query->lazy(500) as $p) {
                yield [
                    $p->id, $p->name, $p->category?->name, $p->city, $p->province?->name, $p->address, $p->phone,
                    $p->phone_simulated ? 1 : 0, $p->rating, $p->review_count, $p->score, $p->pipeline_status->value,
                    $p->handler?->name, $p->last_contacted_at?->copy()->utc()->toIso8601ZuluString(), $p->nearest_outlet_distance_m,
                ];
            }
        })();

        return $csv->download('prospek-'.now()->format('Ymd').'.csv', [
            'id', 'nama', 'kategori', 'kota', 'provinsi', 'alamat', 'telepon', 'simulasi_telepon', 'rating',
            'jumlah_review', 'skor', 'status', 'ditangani_oleh', 'terakhir_dihubungi', 'jarak_outlet_m',
        ], $rows);
    }

    public function dashboard(DashboardQuery $dashboard, CsvExporter $csv): StreamedResponse
    {
        $rows = [];
        foreach ($dashboard->kpis() as $key => $value) {
            $rows[] = ['KPI', $key, $value, '', '', ''];
        }
        foreach ($dashboard->funnel() as $stage => $value) {
            $rows[] = ['Funnel', $stage, $value, '', '', ''];
        }
        foreach ($dashboard->penetration() as $p) {
            $rows[] = ['Penetrasi', $p['province'], $p['outlets'], $p['prospects'], $p['won'], $p['penetration']];
        }

        return $csv->download('dashboard-'.now()->format('Ymd').'.csv', ['bagian', 'nama', 'nilai', 'prospek', 'menang', 'penetrasi'], $rows);
    }
}
