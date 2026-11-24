<?php
/**
 * RTTI configuration. Copy to config/config.php (the web installer does this for you).
 */
return [
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'name'     => 'rtti_training_management',
        'user'     => 'root',
        'pass'     => '',
        'charset'  => 'utf8mb4',
    ],
    // Leave empty to auto-detect (works under XAMPP sub-folders, e.g. http://localhost/rtti/public/)
    'base_url'   => '',
    'debug'      => false,          // true = show PHP errors on screen (never in production)
    'timezone'   => 'Asia/Dhaka',
    'upload_max_mb' => 5,
    // mysqldump binary used by Settings > Backup (XAMPP: C:\\xampp\\mysql\\bin\\mysqldump.exe)
    'mysqldump'  => 'mysqldump',
    'mysql'      => 'mysql',
];
