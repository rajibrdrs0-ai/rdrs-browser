<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Race-safe document numbers. Uses the LAST_INSERT_ID(expr) trick so two
 * concurrent requests can never receive the same number, even without an
 * explicit transaction.
 */
final class Sequence
{
    public static function next(string $key, string $period = 'ALL'): int
    {
        Database::query(
            'INSERT INTO number_sequences (seq_key, seq_period, `last_value`) VALUES (?, ?, LAST_INSERT_ID(1))
             ON DUPLICATE KEY UPDATE `last_value` = LAST_INSERT_ID(`last_value` + 1)',
            [$key, $period]
        );
        return (int) Database::value('SELECT LAST_INSERT_ID()');
    }
}
