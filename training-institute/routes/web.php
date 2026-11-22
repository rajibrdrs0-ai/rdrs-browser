<?php
/**
 * Web routes. Every browser group uses 'csrf'; signed-in areas add 'auth'
 * and 'permission:<slug>' (slugs from the `permissions` table).
 */
declare(strict_types=1);

use App\Controllers\Api\HealthController;
use App\Controllers\AttendanceController;
use App\Controllers\AuthController;
use App\Controllers\EnrollmentController;
use App\Controllers\ExpenseController;
use App\Controllers\FeeController;
use App\Controllers\FinanceController;
use App\Controllers\RefundController;
use App\Controllers\WaiverController;
use App\Controllers\StudentController;
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

        // Trainees
        $router->get('/students', [StudentController::class, 'index'], ['permission:student.view']);
        $router->get('/students/create', [StudentController::class, 'create'], ['permission:student.create']);
        $router->post('/students', [StudentController::class, 'store'], ['permission:student.create']);
        $router->get('/students/{id:\d+}', [StudentController::class, 'show'], ['permission:student.view']);
        $router->get('/students/{id:\d+}/edit', [StudentController::class, 'edit'], ['permission:student.update']);
        $router->put('/students/{id:\d+}', [StudentController::class, 'update'], ['permission:student.update']);
        $router->get('/students/{id:\d+}/photo', [StudentController::class, 'photo'], ['permission:student.view']);
        $router->post('/students/{id:\d+}/documents', [StudentController::class, 'storeDocument'], ['permission:student.documents']);
        $router->get('/students/{id:\d+}/documents/{docId:\d+}', [StudentController::class, 'document'], ['permission:student.documents']);
        $router->delete('/students/{id:\d+}/documents/{docId:\d+}', [StudentController::class, 'deleteDocument'], ['permission:student.documents']);
        $router->post('/students/{id:\d+}/educations', [StudentController::class, 'storeEducation'], ['permission:student.update']);
        $router->delete('/students/{id:\d+}/educations/{eduId:\d+}', [StudentController::class, 'deleteEducation'], ['permission:student.update']);

        // Admission / enrolment
        $router->get('/enrollments/create', [EnrollmentController::class, 'create'], ['permission:enrollment.create']);
        $router->post('/enrollments', [EnrollmentController::class, 'store'], ['permission:enrollment.create']);
        $router->get('/enrollments/{id:\d+}', [EnrollmentController::class, 'show'], ['permission:enrollment.view']);
        $router->post('/enrollments/{id:\d+}/status', [EnrollmentController::class, 'changeStatus'], ['permission:enrollment.update_status']);
        $router->post('/enrollments/{id:\d+}/transfer', [EnrollmentController::class, 'transfer'], ['permission:enrollment.transfer']);

        // Attendance
        $router->get('/attendance', [AttendanceController::class, 'index'], ['permission:attendance.view']);
        $router->get('/batches/{id:\d+}/attendance', [AttendanceController::class, 'register'], ['permission:attendance.view']);
        $router->get('/batches/{id:\d+}/attendance/take', [AttendanceController::class, 'take'], ['permission:attendance.take']);
        $router->post('/batches/{id:\d+}/attendance', [AttendanceController::class, 'save'], ['permission:attendance.take']);
        $router->post('/class-sessions/{sessionId:\d+}/lock', [AttendanceController::class, 'lock'], ['permission:attendance.lock']);

        // Fees & money receipts
        $router->get('/fees', [FeeController::class, 'index'], ['permission:fee.view']);
        $router->get('/fees/dues', [FeeController::class, 'dues'], ['permission:fee.view']);
        $router->get('/fees/collect/{enrollmentId:\d+}', [FeeController::class, 'collectForm'], ['permission:fee.collect']);
        $router->post('/fees/collect/{enrollmentId:\d+}', [FeeController::class, 'collect'], ['permission:fee.collect']);
        $router->get('/fees/receipts', [FeeController::class, 'receipts'], ['permission:fee.view']);
        $router->get('/fees/receipts/{id:\d+}', [FeeController::class, 'receipt'], ['permission:fee.view']);
        $router->post('/fees/receipts/{id:\d+}/cancel', [FeeController::class, 'cancelReceipt'], ['permission:fee.cancel_receipt']);

        // Waivers & refunds
        $router->get('/waivers', [WaiverController::class, 'index'], ['permission:waiver.request,waiver.approve,refund.request,refund.approve']);
        $router->post('/enrollments/{enrollmentId:\d+}/waivers', [WaiverController::class, 'store'], ['permission:waiver.request']);
        $router->post('/waivers/{id:\d+}/decide', [WaiverController::class, 'decide'], ['permission:waiver.approve']);
        $router->post('/enrollments/{enrollmentId:\d+}/refunds', [RefundController::class, 'store'], ['permission:refund.request']);
        $router->post('/refunds/{id:\d+}/decide', [RefundController::class, 'decide'], ['permission:refund.approve']);
        $router->post('/refunds/{id:\d+}/pay', [RefundController::class, 'pay'], ['permission:fee.collect']);

        // Expenses
        $router->get('/expenses', [ExpenseController::class, 'index'], ['permission:expense.view']);
        $router->get('/expenses/create', [ExpenseController::class, 'create'], ['permission:expense.create']);
        $router->post('/expenses', [ExpenseController::class, 'store'], ['permission:expense.create']);
        $router->post('/expenses/{id:\d+}/decide', [ExpenseController::class, 'decide'], ['permission:expense.approve']);
        $router->get('/expenses/{id:\d+}/attachment', [ExpenseController::class, 'attachment'], ['permission:expense.view']);

        // Accounts, transfers, cash book
        $router->get('/finance/accounts', [FinanceController::class, 'accounts'], ['permission:finance.cashbook,finance.accounts_manage']);
        $router->post('/finance/accounts', [FinanceController::class, 'storeAccount'], ['permission:finance.accounts_manage']);
        $router->get('/finance/cashbook', [FinanceController::class, 'cashbook'], ['permission:finance.cashbook']);
        $router->get('/finance/transfers', [FinanceController::class, 'transfers'], ['permission:finance.transfer']);
        $router->post('/finance/transfers', [FinanceController::class, 'storeTransfer'], ['permission:finance.transfer']);
        $router->post('/finance/transfers/{id:\d+}/decide', [FinanceController::class, 'decideTransfer'], ['permission:finance.transfer']);
    });
});
