<?php
/**
 * Application bootstrap: config, autoloader, error handling, session.
 */
declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
define('APP_PATH', ROOT_PATH . '/app');
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('PUBLIC_PATH', ROOT_PATH . '/public');

spl_autoload_register(function (string $class): void {
    if (strncmp($class, 'App\\', 4) !== 0) {
        return;
    }
    $file = APP_PATH . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require APP_PATH . '/Core/helpers.php';

$configFile = ROOT_PATH . '/config/config.php';
$GLOBALS['__config'] = is_file($configFile) ? require $configFile : null;

date_default_timezone_set($GLOBALS['__config']['timezone'] ?? 'Asia/Dhaka');

$debug = (bool)($GLOBALS['__config']['debug'] ?? false);
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', STORAGE_PATH . '/logs/php-error.log');

set_exception_handler(function (Throwable $e) use ($debug): void {
    error_log('[' . date('Y-m-d H:i:s') . '] ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
    if (!headers_sent()) {
        http_response_code(500);
    }
    if (is_ajax()) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'message' => $debug ? $e->getMessage() : 'Server error. The problem has been logged.']);
        return;
    }
    echo '<!doctype html><meta charset="utf-8"><title>Error</title><div style="font-family:sans-serif;max-width:720px;margin:60px auto">'
        . '<h2>Something went wrong</h2><p>The error has been logged. Please contact the system administrator.</p>'
        . ($debug ? '<pre style="white-space:pre-wrap;background:#f6f6f6;padding:12px">' . e($e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString()) . '</pre>' : '')
        . '<p><a href="index.php">Back to dashboard</a></p></div>';
});

if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name('RTTISESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}
