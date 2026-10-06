<?php

namespace App\Services;

use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

class ExcelHelper
{
    /** Streaming reader (low memory, does not load the whole file into RAM). */
    public static function reader(string $path): ReaderInterface
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $reader = $ext === 'csv'
            ? new CsvReader
            : new XlsxReader(new XlsxOptions(SHOULD_FORMAT_DATES: true, SHOULD_PRESERVE_EMPTY_ROWS: true));
        $reader->open($path);

        return $reader;
    }

    /**
     * Iterate over the rows of the first sheet: yields [rowNumber => array values].
     */
    public static function rows(ReaderInterface $reader): \Generator
    {
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $rowNumber => $row) {
                yield $rowNumber => array_map(
                    fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d H:i:s') : $v,
                    $row->toArray()
                );
            }

            return; // first sheet only
        }
    }

    /** Is this row the column header row? */
    public static function isHeader(array $values): bool
    {
        $marker = mb_strtolower(config('esg.header_marker'));
        foreach ($values as $v) {
            if (is_string($v) && mb_strtolower(trim($v)) === $marker) {
                return true;
            }
        }

        return false;
    }

    /**
     * Find the column indexes used for the classification text.
     *
     * @return int[]
     */
    public static function textColumnIndexes(array $header): array
    {
        $indexes = [];

        foreach (config('esg.text_columns') as $spec) {
            $found = self::findColumn($header, $spec);
            if ($found !== null && ! in_array($found, $indexes, true)) {
                $indexes[] = $found;
            }
        }

        return $indexes;
    }

    /**
     * Find the column indexes of the report fields stored for the dashboard.
     *
     * @return array<string, int> [field => column index], only the columns found
     */
    public static function reportColumnIndexes(array $header): array
    {
        $indexes = [];
        foreach (config('esg.report_columns') as $field => $spec) {
            $found = self::findColumn($header, $spec);
            if ($found !== null) {
                $indexes[$field] = $found;
            }
        }

        return $indexes;
    }

    /**
     * Build the report fields of one row.
     *
     * @param  array<string, int>  $indexes
     * @return array<string, string|null>
     */
    public static function reportFields(array $values, array $indexes): array
    {
        $fields = [];
        foreach (array_keys(config('esg.report_columns')) as $field) {
            $value = isset($indexes[$field]) ? trim((string) ($values[$indexes[$field]] ?? '')) : '';
            $fields[$field] = $value === '' || $value === '-' ? null : $value;
        }

        $fields['report_date'] = self::parseDate($fields['report_date']);
        foreach (['tracking_id' => 50, 'title' => 500, 'agency' => 255, 'agency_unit' => 255, 'report_status' => 100] as $field => $max) {
            if ($fields[$field] !== null) {
                $fields[$field] = mb_substr(preg_replace('/\s+/u', ' ', $fields[$field]), 0, $max);
            }
        }

        return $fields;
    }

    /** Parse "6 Jan 2026", "06 Agu 2026", "2026-01-06 00:00:00", "06/01/2026" -> "Y-m-d". */
    public static function parseDate(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // Indonesian month names -> English (full names first, before their abbreviations)
        $value = str_ireplace(
            ['Januari', 'Februari', 'Pebruari', 'Maret', 'Juni', 'Juli', 'Agustus', 'Oktober', 'Nopember', 'Desember', 'Mei', 'Agu', 'Agt', 'Okt', 'Nop', 'Des', 'Peb'],
            ['Jan', 'Feb', 'Feb', 'Mar', 'Jun', 'Jul', 'Aug', 'Oct', 'Nov', 'Dec', 'May', 'Aug', 'Aug', 'Oct', 'Nov', 'Dec', 'Feb'],
            trim($value)
        );

        foreach (['!j M Y', '!Y-m-d H:i:s', '!Y-m-d', '!d/m/Y', '!d-m-Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    /**
     * Find a column by spec "Name A|Name B": exact match first, then prefix match.
     */
    private static function findColumn(array $header, string $spec): ?int
    {
        $header = array_map(fn ($h) => mb_strtolower(trim((string) $h)), $header);

        foreach (explode('|', mb_strtolower($spec)) as $name) {
            $found = array_search($name, $header, true);
            if ($found !== false) {
                return $found;
            }
            foreach ($header as $i => $h) {
                if ($h !== '' && str_starts_with($h, $name)) {
                    return $i;
                }
            }
        }

        return null;
    }

    public static function buildText(array $values, array $header, array $indexes): string
    {
        $parts = [];
        foreach ($indexes as $i) {
            $v = trim(preg_replace('/\s+/u', ' ', (string) ($values[$i] ?? '')));
            if ($v !== '' && $v !== '-') {
                $parts[] = trim((string) $header[$i]).': '.$v;
            }
        }

        return mb_substr(implode("\n", $parts), 0, config('esg.max_text_length'));
    }
}
