<?php
declare(strict_types=1);

return [
    'name'            => env('APP_NAME', 'Courier Management সার্ভিস'),
    'env'             => env('APP_ENV', 'production'),
    'debug'           => (bool) env('APP_DEBUG', false),
    'url'             => rtrim((string) env('APP_URL', 'http://localhost'), '/'),
    'timezone'        => env('APP_TIMEZONE', 'Asia/Dhaka'),
    'locale'          => env('APP_LOCALE', 'bn'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
    'locales'         => ['bn' => 'বাংলা', 'en' => 'English'],
    'key'             => env('APP_KEY', ''),
    'currency'        => ['code' => 'BDT', 'symbol' => '৳'],
    'tracking_prefix' => env('TRACKING_PREFIX', 'CMS'),
    'upload_max_mb'   => (int) env('UPLOAD_MAX_MB', 5),
    'version'         => '1.0.0',

    // Short names used in route definitions -> middleware classes.
    // The router fails closed: a route that names a middleware whose class
    // does not exist throws instead of running unprotected.
    'middleware_aliases' => [
        'csrf'       => \App\Middleware\CsrfMiddleware::class,
        'auth'       => \App\Middleware\AuthMiddleware::class,
        'guest'      => \App\Middleware\GuestMiddleware::class,
        'permission' => \App\Middleware\PermissionMiddleware::class,
        'role'       => \App\Middleware\RoleMiddleware::class,
        'branch'     => \App\Middleware\BranchScopeMiddleware::class,
        'throttle'   => \App\Middleware\ThrottleMiddleware::class,
        'api'        => \App\Middleware\ApiTokenMiddleware::class,
    ],

    // Middleware applied to every request before routing.
    // (Security headers are applied by the kernel to every response,
    //  including error pages, so they are not listed here.)
    'global_middleware' => [],
];
