<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

/**
 * GET / - installation status page shown until the dashboard (Phase 4)
 * replaces it. Read-only: environment checks plus seed-data counts.
 */
final class HomeController extends Controller
{
    private const COUNTED_TABLES = [
        'branches'          => ['Branches & hubs', 'শাখা ও হাব'],
        'districts'         => ['Districts', 'জেলা'],
        'thanas'            => ['Thanas / areas', 'থানা / এলাকা'],
        'roles'             => ['Roles', 'রোল'],
        'permissions'       => ['Permissions', 'পারমিশন'],
        'chart_of_accounts' => ['Ledger accounts', 'লেজার অ্যাকাউন্ট'],
        'rate_card_items'   => ['Tariff lines', 'ট্যারিফ লাইন'],
        'parcel_statuses'   => ['Parcel statuses', 'পার্সেল স্ট্যাটাস'],
        'users'             => ['Users', 'ব্যবহারকারী'],
        'parcels'           => ['Parcels', 'পার্সেল'],
    ];

    public function index(Request $request): Response
    {
        $counts = [];
        $dbError = null;
        $tableCount = 0;
        $mysqlVersion = null;

        try {
            $pdo = $this->connect();
            $mysqlVersion = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
            $schema = (string) Config::get('database.database');
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = :s AND table_type = 'BASE TABLE'");
            $stmt->execute(['s' => $schema]);
            $tableCount = (int) $stmt->fetchColumn();
            foreach (self::COUNTED_TABLES as $table => $labels) {
                // Table names come from the constant above, never from input.
                $counts[$table] = [
                    'label' => $labels,
                    'count' => (int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn(),
                ];
            }
        } catch (\Throwable $e) {
            $dbError = Config::get('app.debug') ? $e->getMessage() : 'Database connection failed.';
        }

        return $this->view('home', [
            'appName'      => (string) Config::get('app.name'),
            'version'      => (string) Config::get('app.version'),
            'env'          => (string) Config::get('app.env'),
            'phpVersion'   => PHP_VERSION,
            'mysqlVersion' => $mysqlVersion,
            'dbError'      => $dbError,
            'tableCount'   => $tableCount,
            'counts'       => $counts,
        ]);
    }

    private function connect(): \PDO
    {
        $cfg = (array) Config::get('database', []);
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'], (int) $cfg['port'], $cfg['database'], $cfg['charset']);
        return new \PDO($dsn, (string) $cfg['username'], (string) $cfg['password'],
            (array) $cfg['options'] + [\PDO::ATTR_TIMEOUT => 3]);
    }
}
