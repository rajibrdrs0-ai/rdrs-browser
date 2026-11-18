<?php
/**
 * Application bootstrap: autoloading, environment, configuration,
 * error handling and the HTTP kernel.
 */
declare(strict_types=1);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

if (PHP_VERSION_ID < 80200) {
    http_response_code(500);
    exit('Courier Management সার্ভিস requires PHP 8.2 or newer.');
}

// Composer autoloader when available, otherwise a built-in PSR-4 loader
// so the system also runs on hosts without Composer (XAMPP / shared hosting).
$composer = BASE_PATH . '/vendor/autoload.php';
if (is_file($composer)) {
    require $composer;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'App\\';
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            return;
        }
        $relative = substr($class, strlen($prefix));
        $file = BASE_PATH . '/app/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require $file;
        }
    });
    require BASE_PATH . '/app/Helpers/functions.php';
}

\App\Core\Env::load(BASE_PATH . '/.env');
\App\Core\Config::load(BASE_PATH . '/config');

mb_internal_encoding('UTF-8');
date_default_timezone_set((string) \App\Core\Config::get('app.timezone', 'Asia/Dhaka'));

\App\Core\ErrorHandler::register(
    (bool) \App\Core\Config::get('app.debug', false),
    BASE_PATH . '/storage/logs'
);

return new \App\Core\Application(BASE_PATH);
