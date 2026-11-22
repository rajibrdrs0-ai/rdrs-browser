<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Bilingual (বাংলা / English) translation loader.
 * Strings live in lang/bn.php and lang/en.php as nested arrays;
 * keys use dot notation: __('menu.users').
 */
final class Lang
{
    private static string $locale = 'bn';
    /** @var array<string,array<string,mixed>> */
    private static array $loaded = [];

    public static function setLocale(string $locale): void
    {
        $available = array_keys((array) Config::get('app.locales', ['bn' => '', 'en' => '']));
        self::$locale = in_array($locale, $available, true) ? $locale : (string) Config::get('app.locale', 'bn');
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    /** @return array<string,mixed> */
    private static function strings(string $locale): array
    {
        if (!isset(self::$loaded[$locale])) {
            $file = BASE_PATH . '/lang/' . basename($locale) . '.php';
            self::$loaded[$locale] = is_file($file) ? (array) require $file : [];
        }
        return self::$loaded[$locale];
    }

    private static function lookup(string $locale, string $key): ?string
    {
        $value = self::strings($locale);
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }
        return is_string($value) ? $value : null;
    }

    /** @param array<string,scalar|null> $replace */
    public static function get(string $key, array $replace = [], ?string $default = null): string
    {
        $text = self::lookup(self::$locale, $key)
            ?? self::lookup((string) Config::get('app.fallback_locale', 'en'), $key)
            ?? $default
            ?? $key;
        foreach ($replace as $name => $value) {
            $text = str_replace(':' . $name, (string) $value, $text);
        }
        return $text;
    }
}
