<?php
/**
 * Web routes. Every browser group uses 'csrf'; signed-in areas add 'auth'
 * and 'permission:<slug>' (slugs from the `permissions` table).
 */
declare(strict_types=1);

use App\Controllers\Api\HealthController;
use App\Controllers\AuthController;
use App\Controllers\BatchController;
use App\Controllers\CenterController;
use App\Controllers\CourseController;
use App\Controllers\GeoController;
use App\Controllers\StaffController;
use App\Controllers\TradeController;
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

        // Geography
        $router->get('/geo/upazila-options', [GeoController::class, 'upazilasJson']);
        $router->get('/geo/upazilas', [GeoController::class, 'upazilas'], ['permission:geo.manage']);
        $router->post('/geo/upazilas', [GeoController::class, 'storeUpazila'], ['permission:geo.manage']);

        // Training centers & rooms
        $router->get('/centers', [CenterController::class, 'index'], ['permission:center.view']);
        $router->get('/centers/create', [CenterController::class, 'create'], ['permission:center.manage']);
        $router->post('/centers', [CenterController::class, 'store'], ['permission:center.manage']);
        $router->get('/centers/{id:\d+}', [CenterController::class, 'show'], ['permission:center.view']);
        $router->get('/centers/{id:\d+}/edit', [CenterController::class, 'edit'], ['permission:center.manage']);
        $router->put('/centers/{id:\d+}', [CenterController::class, 'update'], ['permission:center.manage']);
        $router->post('/centers/{id:\d+}/rooms', [CenterController::class, 'storeRoom'], ['permission:center.manage,routine.manage']);
        $router->post('/centers/{id:\d+}/rooms/{roomId:\d+}/toggle', [CenterController::class, 'toggleRoom'], ['permission:center.manage,routine.manage']);

        // Trades & courses
        $router->get('/trades', [TradeController::class, 'index'], ['permission:course.view']);
        $router->post('/trades', [TradeController::class, 'store'], ['permission:course.manage']);
        $router->put('/trades/{id:\d+}', [TradeController::class, 'update'], ['permission:course.manage']);
        $router->get('/courses', [CourseController::class, 'index'], ['permission:course.view']);
        $router->get('/courses/create', [CourseController::class, 'create'], ['permission:course.manage']);
        $router->post('/courses', [CourseController::class, 'store'], ['permission:course.manage']);
        $router->get('/courses/{id:\d+}', [CourseController::class, 'show'], ['permission:course.view']);
        $router->get('/courses/{id:\d+}/edit', [CourseController::class, 'edit'], ['permission:course.manage']);
        $router->put('/courses/{id:\d+}', [CourseController::class, 'update'], ['permission:course.manage']);
        $router->post('/courses/{id:\d+}/modules', [CourseController::class, 'storeModule'], ['permission:course.manage']);
        $router->delete('/courses/{id:\d+}/modules/{moduleId:\d+}', [CourseController::class, 'deleteModule'], ['permission:course.manage']);
        $router->post('/courses/{id:\d+}/fees', [CourseController::class, 'saveFees'], ['permission:fee_structure.manage']);

        // Staff & trainers
        $router->get('/staff', [StaffController::class, 'index'], ['permission:staff.view']);
        $router->get('/staff/create', [StaffController::class, 'create'], ['permission:staff.manage']);
        $router->post('/staff', [StaffController::class, 'store'], ['permission:staff.manage']);
        $router->get('/staff/{id:\d+}/edit', [StaffController::class, 'edit'], ['permission:staff.manage']);
        $router->put('/staff/{id:\d+}', [StaffController::class, 'update'], ['permission:staff.manage']);

        // Batches & routine
        $router->get('/batches', [BatchController::class, 'index'], ['permission:batch.view']);
        $router->get('/batches/create', [BatchController::class, 'create'], ['permission:batch.manage']);
        $router->post('/batches', [BatchController::class, 'store'], ['permission:batch.manage']);
        $router->get('/batches/{id:\d+}', [BatchController::class, 'show'], ['permission:batch.view']);
        $router->get('/batches/{id:\d+}/edit', [BatchController::class, 'edit'], ['permission:batch.manage']);
        $router->put('/batches/{id:\d+}', [BatchController::class, 'update'], ['permission:batch.manage']);
        $router->post('/batches/{id:\d+}/status', [BatchController::class, 'changeStatus'], ['permission:batch.manage']);
        $router->post('/batches/{id:\d+}/routine', [BatchController::class, 'storeRoutine'], ['permission:routine.manage']);
        $router->delete('/batches/{id:\d+}/routine/{slotId:\d+}', [BatchController::class, 'deleteRoutine'], ['permission:routine.manage']);
        $router->get('/routine', [BatchController::class, 'timetable'], ['permission:batch.view']);
    });
});
