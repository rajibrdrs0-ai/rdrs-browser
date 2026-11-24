<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;
use RuntimeException;

/**
 * Thin PDO wrapper. All queries use prepared statements.
 */
final class DB
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $c = config('db');
            if (!$c) {
                throw new RuntimeException('Database is not configured. Run install.php first.');
            }
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $c['host'], (int)($c['port'] ?? 3306), $c['name'], $c['charset'] ?? 'utf8mb4');
            self::$pdo = new PDO($dsn, $c['user'], $c['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]);
            self::$pdo->exec("SET time_zone = '+06:00'");
        }
        return self::$pdo;
    }

    public static function setPdo(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        foreach ($params as $k => $v) {
            $key = is_int($k) ? $k + 1 : (str_starts_with((string)$k, ':') ? $k : ':' . $k);
            $type = is_int($v) ? PDO::PARAM_INT : (is_null($v) ? PDO::PARAM_NULL : (is_bool($v) ? PDO::PARAM_INT : PDO::PARAM_STR));
            $st->bindValue($key, is_bool($v) ? (int)$v : $v, $type);
        }
        $st->execute();
        return $st;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function row(string $sql, array $params = []): ?array
    {
        $r = self::query($sql, $params)->fetch();
        return $r === false ? null : $r;
    }

    public static function value(string $sql, array $params = [])
    {
        $v = self::query($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public static function column(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll(PDO::FETCH_COLUMN);
    }

    /** key => value pairs */
    public static function pairs(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public static function insert(string $table, array $data): int
    {
        self::assertIdent($table);
        $cols = array_keys($data);
        array_walk($cols, [self::class, 'assertIdent']);
        $sql = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES (:' . implode(',:', $cols) . ')';
        self::query($sql, $data);
        return (int)self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        self::assertIdent($table);
        $sets = [];
        $params = [];
        foreach ($data as $col => $val) {
            self::assertIdent($col);
            $sets[] = "`$col` = :set_$col";
            $params["set_$col"] = $val;
        }
        $sql = 'UPDATE `' . $table . '` SET ' . implode(', ', $sets) . ' WHERE ' . $where;
        return self::query($sql, array_merge($params, $whereParams))->rowCount();
    }

    public static function find(string $table, int $id): ?array
    {
        self::assertIdent($table);
        return self::row("SELECT * FROM `$table` WHERE id = :id", ['id' => $id]);
    }

    public static function exists(string $sql, array $params = []): bool
    {
        return self::value($sql, $params) !== null;
    }

    /**
     * Run $fn inside a transaction. Nested calls join the outer transaction.
     */
    public static function transaction(callable $fn)
    {
        $pdo = self::pdo();
        if ($pdo->inTransaction()) {
            return $fn();
        }
        $pdo->beginTransaction();
        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Paginate a SELECT. $sql must not contain LIMIT.
     * Returns [rows, total, page, per_page, pages].
     */
    public static function paginate(string $sql, array $params, int $perPage = 25, ?int $page = null): array
    {
        $page = max(1, $page ?? (int)($_GET['page'] ?? 1));
        $perPage = max(1, min(500, (int)($_GET['per_page'] ?? $perPage)));
        $total = (int)self::value('SELECT COUNT(*) FROM (' . $sql . ') _cnt', $params);
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;
        $rows = self::all($sql . " LIMIT $perPage OFFSET $offset", $params);
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => $pages];
    }

    public static function assertIdent(string $name): void
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            throw new RuntimeException('Invalid identifier: ' . $name);
        }
    }

    /** Build "IN (:p0,:p1)" placeholders for a list of ints */
    public static function inInts(array $ids): string
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        return $ids ? implode(',', $ids) : '0';
    }
}
