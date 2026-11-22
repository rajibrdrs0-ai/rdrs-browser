<?php
/**
 * RTTI Management System — command-line installer.
 *
 *   php bin/install.php                 (interactive, asks every question)
 *   php bin/install.php --yes --db-pass=secret --admin-pass='MyStrong@Pass1'
 *
 * Works the same on Windows (XAMPP/Laragon: C:\xampp\php\php.exe bin\install.php)
 * and Linux. It does not need the mysql command-line client.
 *
 * Steps: checks PHP → connects to MySQL → creates the database from
 * database/schema.sql → (optional) demo data → creates a least-privilege
 * application DB user → writes .env with a fresh APP_KEY → sets the
 * Super Admin password.
 *
 * Options (all optional):
 *   --db-host=127.0.0.1 --db-port=3306 --db-user=root --db-pass=
 *   --db-name=rtti_management --app-db-user=rtti_user --app-db-pass=<generated>
 *   --app-url=http://localhost/training-institute/public
 *   --admin-pass=<set Super Admin password>  --demo  --force  --yes
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

const ROOT = __DIR__ . '/..';
date_default_timezone_set('Asia/Dhaka');
$opts = getopt('', ['db-host:', 'db-port:', 'db-user:', 'db-pass:', 'db-name:', 'app-db-user:', 'app-db-pass:',
                    'app-url:', 'admin-pass:', 'demo', 'force', 'yes', 'help']);
if (isset($opts['help'])) {
    preg_match('#/\*\*(.*?)\*/#s', (string) file_get_contents(__FILE__), $m);
    echo preg_replace('/^\s*\* ?/m', '', $m[1] ?? ''), PHP_EOL;
    exit(0);
}
$auto = isset($opts['yes']);

// ---------------------------------------------------------------- helpers
function out(string $msg, string $type = 'info'): void
{
    $prefix = ['ok' => '[ OK ] ', 'warn' => '[WARN] ', 'fail' => '[FAIL] ', 'info' => '       ', 'step' => "\n==> "][$type] ?? '';
    fwrite($type === 'fail' ? STDERR : STDOUT, $prefix . $msg . PHP_EOL);
}

function fail(string $msg): never
{
    out($msg, 'fail');
    exit(1);
}

function ask(string $question, string $default, bool $auto, bool $secret = false): string
{
    if ($auto) {
        return $default;
    }
    $shown = $secret ? ($default !== '' ? '(hidden, Enter = keep)' : '') : $default;
    fwrite(STDOUT, '  ' . $question . ($shown !== '' ? " [{$shown}]" : '') . ': ');
    $hide = $secret && DIRECTORY_SEPARATOR === '/' && stream_isatty(STDIN);
    if ($hide) {
        shell_exec('stty -echo');
    }
    $line = fgets(STDIN);
    if ($hide) {
        shell_exec('stty echo');
        fwrite(STDOUT, PHP_EOL);
    }
    $line = $line === false ? '' : trim($line);
    return $line === '' ? $default : $line;
}

function confirm(string $question, bool $default, bool $auto): bool
{
    if ($auto) {
        return $default;
    }
    $answer = strtolower(ask($question . ($default ? ' (Y/n)' : ' (y/N)'), '', false));
    return $answer === '' ? $default : in_array($answer, ['y', 'yes', 'h', 'হ্যাঁ'], true);
}

function randomSecret(int $bytes = 18): string
{
    return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', 'Ab'), '=');
}

/**
 * Split a .sql dump into statements, honouring DELIMITER changes used by
 * the trigger section (PDO cannot execute "DELIMITER").
 * @return list<string>
 */
function splitSql(string $sql): array
{
    $statements = [];
    $delimiter = ';';
    $buffer = '';
    // Split on "\n" only: '/\R/' without the u-flag also matches byte 0x85,
    // which occurs inside UTF-8 Bangla characters and would corrupt them.
    foreach (explode("\n", str_replace("\r\n", "\n", $sql)) as $line) {
        $trimmed = trim($line);
        if (preg_match('/^DELIMITER\s+(\S+)$/i', $trimmed, $m)) {
            $delimiter = $m[1];
            continue;
        }
        if ($buffer === '' && ($trimmed === '' || str_starts_with($trimmed, '--'))) {
            continue;
        }
        $buffer .= $line . "\n";
        if (str_ends_with(rtrim($line), $delimiter)) {
            $stmt = trim(substr(rtrim($buffer), 0, -strlen($delimiter)));
            if ($stmt !== '') {
                $statements[] = $stmt;
            }
            $buffer = '';
        }
    }
    if (trim($buffer) !== '') {
        $statements[] = trim($buffer);
    }
    return $statements;
}

