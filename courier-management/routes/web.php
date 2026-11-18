<?php
/**
 * Web routes. $router is an instance of App\Core\Router.
 *
 * Conventions used by later phases:
 *   - every browser route group carries 'csrf'
 *   - authenticated groups add 'auth' and 'permission:<slug>' where <slug>
 *     matches permissions.slug in the database (see database/schema.sql)
 *
 * Module routes (auth, booking, manifests, DRS, accounting, merchant portal)
 * are appended to this file in Phases 3 & 4 together with their controllers.
 */
declare(strict_types=1);

use App\Controllers\Api\HealthController;
use App\Controllers\HomeController;

/** @var \App\Core\Router $router */

$router->get('/', [HomeController::class, 'index'])->name('home');
$router->get('/health', [HealthController::class, 'index'])->name('health');
