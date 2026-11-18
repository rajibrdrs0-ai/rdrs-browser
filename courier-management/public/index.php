<?php
/**
 * Courier Management সার্ভিস - Front Controller
 * Every HTTP request enters the application here.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('APP_START', microtime(true));

/** @var \App\Core\Application $app */
$app = require BASE_PATH . '/bootstrap/app.php';

$app->handle(\App\Core\Request::capture())->send();
