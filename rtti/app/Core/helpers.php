<?php
/**
 * Global helper functions.
 */
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Lang;
use App\Core\Settings;

function config(string $key, $default = null)
{
    $cfg = $GLOBALS['__config'] ?? [];
    foreach (explode('.', $key) as $part) {
        if (!is_array($cfg) || !array_key_exists($part, $cfg)) {
            return $default;
        }
        $cfg = $cfg[$part];
    }
    return $cfg;
}

/** HTML escape */
function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_url(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $cfg = (string)config('base_url', '');
    if ($cfg !== '') {
        return $base = rtrim($cfg, '/') . '/';
    }
    if (PHP_SAPI === 'cli') {
        return $base = '/';
    }
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    return $base = rtrim($dir, '/') . '/';
}

/** Build an application URL: url('students/view', ['id' => 5]) */
function url(string $route = '', array $params = []): string
{
    $q = $route !== '' ? ['r' => $route] + $params : $params;
    return base_url() . 'index.php' . ($q ? '?' . http_build_query($q) : '');
}

function asset(string $path): string
{
    return base_url() . 'assets/' . ltrim($path, '/');
}

/** URL of a stored upload, served through the access-controlled file route */
function logo_url(): string
{
    return base_url() . ltrim((string)setting('institute_logo', 'assets/img/logo.jpg'), '/');
}

function file_url(?string $path): string
{
    return $path ? url('file/show', ['f' => $path]) : '';
}

function absolute_url(string $relative): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (preg_match('#^https?://#', $relative)) {
        return $relative;
    }
    return $scheme . '://' . $host . $relative;
}

function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

function back(string $fallback = ''): never
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    redirect($ref !== '' ? $ref : ($fallback ?: url('dashboard')));
}

function is_ajax(): bool
{
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
        || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function input(string $key, $default = null)
{
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function input_int(string $key, int $default = 0): int
{
    $v = input($key);
    return is_numeric($v) ? (int)$v : $default;
}

/** Nullable int (for optional foreign keys) */
function input_id(string $key): ?int
{
    $v = input($key);
    return (is_numeric($v) && (int)$v > 0) ? (int)$v : null;
}

function input_money(string $key): float
{
    $v = str_replace([',', ' '], '', (string)input($key, '0'));
    return is_numeric($v) ? round((float)$v, 2) : 0.0;
}

function input_date(string $key): ?string
{
    $v = (string)input($key, '');
    if ($v === '') {
        return null;
    }
    $d = DateTime::createFromFormat('Y-m-d', $v);
    return ($d && $d->format('Y-m-d') === $v) ? $v : null;
}

function nullify($v)
{
    return ($v === '' || $v === null) ? null : $v;
}

/* ---------------- Flash / old input ---------------- */
function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

function old(string $key, $default = '')
{
    return $_SESSION['_old'][$key] ?? $default;
}

function keep_old(): void
{
    $_SESSION['_old'] = $_POST;
    unset($_SESSION['_old']['_csrf'], $_SESSION['_old']['password']);
}

function clear_old(): void
{
    unset($_SESSION['_old']);
}

/* ---------------- CSRF ---------------- */
function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $sent = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        if (is_ajax()) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'message' => 'Security token expired. Please reload the page.']);
            exit;
        }
        flash('danger', 'Your security token expired. Please try again.');
        back();
    }
}

/* ---------------- Auth shortcuts ---------------- */
function can(string $perm): bool
{
    return Auth::can($perm);
}

function user(): ?array
{
    return Auth::user();
}

function t(string $key): string
{
    return Lang::t($key);
}

function setting(string $key, $default = '')
{
    return Settings::get($key, $default);
}

/* ---------------- Formatting ---------------- */
function money($amount, bool $symbol = true): string
{
    $n = number_format((float)$amount, 2);
    return $symbol ? setting('currency_symbol', '৳') . ' ' . $n : $n;
}

function fmt_date(?string $date, ?string $format = null): string
{
    if (!$date || str_starts_with($date, '0000')) {
        return '';
    }
    $ts = strtotime($date);
    return $ts ? date($format ?? setting('date_format', 'd/m/Y'), $ts) : '';
}

function fmt_datetime(?string $date): string
{
    return $date ? fmt_date($date, setting('date_format', 'd/m/Y') . ' h:i A') : '';
}

