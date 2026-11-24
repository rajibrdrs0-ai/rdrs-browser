<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * Database backup / restore. Uses a pure-PHP dumper (works on XAMPP and shared hosting without shell access).
 * Files are written to storage/backups as .sql.gz (or .sql when zlib is unavailable).
 */
final class Backup
{
    public static function dir(): string
    {
        $d = STORAGE_PATH . '/backups';
        if (!is_dir($d)) {
            mkdir($d, 0775, true);
        }
        return $d;
    }

    public static function create(string $label = 'manual'): string
    {
        $gz = function_exists('gzopen');
        $name = 'rtti_' . date('Ymd_His') . '_' . preg_replace('/[^a-z0-9]/i', '', $label) . ($gz ? '.sql.gz' : '.sql');
        $path = self::dir() . '/' . $name;
        $fh = $gz ? gzopen($path, 'wb6') : fopen($path, 'wb');
        $w = fn(string $s) => $gz ? gzwrite($fh, $s) : fwrite($fh, $s);
        $pdo = DB::pdo();
        $w("-- RTTI database backup\n-- Created: " . date('Y-m-d H:i:s') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
        foreach ($pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(\PDO::FETCH_NUM) as [$table]) {
            $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(\PDO::FETCH_NUM)[1];
            $w("DROP TABLE IF EXISTS `$table`;\n$create;\n\n");
            $cols = null;
            $batch = [];
            $st = $pdo->query("SELECT * FROM `$table`");
            // skip generated columns – they are recalculated on insert
            $gen = DB::column("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND EXTRA LIKE '%GENERATED%'", ['t' => $table]);
            while ($row = $st->fetch(\PDO::FETCH_ASSOC)) {
                foreach ($gen as $g) {
                    unset($row[$g]);
                }
                $cols ??= '`' . implode('`,`', array_keys($row)) . '`';
                $batch[] = '(' . implode(',', array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), $row)) . ')';
                if (count($batch) >= 200) {
                    $w("INSERT INTO `$table` ($cols) VALUES\n" . implode(",\n", $batch) . ";\n");
                    $batch = [];
                }
            }
            if ($batch) {
                $w("INSERT INTO `$table` ($cols) VALUES\n" . implode(",\n", $batch) . ";\n");
            }
            $w("\n");
        }
        $w("SET FOREIGN_KEY_CHECKS=1;\n");
        $gz ? gzclose($fh) : fclose($fh);
        self::prune();
        return $name;
    }

    public static function list(): array
    {
        $files = [];
        foreach (glob(self::dir() . '/rtti_*.sql*') ?: [] as $f) {
            $files[] = ['name' => basename($f), 'size' => filesize($f), 'time' => filemtime($f)];
        }
        usort($files, fn($a, $b) => $b['time'] <=> $a['time']);
        return $files;
    }

    public static function path(string $name): ?string
    {
        if (!preg_match('/^rtti_[A-Za-z0-9_]+\.sql(\.gz)?$/', $name)) {
            return null;
        }
        $p = self::dir() . '/' . $name;
        return is_file($p) ? $p : null;
    }

    public static function restore(string $name): void
    {
        $p = self::path($name) ?? throw new \RuntimeException('Backup file not found.');
        $sql = str_ends_with($name, '.gz') ? gzdecode((string)file_get_contents($p)) : file_get_contents($p);
        if ($sql === false || !str_contains($sql, 'RTTI database backup')) {
            throw new \RuntimeException('This is not a valid RTTI backup file.');
        }
        $pdo = DB::pdo();
        foreach (Installer::splitSql($sql) as $stmt) {
            $pdo->exec($stmt);
        }
    }

    /** Delete backups older than the configured retention */
    public static function prune(): void
    {
        $days = max(1, (int)\App\Core\Settings::get('backup_keep_days', 30));
        foreach (self::list() as $f) {
            if ($f['time'] < time() - $days * 86400) {
                @unlink(self::dir() . '/' . $f['name']);
            }
        }
    }
}