function runSqlFile(PDO $pdo, string $file, string $dbName): int
{
    $sql = (string) file_get_contents($file);
    // The dump targets `rtti_management`; retarget it if another name was chosen.
    $sql = str_replace('`rtti_management`', '`' . $dbName . '`', $sql);
    $count = 0;
    foreach (splitSql($sql) as $i => $statement) {
        try {
            $pdo->exec($statement);
            $count++;
        } catch (PDOException $e) {
            fail(basename($file) . ' statement #' . ($i + 1) . ' failed: ' . $e->getMessage()
                . "\n       " . mb_substr(preg_replace('/\s+/', ' ', $statement) ?? '', 0, 160) . ' …');
        }
    }
    return $count;
}

// ---------------------------------------------------------------- 1. PHP
out('RTTI Management System — installer', 'step');
out('Checking PHP', 'step');
if (PHP_VERSION_ID < 80200) {
    fail('PHP 8.2 or newer is required (found ' . PHP_VERSION . ').');
}
out('PHP ' . PHP_VERSION, 'ok');
$missing = array_filter(['pdo_mysql', 'mbstring', 'openssl', 'sodium', 'fileinfo', 'json'], static fn(string $e): bool => !extension_loaded($e));
if ($missing !== []) {
    fail('Missing PHP extensions: ' . implode(', ', $missing) . '. Enable them in php.ini (XAMPP: remove the ";" before extension=...).');
}
out('Extensions: pdo_mysql, mbstring, openssl, sodium, fileinfo, json', 'ok');
foreach (['storage/logs', 'storage/cache', 'storage/sessions', 'storage/uploads'] as $dir) {
    $path = ROOT . '/' . $dir;
    if (!is_dir($path)) {
        @mkdir($path, 0775, true);
    }
    if (!is_writable($path)) {
        fail("Folder {$dir} is not writable by PHP. Linux: sudo chown -R www-data:www-data storage && sudo chmod -R 775 storage");
    }
}
out('storage/ folders are writable', 'ok');

// ---------------------------------------------------------------- 2. MySQL
out('Database server (an account that can create databases and users, e.g. root)', 'step');
$host = ask('MySQL host', (string) ($opts['db-host'] ?? '127.0.0.1'), $auto);
$port = (int) ask('MySQL port', (string) ($opts['db-port'] ?? '3306'), $auto);
$adminUser = ask('MySQL admin user', (string) ($opts['db-user'] ?? 'root'), $auto);
$adminPass = ask('MySQL admin password (XAMPP default is empty)', (string) ($opts['db-pass'] ?? ''), $auto, true);
$dbName = ask('Database name', (string) ($opts['db-name'] ?? 'rtti_management'), $auto);
if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $dbName)) {
    fail('Database name may only contain letters, digits and underscore.');
}

try {
    $dsn = $host === 'localhost' ? "mysql:host=localhost;charset=utf8mb4" : "mysql:host={$host};port={$port};charset=utf8mb4";
    $pdo = new PDO($dsn, $adminUser, $adminPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => true]);
} catch (PDOException $e) {
    fail('Cannot connect to MySQL: ' . $e->getMessage() . "\n       Is MySQL running (XAMPP/Laragon control panel → Start MySQL)?");
}
$version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
$isMaria = stripos($version, 'mariadb') !== false;
out("Connected: {$version}", 'ok');
if ($isMaria) {
    out('MariaDB detected. The system is built and tested for MySQL 8.0+; MariaDB 10.6+ usually works but is not tested. Laragon (with MySQL 8) is recommended on Windows.', 'warn');
} elseif (version_compare(preg_replace('/[^0-9.].*$/', '', $version) ?: '0', '8.0.16', '<')) {
    fail('MySQL 8.0.16 or newer is required (CHECK constraints). Found ' . $version);
}

$exists = (bool) $pdo->query('SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = ' . $pdo->quote($dbName))->fetchColumn();
if ($exists) {
    $hasData = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ' . $pdo->quote($dbName))->fetchColumn() > 0;
    if ($hasData) {
        out("Database `{$dbName}` already exists and contains tables.", 'warn');
        if (!isset($opts['force']) && !confirm('ERASE it completely and reinstall? All its data will be lost', false, false)) {
            fail('Stopped. Nothing was changed. (Use another --db-name, or --force to overwrite.)');
        }
        $pdo->exec("DROP DATABASE `{$dbName}`");
        out("Old database `{$dbName}` dropped", 'ok');
    }
}

// ---------------------------------------------------------------- 3. schema + data
out('Creating tables, triggers, views and base data', 'step');
$n = runSqlFile($pdo, ROOT . '/database/schema.sql', $dbName);
out("schema.sql: {$n} statements executed", 'ok');

$demo = isset($opts['demo']) || (!$auto && confirm('Load DEMO data (sample centers, courses and one user per role — not for real use)?', false, false));
if ($demo) {
    $n = runSqlFile($pdo, ROOT . '/database/demo_data.sql', $dbName);
    out("demo_data.sql: {$n} statements executed (demo users' password: Demo@12345)", 'ok');
}

