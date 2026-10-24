<?php

declare(strict_types=1);

namespace App\Services\Export;

use Symfony\Component\HttpFoundation\StreamedResponse;

/** Streams CSV with spreadsheet-formula neutralisation (cells starting with = + - @ get a leading '). */
final class CsvExporter
{
    public static function sanitize(mixed $value): string|int|float
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        $s = (string) $value;
        if ($s !== '' && in_array($s[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$s;
        }

        return $s;
    }

    /**
     * @param  list<string>  $headers
     * @param  iterable<list<mixed>>  $rows
     */
    public function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads Indonesian text correctly
            fputcsv($out, $headers, ',', '"', '');
            foreach ($rows as $row) {
                fputcsv($out, array_map(self::sanitize(...), $row), ',', '"', '');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
