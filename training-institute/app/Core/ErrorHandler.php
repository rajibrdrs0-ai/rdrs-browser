<?php
declare(strict_types=1);

namespace App\Core;

use ErrorException;
use Throwable;

/**
 * Converts PHP errors to exceptions, logs every unhandled throwable to
 * storage/logs/app-YYYY-MM-DD.log and renders a safe error page.
 * Stack traces are shown only when APP_DEBUG=true.
 */
final class ErrorHandler
{
    private static bool $debug = false;
    private static string $logDir = '';

    public static function register(bool $debug, string $logDir): void
    {
        self::$debug = $debug;
        self::$logDir = rtrim($logDir, '/');

        error_reporting(E_ALL);
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(static function (Throwable $e): void {
            self::report($e);
            $wantsJson = str_starts_with((string) ($_SERVER['REQUEST_URI'] ?? ''), '/api/')
                || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
            self::render($e, $wantsJson)->send();
        });

        register_shutdown_function(static function (): void {
            $error = error_get_last();
            if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                self::report(new ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']));
                if (!headers_sent()) {
                    self::render(new \RuntimeException('Fatal error'), false)->send();
                }
            }
        });
    }

    public static function isDebug(): bool
    {
        return self::$debug;
    }

    public static function report(Throwable $e, array $context = []): void
    {
        if ($e instanceof HttpException && $e->getStatusCode() < 500) {
            return; // 4xx are expected flow, not errors
        }
        $dir = self::$logDir !== '' ? self::$logDir : BASE_PATH . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $entry = sprintf(
            "[%s] %s: %s in %s:%d\nURI: %s %s | IP: %s\nContext: %s\n%s\n\n",
            date('Y-m-d H:i:s'),
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            (string) ($_SERVER['REQUEST_METHOD'] ?? 'CLI'),
            (string) ($_SERVER['REQUEST_URI'] ?? ''),
            (string) ($_SERVER['REMOTE_ADDR'] ?? '-'),
            json_encode($context, JSON_UNESCAPED_UNICODE) ?: '{}',
            $e->getTraceAsString()
        );
        @file_put_contents($dir . '/app-' . date('Y-m-d') . '.log', $entry, FILE_APPEND | LOCK_EX);
    }

    public static function render(Throwable $e, bool $wantsJson): Response
    {
        $status = $e instanceof HttpException ? $e->getStatusCode() : 500;
        $title = Response::STATUS_TEXTS[$status] ?? 'Error';
        $message = $e instanceof HttpException ? $e->getMessage() : 'Something went wrong. Please try again later.';
        if (self::$debug && !($e instanceof HttpException)) {
            $message = $e::class . ': ' . $e->getMessage();
        }

        if ($wantsJson) {
            $payload = ['success' => false, 'message' => $message];
            if ($e instanceof ValidationException) {
                $payload['errors'] = $e->errors();
            }
            if (self::$debug && !($e instanceof HttpException)) {
                $payload['trace'] = explode("\n", $e->getTraceAsString());
            }
            $response = Response::json($payload, $status);
        } else {
            $view = BASE_PATH . '/app/Views/errors/' . $status . '.php';
            $generic = BASE_PATH . '/app/Views/errors/error.php';
            $trace = self::$debug && !($e instanceof HttpException) ? $e->getTraceAsString() : null;
            ob_start();
            (static function (string $__f, int $status, string $title, string $message, ?string $trace): void {
                require $__f;
            })(is_file($view) ? $view : $generic, $status, $title, $message, $trace);
            $response = Response::html((string) ob_get_clean(), $status);
        }

        if ($e instanceof HttpException) {
            foreach ($e->getHeaders() as $name => $value) {
                $response->header($name, $value);
            }
        }
        return $response;
    }
}
