<?php
declare(strict_types=1);

namespace App\Core;

final class Settings
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache === null) {
            try {
                self::$cache = DB::pairs('SELECT setting_key, setting_value FROM system_settings');
            } catch (\Throwable) {
                self::$cache = [];
            }
        }
        return self::$cache;
    }

    public static function get(string $key, $default = '')
    {
        $all = self::all();
        return (array_key_exists($key, $all) && $all[$key] !== null && $all[$key] !== '') ? $all[$key] : $default;
    }

    public static function set(string $key, ?string $value, string $group = 'general'): void
    {
        DB::query(
            'INSERT INTO system_settings (setting_key, setting_value, setting_group) VALUES (:k,:v,:g)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
            ['k' => $key, 'v' => $value, 'g' => $group]
        );
        self::$cache = null;
    }
}