// ---------------------------------------------------------------- 4. app DB user
out('Application database account (least privilege: no DROP/ALTER)', 'step');
$appUser = ask('App DB user name', (string) ($opts['app-db-user'] ?? 'rtti_user'), $auto);
$appPass = (string) ($opts['app-db-pass'] ?? randomSecret());
if (!preg_match('/^[A-Za-z0-9_]{1,32}$/', $appUser)) {
    fail('App DB user may only contain letters, digits and underscore.');
}
$appHosts = in_array($host, ['localhost', '127.0.0.1', '::1'], true) ? ['localhost', '127.0.0.1'] : ['%'];
try {
    foreach ($appHosts as $h) {
        $account = $pdo->quote($appUser) . '@' . $pdo->quote($h);
        $pdo->exec("DROP USER IF EXISTS {$account}");
        $pdo->exec("CREATE USER {$account} IDENTIFIED BY " . $pdo->quote($appPass));
        $pdo->exec("GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON `{$dbName}`.* TO {$account}");
    }
    $pdo->exec('FLUSH PRIVILEGES');
    out("User {$appUser}@(" . implode(', ', $appHosts) . ") created with a random password (saved in .env)", 'ok');
} catch (PDOException $e) {
    out('Could not create the app DB user (' . $e->getMessage() . '). Falling back to the admin account in .env — create a restricted user later for production.', 'warn');
    $appUser = $adminUser;
    $appPass = $adminPass;
}

// ---------------------------------------------------------------- 5. .env
out('Writing configuration (.env)', 'step');
$appUrl = ask('Address users will open in the browser', (string) ($opts['app-url'] ?? 'http://localhost/training-institute/public'), $auto);
$envFile = ROOT . '/.env';
if (is_file($envFile)) {
    $backup = $envFile . '.backup-' . date('Ymd-His');
    copy($envFile, $backup);
    out('Existing .env backed up to ' . basename($backup), 'ok');
}
$template = (string) file_get_contents(ROOT . '/.env.example');
$values = [
    'APP_ENV'     => 'production',
    'APP_DEBUG'   => 'false',
    'APP_URL'     => $appUrl,
    'APP_KEY'     => base64_encode(random_bytes(32)),
    'DB_HOST'     => $host,
    'DB_PORT'     => (string) $port,
    'DB_DATABASE' => $dbName,
    'DB_USERNAME' => $appUser,
    'DB_PASSWORD' => '"' . addcslashes($appPass, '"\\$') . '"',
    'SESSION_SECURE_COOKIE' => str_starts_with($appUrl, 'https://') ? 'true' : 'auto',
];
foreach ($values as $key => $value) {
    $template = (string) preg_replace('/^' . $key . '=.*$/m', $key . '=' . str_replace('$', '\\$', $value), $template);
}
file_put_contents($envFile, $template, LOCK_EX);
@chmod($envFile, 0600);
out('.env written (APP_KEY generated, debug off)', 'ok');

// ---------------------------------------------------------------- 6. Super Admin
out('Super Admin account (username: superadmin)', 'step');
$adminPassword = (string) ($opts['admin-pass'] ?? '');
if ($adminPassword === '' && !$auto) {
    $adminPassword = ask('New Super Admin password (Enter = keep Admin@12345 and force a change at first login)', '', false, true);
}
$pdo->exec("USE `{$dbName}`");
if ($adminPassword !== '') {
    $weak = strlen($adminPassword) < 8 || !preg_match('/[A-Z]/', $adminPassword) || !preg_match('/[a-z]/', $adminPassword) || !preg_match('/\d/', $adminPassword);
    if ($weak) {
        out('Password too weak (min 8 chars, upper, lower, digit). Keeping Admin@12345 with forced change.', 'warn');
    } else {
        $hash = defined('PASSWORD_ARGON2ID') ? password_hash($adminPassword, PASSWORD_ARGON2ID) : password_hash($adminPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, must_change_password = 0, password_changed_at = NOW() WHERE username = 'superadmin'");
        $stmt->execute([$hash]);
        out('Super Admin password set', 'ok');
    }
} else {
    out('Super Admin password is Admin@12345 — it must be changed at first login', 'warn');
}

// ---------------------------------------------------------------- 7. verify
out('Verifying with the application account', 'step');
try {
    $dsn = $host === 'localhost' ? "mysql:host=localhost;dbname={$dbName};charset=utf8mb4" : "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
    $check = new PDO($dsn, $appUser, $appPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $roles = (int) $check->query('SELECT COUNT(*) FROM roles')->fetchColumn();
    $tables = (int) $check->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'")->fetchColumn();
    out("App account can read the database: {$tables} tables, {$roles} roles", 'ok');
} catch (PDOException $e) {
    fail('The application account cannot connect: ' . $e->getMessage());
}

out('Installation complete', 'step');
out('Open:     ' . rtrim($appUrl, '/') . '/login');
out('Login:    superadmin' . ($adminPassword === '' ? '  /  Admin@12345 (change at first login)' : ''));
out('Health:   ' . rtrim($appUrl, '/') . '/health');
out('Quick test without Apache:  php -S 127.0.0.1:8100 -t public public/index.php   →  http://127.0.0.1:8100');
