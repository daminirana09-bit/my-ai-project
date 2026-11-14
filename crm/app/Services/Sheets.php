<?php

namespace App\Services;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ODS\Reader as OdsReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\ODS\Writer as OdsWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/**
 * Spreadsheets in and out: Excel (.xlsx), CSV (comma, semicolon or tab) and OpenDocument (.ods,
 * also what Google Sheets and LibreOffice save). A Google Sheet is downloaded as one of these first.
 */
class Sheets
{
    /** Extensions people may upload for import. Old .xls must be saved as .xlsx first. */
    public const IMPORT = ['xlsx', 'csv', 'tsv', 'txt', 'ods'];

    public const EXPORT = ['xlsx' => 'Excel', 'csv' => 'CSV', 'ods' => 'ODS'];

    /** Rows of the first sheet, as plain arrays (dates as Y-m-d). */
    public static function rows(string $path, string $extension, int $max = 20000): array
    {
        $ext = strtolower($extension);
        $reader = match ($ext) {
            'xlsx' => new XlsxReader,
            'ods' => new OdsReader,
            default => new CsvReader(new CsvOptions(FIELD_DELIMITER: self::delimiter($path))),
        };
        $reader->open($path);
        $rows = [];
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v, $row->toArray());
                    if (count($rows) > $max) {
                        break;
                    }
                }
                break; // first sheet only
            }
        } finally {
            $reader->close();
        }
        if ($rows && isset($rows[0][0]) && is_string($rows[0][0])) {
            $rows[0][0] = preg_replace('/^\xEF\xBB\xBF/', '', $rows[0][0]); // Excel's UTF-8 mark
        }

        return $rows;
    }

    /** Writes rows to a temporary file in the chosen format and returns its path. */
    public static function write(string $format, array $header, iterable $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sheet');
        $w = match ($format) {
            'csv' => new CsvWriter,
            'ods' => new OdsWriter,
            default => new XlsxWriter,
        };
        $w->openToFile($path);
        $w->addRow(Row::fromValues($header));
        foreach ($rows as $r) {
            $w->addRow(Row::fromValues($r));
        }
        $w->close();

        return $path;
    }

    public static function format(?string $asked): string
    {
        return isset(self::EXPORT[$asked]) ? $asked : 'xlsx';
    }

    /** Comma, semicolon (European Excel) or tab, whichever the first line uses most. */
    private static function delimiter(string $path): string
    {
        $h = fopen($path, 'r');
        $line = $h ? (string) fgets($h) : '';
        if ($h) {
            fclose($h);
        }
        $counts = [',' => substr_count($line, ','), ';' => substr_count($line, ';'), "\t" => substr_count($line, "\t")];
        arsort($counts);
        $best = (string) array_key_first($counts);

        return $counts[$best] > 0 ? $best : ',';
    }
}
