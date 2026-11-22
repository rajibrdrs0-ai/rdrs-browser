<?php
/**
 * Courier Management সার্ভিস - Front Controller
 * Every HTTP request enters the application here.
 */
declare(strict_types=1);

// PHP built-in dev server: let it serve real files (css/js/fonts) directly.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if ($file !== __DIR__ . '/' && is_file($file) && !str_ends_with($file, '.php')) {
        return false;
    }
}

define('BASE_PATH', dirname(__DIR__));
define('APP_START', microtime(true));

/** @var \App\Core\Application $app */
$app = require BASE_PATH . '/bootstrap/app.php';

$app->handle(\App\Core\Request::capture())->send();
