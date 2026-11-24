<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Generates formatted, gap-free document numbers (Student ID, receipt no, ...).
 * Format tokens: {YYYY} {YY} {MM} {CENTER} {COURSE} {SEQn} (n = zero padding).
 * The counter row is locked with SELECT ... FOR UPDATE, so call inside DB::transaction().
 */
final class Sequence
{
    public static function next(string $formatSettingKey, string $defaultFormat, array $tokens = []): string
    {
        $format = (string)Settings::get($formatSettingKey, $defaultFormat);
        $now = new \DateTimeImmutable('now');
        $repl = [
            '{YYYY}' => $now->format('Y'),
            '{YY}' => $now->format('y'),
            '{MM}' => $now->format('m'),
            '{CENTER}' => strtoupper((string)($tokens['center'] ?? '')),
            '{COURSE}' => strtoupper((string)($tokens['course'] ?? '')),
        ];
        $prefix = strtr($format, $repl);
        // Counter key = everything except the {SEQn} token, so counters reset per year/center automatically.
        $key = $formatSettingKey . ':' . preg_replace('/\{SEQ\d*\}/', '#', $prefix);
        $key = substr($key, 0, 60);

        return DB::transaction(function () use ($key, $prefix) {
            DB::query('INSERT IGNORE INTO sequences (seq_key, last_no) VALUES (:k, 0)', ['k' => $key]);
            $last = (int)DB::value('SELECT last_no FROM sequences WHERE seq_key = :k FOR UPDATE', ['k' => $key]);
            $next = $last + 1;
            DB::query('UPDATE sequences SET last_no = :v WHERE seq_key = :k', ['v' => $next, 'k' => $key]);
            return preg_replace_callback('/\{SEQ(\d*)\}/', function ($m) use ($next) {
                $pad = $m[1] === '' ? 1 : (int)$m[1];
                return str_pad((string)$next, $pad, '0', STR_PAD_LEFT);
            }, $prefix);
        });
    }
}
