<?php
/**
 * Web routes. Every browser group uses 'csrf'; signed-in areas add 'auth'
 * and 'permission:<slug>' (slugs from the `permissions` table).
 */
declare(strict_types=1);

use App\Controllers\Api\HealthController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\LocaleController;
use App\Controllers\RoleController;
use App\Controllers\UserController;

/** @var \App\Core\Router $router */

$router->get('/health', [HealthController::class, 'index'])->name('health');

$router->group(['middleware' => ['csrf']], static function ($router): void {
    $router->get('/lang/{code:bn|en}', [LocaleController::class, 'switch'])->name('locale');

    $router->group(['middleware' => ['guest']], static function ($router): void {
        $router->get('/login', [AuthController::class, 'showLogin'])->name('login');
        $router->post('/login', [AuthController::class, 'login']);
    });

    $router->group(['middleware' => ['auth']], static function ($router): void {
        $router->post('/logout', [AuthController::class, 'logout'])->name('logout');
        $router->get('/password', [AuthController::class, 'showPassword'])->name('password');
        $router->post('/password', [AuthController::class, 'updatePassword']);

        $router->get('/', [DashboardController::class, 'index'], ['permission:dashboard.view,portal.student'])->name('dashboard');

        // Users
        $router->get('/users', [UserController::class, 'index'], ['permission:user.view'])->name('users.index');
        $router->get('/users/create', [UserController::class, 'create'], ['permission:user.create'])->name('users.create');
        $router->post('/users', [UserController::class, 'store'], ['permission:user.create']);
        $router->get('/users/{id:\d+}/edit', [UserController::class, 'edit'], ['permission:user.update'])->name('users.edit');
        $router->put('/users/{id:\d+}', [UserController::class, 'update'], ['permission:user.update']);
        $router->post('/users/{id:\d+}/status', [UserController::class, 'toggleStatus'], ['permission:user.deactivate']);
        $router->post('/users/{id:\d+}/reset-password', [UserController::class, 'resetPassword'], ['permission:user.reset_password']);

        // Roles & permissions
        $router->get('/roles', [RoleController::class, 'index'], ['permission:role.view,role.manage'])->name('roles.index');
        $router->get('/roles/{id:\d+}/edit', [RoleController::class, 'edit'], ['permission:role.manage'])->name('roles.edit');
        $router->put('/roles/{id:\d+}', [RoleController::class, 'update'], ['permission:role.manage']);
    });
});
