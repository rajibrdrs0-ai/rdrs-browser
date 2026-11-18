<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Minimal, dependency-free .env loader.
 * Supports KEY=value, quoted values, inline "#" comments and ${VAR} expansion.
 */
final class Env
{
    /** @var array<string,string> */
    private static array $values = [];

    public static function load(string $file): void
    {
        if (!is_file($file) || !is_readable($file)) {
            return;
        }
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            if (str_starts_with($key, 'export ')) {
                $key = trim(substr($key, 7));
            }
            if (!preg_match('/^[A-Z_][A-Z0-9_]*$/i', $key)) {
                continue;
            }
            $value = self::parseValue($value);
            // Real environment variables (server config) take precedence.
            if (getenv($key) !== false) {
                $value = (string) getenv($key);
            }
            self::$values[$key] = $value;
            $_ENV[$key] = $value;
        }
    }

    private static function parseValue(string $value): string
    {
        if ($value === '') {
            return '';
        }
        $first = $value[0];
        if (($first === '"' || $first === "'") && ($end = strpos($value, $first, 1)) !== false) {
            $inner = substr($value, 1, $end - 1);
            return $first === '"' ? self::expand(stripcslashes($inner)) : $inner;
        }
        $hash = strpos($value, ' #');
        if ($hash !== false) {
            $value = rtrim(substr($value, 0, $hash));
        }
        return self::expand($value);
    }

    private static function expand(string $value): string
    {
        return (string) preg_replace_callback(
            '/\$\{([A-Z_][A-Z0-9_]*)\}/i',
            static fn(array $m): string => (string) (self::$values[$m[1]] ?? getenv($m[1]) ?: ''),
            $value
        );
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$values[$key] ?? (getenv($key) !== false ? getenv($key) : null);
        if ($value === null) {
            return $default;
        }
        return match (strtolower((string) $value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            'empty', '(empty)' => '',
            default            => $value,
        };
    }
}
