<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Minimal .xlsx reader (first worksheet → array of rows). Needs the PHP zip extension.
 */
final class XlsxReader
{
    public static function available(): bool
    {
        return class_exists(\ZipArchive::class);
    }

    public static function read(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Cannot open the Excel file.');
        }
        $strings = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $sx = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET);
            foreach ($sx->si as $si) {
                if (isset($si->t)) {
                    $strings[] = (string)$si->t;
                } else {
                    $t = '';
                    foreach ($si->r as $r) {
                        $t .= (string)$r->t;
                    }
                    $strings[] = $t;
                }
            }
        }
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($sheet === false) {
            throw new \RuntimeException('The Excel file has no first worksheet.');
        }
        $sx = simplexml_load_string($sheet, \SimpleXMLElement::class, LIBXML_NONET);
        $rows = [];
        foreach ($sx->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $ref = (string)$c['r'];
                $col = self::colIndex(preg_replace('/\d+/', '', $ref));
                $type = (string)$c['t'];
                $v = isset($c->v) ? (string)$c->v : (isset($c->is->t) ? (string)$c->is->t : '');
                if ($type === 's') {
                    $v = $strings[(int)$v] ?? '';
                }
                $cells[$col] = trim($v);
            }
            if ($cells) {
                $max = max(array_keys($cells));
                $line = [];
                for ($i = 0; $i <= $max; $i++) {
                    $line[] = $cells[$i] ?? '';
                }
                $rows[] = $line;
            }
        }
        return $rows;
    }

    private static function colIndex(string $letters): int
    {
        $n = 0;
        foreach (str_split(strtoupper($letters)) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }
        return $n - 1;
    }
}
