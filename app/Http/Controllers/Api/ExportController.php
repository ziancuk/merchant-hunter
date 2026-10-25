<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProspectExportRequest;
use App\Services\Export\CsvExporter;
use App\Services\Export\DashboardCsvExport;
use App\Services\Export\ProspectCsvExport;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function prospects(ProspectExportRequest $request, CsvExporter $csv, ProspectCsvExport $export): StreamedResponse
    {
        return $csv->download($export->filename(), ProspectCsvExport::HEADERS, $export->rows($request->filter()));
    }

    public function dashboard(CsvExporter $csv, DashboardCsvExport $export): StreamedResponse
    {
        return $csv->download($export->filename(), DashboardCsvExport::HEADERS, $export->rows());
    }
}
