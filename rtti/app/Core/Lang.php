<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Minimal English / Bangla translation. Missing keys fall back to the key itself.
 */
final class Lang
{
    private static ?array $dict = null;

    public static function current(): string
    {
        $l = $_SESSION['lang'] ?? Settings::get('default_language', 'en');
        return in_array($l, ['en', 'bn'], true) ? $l : 'en';
    }

    public static function t(string $key): string
    {
        if (self::$dict === null) {
            $file = APP_PATH . '/Lang/' . self::current() . '.php';
            self::$dict = is_file($file) ? require $file : [];
        }
        return self::$dict[$key] ?? $key;
    }
}
