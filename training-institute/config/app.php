<?php
declare(strict_types=1);

return [
    'name'            => env('APP_NAME', 'RTTI Management System'),
    'env'             => env('APP_ENV', 'production'),
    'debug'           => (bool) env('APP_DEBUG', false),
    'url'             => rtrim((string) env('APP_URL', 'http://localhost'), '/'),
    'timezone'        => env('APP_TIMEZONE', 'Asia/Dhaka'),
    'locale'          => env('APP_LOCALE', 'bn'),
    'fallback_locale' => 'en',
    'locales'         => ['bn' => 'বাংলা', 'en' => 'English'],
    'key'             => env('APP_KEY', ''),
    'currency'        => ['code' => 'BDT', 'symbol' => '৳'],
    'upload_max_mb'   => (int) env('UPLOAD_MAX_MB', 5),
    'version'         => '2.0.0',

    // Route middleware names -> classes. The router fails closed on unknown names.
    'middleware_aliases' => [
        'csrf'       => \App\Middleware\CsrfMiddleware::class,
        'auth'       => \App\Middleware\AuthMiddleware::class,
        'guest'      => \App\Middleware\GuestMiddleware::class,
        'permission' => \App\Middleware\PermissionMiddleware::class,
    ],

    'global_middleware' => [],
];
