<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProspectExportRequest;
use App\Services\Export\CsvExporter;
use App\Services\Export\DashboardCsvExport;
use App\Services\Export\ProspectCsvExport;
use App\Services\Export\XlsxExporter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function prospects(ProspectExportRequest $request, CsvExporter $csv, ProspectCsvExport $export): StreamedResponse
    {
        return $csv->download($export->filename(), ProspectCsvExport::headers(), $export->rows($request->filter()));
    }

    public function prospectsXlsx(ProspectExportRequest $request, XlsxExporter $xlsx, ProspectCsvExport $export): StreamedResponse
    {
        return $xlsx->download(str_replace('.csv', '.xlsx', $export->filename()), __('export.prospects.sheet'), ProspectCsvExport::headers(), $export->rows($request->filter()));
    }

    public function dashboard(CsvExporter $csv, DashboardCsvExport $export): StreamedResponse
    {
        return $csv->download($export->filename(), DashboardCsvExport::headers(), $export->rows());
    }
}
