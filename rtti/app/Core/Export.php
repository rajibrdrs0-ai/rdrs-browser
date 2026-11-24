<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Export tabular data as CSV, Excel (.xls – SpreadsheetML-compatible HTML table, opens in Excel
 * with UTF-8 Bangla intact) or a print-ready page (use the browser's "Save as PDF").
 * $columns = ['key' => 'Header', ...]; value may be a callable(row) => string.
 */
final class Export
{
    public static function requested(): ?string
    {
        $x = $_GET['export'] ?? null;
        return in_array($x, ['csv', 'xls', 'print', 'pdf'], true) ? $x : null;
    }

    public static function send(string $format, string $title, array $columns, array $rows, array $meta = []): never
    {
        Audit::log('export', $format, null, $title . ' (' . count($rows) . ' rows)');
        $file = preg_replace('/[^A-Za-z0-9_-]+/', '_', $title) . '_' . date('Ymd_His');
        $cell = fn(array $row, $key) => (string)($row[$key] ?? '');
        $keys = array_keys($columns);
        $callables = [];
        foreach ($columns as $k => $h) {
            if (is_array($h)) { // ['Header', callable]
                $callables[$k] = $h[1];
                $columns[$k] = $h[0];
            }
        }
        $val = function (array $row, string $k) use ($callables, $cell) {
            return isset($callables[$k]) ? (string)$callables[$k]($row) : $cell($row, $k);
        };

        if ($format === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $file . '.csv"');
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_values($columns));
            foreach ($rows as $r) {
                fputcsv($out, array_map(fn($k) => self::csvSafe($val($r, (string)$k)), $keys));
            }
            fclose($out);
            exit;
        }

        $headHtml = '';
        foreach ($columns as $h) {
            $headHtml .= '<th>' . e($h) . '</th>';
        }
        $bodyHtml = '';
        foreach ($rows as $i => $r) {
            $bodyHtml .= '<tr>';
            foreach ($keys as $k) {
                $v = $val($r, (string)$k);
                $style = (is_numeric($v) && preg_match('/^0\d+/', $v)) ? ' style="mso-number-format:\'\@\'"' : '';
                $bodyHtml .= '<td' . $style . '>' . e($v) . '</td>';
            }
            $bodyHtml .= '</tr>';
        }
        $footHtml = '';
        if (!empty($meta['totals'])) {
            $footHtml = '<tr class="tot">';
            foreach ($keys as $k) {
                $footHtml .= '<td><b>' . e($meta['totals'][$k] ?? '') . '</b></td>';
            }
            $footHtml .= '</tr>';
        }

        if ($format === 'xls') {
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $file . '.xls"');
            echo "\xEF\xBB\xBF<html><head><meta charset=\"utf-8\"></head><body><table border=\"1\"><thead><tr><th colspan=\"" . count($columns) . "\">"
                . e($title) . '</th></tr><tr>' . $headHtml . '</tr></thead><tbody>' . $bodyHtml . $footHtml . '</tbody></table></body></html>';
            exit;
        }

        // print / pdf
        View::render('partials/print_table', [
            'title' => $title,
            'subtitle' => $meta['subtitle'] ?? '',
            'headHtml' => $headHtml,
            'bodyHtml' => $bodyHtml . $footHtml,
            'count' => count($rows),
        ], 'print');
        exit;
    }

    /** Prevent CSV formula injection */
    private static function csvSafe(string $v): string
    {
        return ($v !== '' && in_array($v[0], ['=', '+', '-', '@'], true) && !is_numeric($v)) ? "'" . $v : $v;
    }

    /** Parse an uploaded CSV into associative rows (header row = keys, lower_snake_case) */
    public static function readCsv(string $tmpPath): array
    {
        $fh = fopen($tmpPath, 'r');
        if (!$fh) {
            return [];
        }
        $first = fgets($fh);
        $first = preg_replace('/^\xEF\xBB\xBF/', '', (string)$first);
        $delim = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        $header = array_map(fn($h) => strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '_', $h), '_')), str_getcsv($first, $delim));
        $rows = [];
        while (($line = fgetcsv($fh, 0, $delim)) !== false) {
            if (count(array_filter($line, fn($x) => trim((string)$x) !== '')) === 0) {
                continue;
            }
            $row = [];
            foreach ($header as $i => $h) {
                $row[$h] = trim((string)($line[$i] ?? ''));
            }
            $rows[] = $row;
        }
        fclose($fh);
        return $rows;
    }
}
