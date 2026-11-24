<?php
/**
 * Permission catalogue (module => [action => label]) and default role matrix.
 * The installer / "Sync permissions" button writes these to the database.
 * Role permissions can then be changed from Administration > User Accounts > Roles.
 */
return [
    'modules' => [
        'dashboard'   => ['label' => 'Dashboard',          'actions' => ['view']],
        'students'    => ['label' => 'Student Register',   'actions' => ['view','create','edit','delete','export','import','print']],
        'enrollments' => ['label' => 'Student Enrollment', 'actions' => ['view','create','edit','approve','cancel','transfer','export']],
        'documents'   => ['label' => 'Student Documents',  'actions' => ['view','create','verify','delete']],
        'certificates'=> ['label' => 'Certificates',       'actions' => ['view','create','print','cancel']],
        'courses'     => ['label' => 'Courses',            'actions' => ['view','create','edit','delete','export','import']],
        'batches'     => ['label' => 'Batches',            'actions' => ['view','create','edit','delete','complete','export','import']],
        'routines'    => ['label' => 'Class Routine',      'actions' => ['view','manage']],
        'assignments' => ['label' => 'Assignments',        'actions' => ['view','create','edit','delete','evaluate']],
        'attendance'  => ['label' => 'Attendance',         'actions' => ['view','take','staff']],
        'exams'       => ['label' => 'Examinations',       'actions' => ['view','create','marks','publish']],
        'fees'        => ['label' => 'Fee Collection',     'actions' => ['view','collect','reverse','export']],
        'expenses'    => ['label' => 'Expenses',           'actions' => ['view','create','approve','cancel','export']],
        'staff'       => ['label' => 'Staff Register',     'actions' => ['view','create','edit','delete','export','import']],
        'assets'      => ['label' => 'Asset Register',     'actions' => ['view','create','edit','transfer','maintenance','dispose','export','import']],
        'users'       => ['label' => 'User Accounts',      'actions' => ['view','create','edit','reset_password','logs']],
        'roles'       => ['label' => 'Roles & Permissions','actions' => ['view','manage']],
        'master'      => ['label' => 'Master Entry',       'actions' => ['view','manage']],
        'centers'     => ['label' => 'Centers',            'actions' => ['view','manage']],
        'reports'     => ['label' => 'Reports',            'actions' => ['view','financial','admin']],
        'settings'    => ['label' => 'System Settings',    'actions' => ['view','manage','backup']],
        'audit'       => ['label' => 'Audit Log',          'actions' => ['view']],
    ],
    // slug => [name, all_centers, description, permission patterns]
    'roles' => [
        'super_admin'      => ['Super Administrator', 1, 'Full unrestricted access', ['*']],
        'ho_admin'         => ['Head Office Administrator', 1, 'Monitors and controls all centers', ['*.*', '!settings.backup']],
        'ho_accounts'      => ['Head Office Accounts', 1, 'Finance across all centers',
                               ['dashboard.view','students.view','enrollments.view','courses.view','batches.view','fees.*','expenses.*','reports.view','reports.financial','centers.view']],
        'academic_manager' => ['Academic Manager', 1, 'Courses, batches, exams and certificates for all centers',
                               ['dashboard.view','students.view','students.export','enrollments.view','enrollments.approve','enrollments.transfer','courses.*','batches.*','routines.*','assignments.*','attendance.*','exams.*','certificates.*','staff.view','reports.view','centers.view']],
        'center_manager'   => ['Center Manager', 0, 'Manages one or more centers',
                               ['dashboard.view','students.*','enrollments.*','documents.*','certificates.*','courses.view','batches.*','routines.*','assignments.*','attendance.*','exams.*','fees.*','expenses.*','staff.view','staff.create','staff.edit','staff.export','assets.*','reports.view','reports.financial','reports.admin']],
        'center_admin'     => ['Center Administrator', 0, 'Day-to-day center administration',
                               ['dashboard.view','students.view','students.create','students.edit','students.export','students.print','students.import','enrollments.view','enrollments.create','enrollments.edit','documents.*','certificates.view','certificates.print','courses.view','batches.view','batches.create','batches.edit','routines.*','attendance.*','fees.view','fees.collect','expenses.view','expenses.create','staff.view','assets.view','assets.create','assets.edit','assets.maintenance','reports.view']],
        'accounts_officer' => ['Accounts Officer', 0, 'Fee collection and expenses',
                               ['dashboard.view','students.view','enrollments.view','fees.view','fees.collect','fees.export','expenses.view','expenses.create','expenses.export','reports.view','reports.financial']],
        'admission_officer'=> ['Admission Officer', 0, 'Student registration and enrollment',
                               ['dashboard.view','students.view','students.create','students.edit','students.print','enrollments.view','enrollments.create','documents.view','documents.create','courses.view','batches.view','fees.view','fees.collect']],
        'trainer'          => ['Trainer', 0, 'Class, attendance, assignments and marks',
                               ['dashboard.view','students.view','enrollments.view','courses.view','batches.view','routines.view','assignments.*','attendance.view','attendance.take','exams.view','exams.marks']],
        'data_entry'       => ['Data Entry Operator', 0, 'Basic data entry',
                               ['dashboard.view','students.view','students.create','students.edit','documents.view','documents.create','staff.view','assets.view','assets.create']],
        'report_viewer'    => ['Report Viewer', 1, 'Read-only access to reports',
                               ['dashboard.view','reports.view','reports.financial','reports.admin','students.view','courses.view','batches.view']],
    ],
];
