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

if (!function_exists('__')) {
    /** Translate a key for the current locale: __('menu.users'), __('users.saved', ['name' => $n]) */
    function __(string $key, array $replace = [], ?string $default = null): string
    {
        return \App\Core\Lang::get($key, $replace, $default);
    }
}

if (!function_exists('current_locale')) {
    function current_locale(): string
    {
        return \App\Core\Lang::locale();
    }
}

if (!function_exists('loc')) {
    /** Pick the bn/en column of a row: loc($role, 'name') -> name_bn or name_en */
    function loc(array $row, string $base): string
    {
        $bn = $row[$base . '_bn'] ?? null;
        $en = $row[$base . '_en'] ?? $row[$base] ?? '';
        return (string) (current_locale() === 'bn' && $bn !== null && $bn !== '' ? $bn : $en);
    }
}

if (!function_exists('auth')) {
    /** The logged-in user (array) or null. */
    function auth(): ?array
    {
        return \App\Services\Auth::user();
    }
}

if (!function_exists('can')) {
    function can(string $permission): bool
    {
        return \App\Services\Rbac::can($permission);
    }
}

/* ------------------------------------------------------------------
 * Form helpers — Bootstrap markup with old() re-population and
 * per-field validation errors. All output is escaped.
 * ------------------------------------------------------------------ */

if (!function_exists('html_attrs')) {
    /** @param array<string,scalar|bool|null> $attrs */
    function html_attrs(array $attrs): string
    {
        $out = '';
        foreach ($attrs as $key => $value) {
            if ($value === false || $value === null) {
                continue;
            }
            $out .= ' ' . e($key) . ($value === true ? '' : '="' . e($value) . '"');
        }
        return $out;
    }
}

if (!function_exists('field_error')) {
    function field_error(string $name): string
    {
        $messages = errors(str_replace(['[', ']'], ['.', ''], $name));
        return $messages === [] ? '' : '<div class="invalid-feedback d-block">' . e(implode(' ', $messages)) . '</div>';
    }
}

if (!function_exists('form_label')) {
    function form_label(string $id, string $label, bool $required): string
    {
        return '<label class="form-label" for="' . e($id) . '">' . e($label) . ($required ? ' <span class="text-danger">*</span>' : '') . '</label>';
    }
}

if (!function_exists('form_input')) {
    /** @param array<string,scalar|bool|null> $attrs  extra: 'help' => text */
    function form_input(string $name, string $label, mixed $value = '', array $attrs = []): string
    {
        $help = $attrs['help'] ?? null;
        unset($attrs['help']);
        $id = (string) ($attrs['id'] ?? $name);
        $invalid = errors($name) !== [] ? ' is-invalid' : '';
        $attrs = ['type' => 'text', 'class' => 'form-control' . $invalid, 'id' => $id, 'name' => $name,
                  'value' => (string) old($name, $value ?? '')] + $attrs;
        return form_label($id, $label, !empty($attrs['required']))
            . '<input' . html_attrs($attrs) . '>'
            . ($help !== null ? '<div class="form-text">' . e($help) . '</div>' : '')
            . field_error($name);
    }
}

if (!function_exists('form_select')) {
    /**
     * @param array<int|string,string> $options value => label
     * @param array<string,scalar|bool|null> $attrs  extra: 'placeholder' => text|false
     */
    function form_select(string $name, string $label, array $options, mixed $selected = '', array $attrs = []): string
    {
        $placeholder = array_key_exists('placeholder', $attrs) ? $attrs['placeholder'] : __('common.select');
        unset($attrs['placeholder']);
        $id = (string) ($attrs['id'] ?? $name);
        $current = (string) old($name, $selected ?? '');
        $invalid = errors($name) !== [] ? ' is-invalid' : '';
        $html = form_label($id, $label, !empty($attrs['required']))
            . '<select' . html_attrs(['class' => 'form-select' . $invalid, 'id' => $id, 'name' => $name] + $attrs) . '>';
        if ($placeholder !== false) {
            $html .= '<option value="">' . e($placeholder) . '</option>';
        }
        foreach ($options as $value => $text) {
            $html .= '<option value="' . e($value) . '"' . ((string) $value === $current ? ' selected' : '') . '>' . e($text) . '</option>';
        }
        return $html . '</select>' . field_error($name);
    }
}

if (!function_exists('form_textarea')) {
    function form_textarea(string $name, string $label, mixed $value = '', array $attrs = []): string
    {
        $id = (string) ($attrs['id'] ?? $name);
        $invalid = errors($name) !== [] ? ' is-invalid' : '';
        return form_label($id, $label, !empty($attrs['required']))
            . '<textarea' . html_attrs(['class' => 'form-control' . $invalid, 'id' => $id, 'name' => $name, 'rows' => 3] + $attrs) . '>'
            . e(old($name, $value ?? '')) . '</textarea>' . field_error($name);
    }
}

if (!function_exists('form_check')) {
    function form_check(string $name, string $label, bool $checked = false, array $attrs = []): string
    {
        $id = (string) ($attrs['id'] ?? $name);
        $oldAll = \App\Core\Session::getFlash('old', []);
        if (is_array($oldAll) && $oldAll !== []) {
            $checked = !empty($oldAll[$name]);
        }
        return '<div class="form-check">'
            . '<input type="hidden" name="' . e($name) . '" value="0">'
            . '<input' . html_attrs(['class' => 'form-check-input', 'type' => 'checkbox', 'id' => $id, 'name' => $name, 'value' => '1', 'checked' => $checked] + $attrs) . '>'
            . '<label class="form-check-label" for="' . e($id) . '">' . e($label) . '</label></div>';
    }
}

if (!function_exists('options')) {
    /**
     * Turn DB rows into select options using the localised name column.
     * @param list<array<string,mixed>> $rows
     * @return array<int|string,string>
     */
    function options(array $rows, string $base = 'name', string $key = 'id', ?string $prefixKey = null): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[$row[$key]] = ($prefixKey !== null ? $row[$prefixKey] . ' — ' : '') . loc($row, $base);
        }
        return $out;
    }
}

if (!function_exists('fmt_date')) {
    function fmt_date(?string $date): string
    {
        if ($date === null || $date === '') {
            return '—';
        }
        $out = date('d/m/Y', strtotime($date) ?: 0);
        return current_locale() === 'bn' ? bn_digits($out) : $out;
    }
}

if (!function_exists('num')) {
    /** Locale-aware integer / decimal display. */
    function num(int|float|string|null $value, int $decimals = 0): string
    {
        $out = number_format((float) $value, $decimals);
        return current_locale() === 'bn' ? bn_digits($out) : $out;
    }
}
