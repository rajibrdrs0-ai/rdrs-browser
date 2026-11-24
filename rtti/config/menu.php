<?php
/**
 * Sidebar menu. Items are shown only when the user holds the listed permission.
 */
return [
    ['label' => 'Dashboard', 'icon' => 'speedometer2', 'route' => 'dashboard/index', 'perm' => 'dashboard.view'],
    ['label' => 'Students', 'icon' => 'people', 'children' => [
        ['label' => 'Student Register', 'route' => 'students/index', 'perm' => 'students.view'],
        ['label' => 'Student Enrollment', 'route' => 'enrollments/index', 'perm' => 'enrollments.view'],
        ['label' => 'Student Courses', 'route' => 'studentcourses/index', 'perm' => 'enrollments.view'],
        ['label' => 'Documents', 'route' => 'documents/index', 'perm' => 'documents.view'],
        ['label' => 'Certificate', 'route' => 'certificates/index', 'perm' => 'certificates.view'],
    ]],
    ['label' => 'Academics', 'icon' => 'mortarboard', 'children' => [
        ['label' => 'Courses', 'route' => 'courses/index', 'perm' => 'courses.view'],
        ['label' => 'Batches', 'route' => 'batches/index', 'perm' => 'batches.view'],
        ['label' => 'Class Routine', 'route' => 'routines/index', 'perm' => 'routines.view'],
        ['label' => 'Assignments', 'route' => 'assignments/index', 'perm' => 'assignments.view'],
        ['label' => 'Attendance', 'route' => 'attendance/index', 'perm' => 'attendance.view'],
        ['label' => 'Examinations', 'route' => 'exams/index', 'perm' => 'exams.view'],
    ]],
    ['label' => 'Accounts', 'icon' => 'cash-coin', 'children' => [
        ['label' => 'Fee Collection', 'route' => 'fees/index', 'perm' => 'fees.view'],
        ['label' => 'Due List', 'route' => 'fees/dues', 'perm' => 'fees.view'],
        ['label' => 'Student Ledger', 'route' => 'fees/ledger', 'perm' => 'fees.view'],
        ['label' => 'Expenses', 'route' => 'expenses/index', 'perm' => 'expenses.view'],
    ]],
    ['label' => 'Administration', 'icon' => 'building-gear', 'children' => [
        ['label' => 'Staff Register', 'route' => 'staff/index', 'perm' => 'staff.view'],
        ['label' => 'Asset Register', 'route' => 'assets/index', 'perm' => 'assets.view'],
        ['label' => 'User Accounts', 'route' => 'users/index', 'perm' => 'users.view'],
        ['label' => 'Roles & Permissions', 'route' => 'roles/index', 'perm' => 'roles.view'],
        ['label' => 'Data Import', 'route' => 'import/index', 'perm_any' => ['students.import', 'staff.import', 'courses.import', 'batches.import', 'assets.import']],
        ['label' => 'Audit Log', 'route' => 'audit/index', 'perm' => 'audit.view'],
        ['label' => 'System Settings', 'route' => 'settings/index', 'perm' => 'settings.view'],
    ]],
    ['label' => 'Master Entry', 'icon' => 'database-gear', 'children' => [
        ['label' => 'Academic', 'route' => 'master/index', 'params' => ['g' => 'academic'], 'perm' => 'master.view'],
        ['label' => 'Location', 'route' => 'master/index', 'params' => ['g' => 'location'], 'perm' => 'master.view'],
        ['label' => 'Gender', 'route' => 'master/index', 'params' => ['g' => 'gender'], 'perm' => 'master.view'],
        ['label' => 'Trade', 'route' => 'master/index', 'params' => ['g' => 'trade'], 'perm' => 'master.view'],
        ['label' => 'Course Status', 'route' => 'master/index', 'params' => ['g' => 'course_status'], 'perm' => 'master.view'],
        ['label' => 'Batch Status', 'route' => 'master/index', 'params' => ['g' => 'batch_status'], 'perm' => 'master.view'],
        ['label' => 'Center', 'route' => 'master/index', 'params' => ['g' => 'center'], 'perm' => 'centers.view'],
        ['label' => 'Accounts', 'route' => 'master/index', 'params' => ['g' => 'accounts'], 'perm' => 'master.view'],
        ['label' => 'Assets', 'route' => 'master/index', 'params' => ['g' => 'assets'], 'perm' => 'master.view'],
    ]],
    ['label' => 'Reports', 'icon' => 'bar-chart-line', 'route' => 'reports/index', 'perm' => 'reports.view'],
];