function status_badge(?string $status, ?string $color = null): string
{
    $map = [
        'active' => 'success', 'approved' => 'success', 'posted' => 'success', 'issued' => 'success', 'verified' => 'success',
        'present' => 'success', 'pass' => 'success', 'completed' => 'primary', 'evaluated' => 'primary', 'published' => 'primary',
        'in_use' => 'success', 'pending' => 'warning', 'on_leave' => 'warning', 'late' => 'warning', 'under_maintenance' => 'warning',
        'submitted' => 'info', 'marks_entry' => 'info', 'scheduled' => 'info', 'in_store' => 'info', 'alumni' => 'info', 'leave' => 'info',
        'inactive' => 'secondary', 'transferred' => 'secondary', 'dropped' => 'secondary', 'disposed' => 'dark',
        'cancelled' => 'danger', 'rejected' => 'danger', 'reversed' => 'danger', 'absent' => 'danger', 'fail' => 'danger',
        'blacklisted' => 'danger', 'resigned' => 'secondary', 'terminated' => 'danger', 'lost' => 'danger', 'retired' => 'secondary',
    ];
    $c = $color ?: ($map[strtolower((string)$status)] ?? 'secondary');
    $label = ucwords(str_replace('_', ' ', (string)$status));
    return '<span class="badge text-bg-' . e($c) . '">' . e($label) . '</span>';
}

/** <option> list from rows */
function options(array $rows, $selected = null, string $valueKey = 'id', string $labelKey = 'name', string $placeholder = '-- Select --'): string
{
    $html = $placeholder !== '' ? '<option value="">' . e($placeholder) . '</option>' : '';
    $sel = is_array($selected) ? array_map('strval', $selected) : [(string)$selected];
    foreach ($rows as $r) {
        $v = (string)$r[$valueKey];
        $html .= '<option value="' . e($v) . '"' . (in_array($v, $sel, true) ? ' selected' : '') . '>' . e($r[$labelKey]) . '</option>';
    }
    return $html;
}

/** Options from a simple [value => label] array */
function kv_options(array $map, $selected = null, string $placeholder = '-- Select --'): string
{
    $html = $placeholder !== '' ? '<option value="">' . e($placeholder) . '</option>' : '';
    foreach ($map as $v => $l) {
        $html .= '<option value="' . e($v) . '"' . ((string)$v === (string)$selected ? ' selected' : '') . '>' . e($l) . '</option>';
    }
    return $html;
}

function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? 'cli'), 0, 45);
}

function query_string_without(array $remove = []): string
{
    $q = $_GET;
    foreach ($remove as $k) {
        unset($q[$k]);
    }
    return http_build_query($q);
}

/** Current URL with extra/overridden query params */
function current_url_with(array $params): string
{
    $q = array_merge($_GET, $params);
    return base_url() . 'index.php?' . http_build_query($q);
}

function json_response(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Age in years from a Y-m-d date */
function age(?string $dob): string
{
    if (!$dob) {
        return '';
    }
    try {
        return (string)(new DateTime($dob))->diff(new DateTime('today'))->y;
    } catch (Throwable) {
        return '';
    }
}

function amount_in_words(float $number): string
{
    $number = round($number, 2);
    $taka = (int)floor($number);
    $paisa = (int)round(($number - $taka) * 100);
    $words = $taka === 0 ? 'Zero' : _num_words_bd($taka);
    $out = $words . ' Taka';
    if ($paisa > 0) {
        $out .= ' and ' . _num_words_bd($paisa) . ' Paisa';
    }
    return $out . ' Only';
}

function _num_words_bd(int $n): string
{
    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve',
        'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    $two = function (int $x) use ($ones, $tens): string {
        if ($x < 20) {
            return $ones[$x];
        }
        return trim($tens[intdiv($x, 10)] . ' ' . $ones[$x % 10]);
    };
    $three = function (int $x) use ($ones, $two): string {
        $h = intdiv($x, 100);
        $r = $x % 100;
        return trim(($h ? $ones[$h] . ' Hundred ' : '') . ($r ? $two($r) : ''));
    };
    $parts = [];
    $crore = intdiv($n, 10000000); $n %= 10000000;
    $lakh = intdiv($n, 100000); $n %= 100000;
    $thousand = intdiv($n, 1000); $n %= 1000;
    if ($crore) { $parts[] = _num_words_bd($crore) . ' Crore'; }
    if ($lakh) { $parts[] = $two($lakh) . ' Lakh'; }
    if ($thousand) { $parts[] = $two($thousand) . ' Thousand'; }
    if ($n) { $parts[] = $three($n); }
    return trim(implode(' ', $parts));
}
