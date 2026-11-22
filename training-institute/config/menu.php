<?php
/**
 * Sidebar menu. An item is shown only if the user holds at least one of its
 * permissions. 'ready' => false items are listed (so each role sees what is
 * coming for it) but disabled until their module is delivered.
 */
declare(strict_types=1);

return [
    ['label' => 'menu.dashboard', 'icon' => 'speedometer2', 'url' => '/', 'perm' => ['dashboard.view'], 'ready' => true],
    ['label' => 'menu.portal', 'icon' => 'person-badge', 'url' => '/portal', 'perm' => ['portal.student'], 'ready' => false],

    ['section' => 'menu.administration'],
    ['label' => 'menu.users',   'icon' => 'people',        'url' => '/users',   'perm' => ['user.view'], 'ready' => true],
    ['label' => 'menu.roles',   'icon' => 'shield-lock',   'url' => '/roles',   'perm' => ['role.view', 'role.manage'], 'ready' => true],
    ['label' => 'menu.centers', 'icon' => 'building',      'url' => '/centers', 'perm' => ['center.view'], 'ready' => true],
    ['label' => 'menu.upazilas', 'icon' => 'geo-alt',      'url' => '/geo/upazilas', 'perm' => ['geo.manage'], 'ready' => true],

    ['section' => 'menu.academic'],
    ['label' => 'menu.trades',       'icon' => 'tools',            'url' => '/trades',       'perm' => ['course.view'], 'ready' => true],
    ['label' => 'menu.courses',      'icon' => 'journal-bookmark', 'url' => '/courses',      'perm' => ['course.view'], 'ready' => true],
    ['label' => 'menu.batches',      'icon' => 'collection',       'url' => '/batches',      'perm' => ['batch.view'], 'ready' => true],
    ['label' => 'menu.routine',      'icon' => 'calendar3-week',   'url' => '/routine',      'perm' => ['batch.view'], 'ready' => true],
    ['label' => 'menu.students',     'icon' => 'mortarboard',      'url' => '/students',     'perm' => ['student.view'], 'ready' => false],
    ['label' => 'menu.attendance',   'icon' => 'calendar-check',   'url' => '/attendance',   'perm' => ['attendance.view'], 'ready' => false],
    ['label' => 'menu.exams',        'icon' => 'clipboard-check',  'url' => '/assessments',  'perm' => ['assessment.view'], 'ready' => false],
    ['label' => 'menu.certificates', 'icon' => 'award',            'url' => '/certificates', 'perm' => ['certificate.view'], 'ready' => false],

    ['section' => 'menu.finance'],
    ['label' => 'menu.fees',     'icon' => 'cash-coin', 'url' => '/fees',     'perm' => ['fee.view'], 'ready' => false],
    ['label' => 'menu.expenses', 'icon' => 'receipt',   'url' => '/expenses', 'perm' => ['expense.view'], 'ready' => false],
    ['label' => 'menu.cashbook', 'icon' => 'journal-text', 'url' => '/cashbook', 'perm' => ['finance.cashbook'], 'ready' => false],

    ['section' => 'menu.hr'],
    ['label' => 'menu.staff',     'icon' => 'person-vcard', 'url' => '/staff',      'perm' => ['staff.view'], 'ready' => true],
    ['label' => 'menu.assets',    'icon' => 'pc-display',   'url' => '/assets',     'perm' => ['asset.view'], 'ready' => false],
    ['label' => 'menu.placement', 'icon' => 'briefcase',    'url' => '/placements', 'perm' => ['placement.view'], 'ready' => false],

    ['section' => 'menu.reports'],
    ['label' => 'menu.reports',  'icon' => 'bar-chart-line', 'url' => '/reports',  'perm' => ['report.academic', 'report.finance'], 'ready' => false],
    ['label' => 'menu.audit',    'icon' => 'clock-history',  'url' => '/audit',    'perm' => ['audit.view'], 'ready' => false],
    ['label' => 'menu.settings', 'icon' => 'gear',           'url' => '/settings', 'perm' => ['settings.manage'], 'ready' => false],
];
