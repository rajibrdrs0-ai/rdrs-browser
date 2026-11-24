<?php
/**
 * Command-line installer (alternative to public/install.php)
 *
 *   php database/install_cli.php --host=127.0.0.1 --user=root --pass= --db=rtti_training_management \
 *       --admin=admin --admin-pass=Admin@12345 [--demo]
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}
require dirname(__DIR__) . '/app/Core/bootstrap.php';

$o = getopt('', ['host::', 'port::', 'user::', 'pass::', 'db::', 'admin::', 'admin-pass::', 'admin-name::', 'demo', 'force']);
if (is_file(ROOT_PATH . '/config/config.php') && !isset($o['force'])) {
    fwrite(STDERR, "config/config.php already exists. Use --force to reinstall into an EMPTY database.\n");
    exit(1);
}
$pass = $o['admin-pass'] ?? '';
if (strlen($pass) < 8) {
    fwrite(STDERR, "--admin-pass is required (min 8 characters).\n");
    exit(1);
}
try {
    $log = \App\Services\Installer::install(
        ['host' => $o['host'] ?? '127.0.0.1', 'port' => (int)($o['port'] ?? 3306), 'name' => $o['db'] ?? 'rtti_training_management', 'user' => (string)($o['user'] ?? 'root'), 'pass' => (string)($o['pass'] ?? '')],
        ['admin_user' => $o['admin'] ?? 'admin', 'admin_pass' => $pass, 'admin_name' => $o['admin-name'] ?? 'System Administrator', 'admin_email' => null,
         'ho_name' => 'RTTI Head Office', 'ho_district' => 'Rangpur', 'demo' => isset($o['demo'])]
    );
    echo implode(PHP_EOL, $log), PHP_EOL, "Installation complete.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Installation failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
