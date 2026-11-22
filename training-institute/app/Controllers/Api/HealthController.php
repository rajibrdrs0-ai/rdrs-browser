<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

/**
 * GET /health - liveness/readiness probe for load balancers and uptime
 * monitors. Reports PHP runtime, required extensions, writable storage
 * and database connectivity (without exposing credentials).
 */
final class HealthController extends Controller
{
    private const REQUIRED_EXTENSIONS = ['pdo_mysql', 'mbstring', 'json', 'openssl', 'sodium', 'fileinfo'];

    public function index(Request $request): Response
    {
        $checks = [
            'php'        => PHP_VERSION_ID >= 80200,
            'extensions' => array_values(array_filter(self::REQUIRED_EXTENSIONS, static fn(string $e): bool => !extension_loaded($e))) === [],
            'storage'    => is_writable(BASE_PATH . '/storage/logs') && is_writable(BASE_PATH . '/storage/uploads'),
            'app_key'    => strlen((string) base64_decode((string) Config::get('app.key', ''), true)) === 32,
            'database'   => $this->databaseReachable(),
        ];
        $healthy = !in_array(false, $checks, true);

        return $this->json([
            'status'  => $healthy ? 'ok' : 'degraded',
            'app'     => Config::get('app.name'),
            'version' => Config::get('app.version'),
            'time'    => date(DATE_ATOM),
            'checks'  => $checks,
        ], $healthy ? 200 : 503)->header('Cache-Control', 'no-store');
    }

    private function databaseReachable(): bool
    {
        $cfg = (array) Config::get('database', []);
        try {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $cfg['host'] ?? '127.0.0.1', (int) ($cfg['port'] ?? 3306), $cfg['database'] ?? '', $cfg['charset'] ?? 'utf8mb4');
            $pdo = new \PDO($dsn, (string) ($cfg['username'] ?? ''), (string) ($cfg['password'] ?? ''), [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 3,
            ]);
            return (int) $pdo->query("SELECT COUNT(*) FROM roles")->fetchColumn() > 0;
        } catch (\Throwable) {
            return false;
        }
    }
}
