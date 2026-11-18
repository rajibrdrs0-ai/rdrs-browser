<?php
/**
 * Global helper functions (loaded by Composer "files" or bootstrap/app.php).
 * The translation helper __() is provided by the Bilingual Engine (Phase 3).
 */
declare(strict_types=1);

use App\Core\Application;
use App\Core\Config;
use App\Core\Env;
use App\Core\Session;
use App\Security\Csrf;
use App\Security\SecurityHeaders;

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('e')) {
    /** HTML-escape for output in templates (XSS protection). */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}

if (!function_exists('base_url')) {
    /** Base URL path of the installation as seen by the browser ("/courier/public" or ""). */
    function base_url(): string
    {
        try {
            $request = Application::getInstance()->request();
        } catch (\RuntimeException) {
            $request = null;
        }
        return $request !== null ? $request->basePath() : '';
    }
}

if (!function_exists('url')) {
    /** @param array<string,scalar> $query */
    function url(string $path = '/', array $query = []): string
    {
        $url = base_url() . '/' . ltrim($path, '/');
        return $query === [] ? $url : $url . '?' . http_build_query($query);
    }
}

if (!function_exists('route')) {
    /** @param array<string,scalar> $params */
    function route(string $name, array $params = []): string
    {
        return base_url() . Application::getInstance()->router()->url($name, $params);
    }
}

if (!function_exists('asset')) {
    /** Asset URL with cache-busting version from file mtime. */
    function asset(string $path): string
    {
        $path = ltrim($path, '/');
        $file = BASE_PATH . '/public/' . $path;
        $version = is_file($file) ? (string) filemtime($file) : (string) config('app.version', '1');
        return base_url() . '/' . $path . '?v=' . $version;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return Csrf::field();
    }
}

if (!function_exists('method_field')) {
    function method_field(string $method): string
    {
        return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
    }
}

if (!function_exists('csp_nonce')) {
    function csp_nonce(): string
    {
        return SecurityHeaders::nonce();
    }
}

if (!function_exists('old')) {
    /** Previously submitted value after a failed validation redirect. */
    function old(string $key, mixed $default = ''): mixed
    {
        $old = Session::getFlash('old', []);
        return is_array($old) && array_key_exists($key, $old) ? $old[$key] : $default;
    }
}

if (!function_exists('errors')) {
    /** @return array<string,list<string>>|list<string> */
    function errors(?string $field = null): array
    {
        $errors = Session::getFlash('errors', []);
        $errors = is_array($errors) ? $errors : [];
        return $field === null ? $errors : (array) ($errors[$field] ?? []);
    }
}

if (!function_exists('flash')) {
    function flash(string $key, mixed $default = null): mixed
    {
        return Session::getFlash($key, $default);
    }
}

if (!function_exists('money')) {
    /** Format amount in BDT, e.g. ৳ 1,250.00 (Bangla digits when locale is bn). */
    function money(float|int|string|null $amount, bool $symbol = true): string
    {
        $formatted = number_format((float) $amount, 2);
        if (function_exists('current_locale') && current_locale() === 'bn') {
            $formatted = bn_digits($formatted);
        }
        return ($symbol ? (string) config('app.currency.symbol', '৳') . ' ' : '') . $formatted;
    }
}

if (!function_exists('bn_digits')) {
    /** Convert ASCII digits to Bangla digits (০-৯). */
    function bn_digits(string|int|float $value): string
    {
        return strtr((string) $value, ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪',
            '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯']);
    }
}

if (!function_exists('en_digits')) {
    /** Convert Bangla digits to ASCII (for parsing user input typed with a Bangla keyboard). */
    function en_digits(string $value): string
    {
        return strtr($value, ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4',
            '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9']);
    }
}

if (!function_exists('normalize_phone')) {
    /** Normalise Bangladeshi mobile numbers to 01XXXXXXXXX. */
    function normalize_phone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', en_digits($phone)) ?? '';
        if (str_starts_with($digits, '880')) {
            $digits = substr($digits, 2); // 8801XXXXXXXXX -> 01XXXXXXXXX
        }
        return $digits;
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        return BASE_PATH . '/storage' . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('logger')) {
    /** Append a line to storage/logs/<channel>-YYYY-MM-DD.log */
    function logger(string $message, array $context = [], string $channel = 'app'): void
    {
        $channel = preg_replace('/[^a-z0-9_-]/i', '', $channel) ?: 'app';
        $line = sprintf("[%s] %s %s\n", date('Y-m-d H:i:s'), $message,
            $context === [] ? '' : (json_encode($context, JSON_UNESCAPED_UNICODE) ?: ''));
        @file_put_contents(storage_path("logs/{$channel}-" . date('Y-m-d') . '.log'), $line, FILE_APPEND | LOCK_EX);
    }
}
