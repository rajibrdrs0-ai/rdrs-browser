<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;
use Throwable;

/**
 * PDO singleton. Native prepared statements only, utf8mb4, Dhaka time zone.
 * After login the audit context (@app_user_id, @app_center_id, @app_ip) is set
 * on the connection so database triggers can attribute every change.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $cfg = (array) Config::get('database', []);
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $cfg['host'], (int) $cfg['port'], $cfg['database'], $cfg['charset']);
            self::$pdo = new PDO($dsn, (string) $cfg['username'], (string) $cfg['password'], (array) $cfg['options']);
            self::$pdo->exec("SET time_zone = '+06:00'");
        }
        return self::$pdo;
    }

    public static function setAuditContext(?int $userId, ?int $centerId, string $ip): void
    {
        $stmt = self::pdo()->prepare('SET @app_user_id = ?, @app_center_id = ?, @app_ip = ?');
        $stmt->execute([$userId, $centerId, $ip]);
    }

    /** @param array<int|string,mixed> $params */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** @return array<string,mixed>|null */
    public static function first(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** @return list<array<string,mixed>> */
    public static function all(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $value = self::query($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    public static function lastInsertId(): int
    {
        return (int) self::pdo()->lastInsertId();
    }

    /**
     * Run $callback inside a transaction; rolls back and re-throws on any error.
     * @template T
     * @param callable():T $callback
     * @return T
     */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $callback();
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Build "IN (?,?,?)" placeholders for a list.
     * @param list<int|string> $values
     */
    public static function in(array $values): string
    {
        return $values === [] ? '(NULL)' : '(' . implode(',', array_fill(0, count($values), '?')) . ')';
    }
}
