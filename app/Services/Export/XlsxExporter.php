<?php

declare(strict_types=1);

namespace App\Services\Export;

use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams an .xlsx workbook (one sheet). Text is always written as an explicit string cell: OpenSpout's own type
 * detection would turn a value like "=HYPERLINK(...)" into a live formula cell.
 */
final class XlsxExporter
{
    public const CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    /**
     * @param  list<string>  $headers
     * @param  iterable<list<mixed>>  $rows
     */
    public function download(string $filename, string $sheetName, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($sheetName, $headers, $rows): void {
            $options = new Options;
            $options->setColumnWidth(8, 1);
            $options->setColumnWidth(30, 2);
            $options->setColumnWidth(20, 3, 5);
            $options->setColumnWidth(36, 6);
            $options->setColumnWidth(18, 7);

            $writer = new Writer($options);
            $writer->openToFile('php://output');
            $writer->getCurrentSheet()->setName(mb_substr($sheetName, 0, 31));

            $header = new Style;
            $header->setFontBold();
            $writer->addRow(new Row(array_map(fn (string $h) => Cell::fromValue($h, $header), $headers)));

            foreach ($rows as $row) {
                $writer->addRow(new Row(array_map(self::cell(...), $row)));
            }
            $writer->close();
        }, $filename, ['Content-Type' => self::CONTENT_TYPE]);
    }

    private static function cell(mixed $value): Cell
    {
        return match (true) {
            $value === null => new StringCell('', null),
            is_string($value) => new StringCell($value, null),
            default => Cell::fromValue($value),
        };
    }
}
