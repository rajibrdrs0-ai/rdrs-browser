<?php
declare(strict_types=1);

$secureCookie = strtolower((string) env('SESSION_SECURE_COOKIE', 'auto'));

return [
    'session' => [
        'name'             => env('SESSION_NAME', 'CMS_SESSID'),
        'idle_timeout'     => (int) env('SESSION_IDLE_TIMEOUT', 1800),
        'absolute_timeout' => (int) env('SESSION_ABSOLUTE_TIMEOUT', 43200),
        // 'auto' = secure flag only when the request arrived over HTTPS
        'secure'           => $secureCookie === 'auto' ? null : filter_var($secureCookie, FILTER_VALIDATE_BOOL),
        'samesite'         => 'Lax',
        'save_path'        => BASE_PATH . '/storage/sessions',
        'regenerate_every' => 900, // rotate session id every 15 minutes
    ],

    'csrf' => [
        'field'  => '_token',
        'header' => 'X-CSRF-TOKEN',
        // Paths (prefix match) exempt from CSRF; token-authenticated APIs only.
        'except' => ['/api/v1/'],
    ],

    'password' => [
        'min_length'      => 8,
        'require_upper'   => true,
        'require_lower'   => true,
        'require_digit'   => true,
        'require_symbol'  => false,
        'argon2_options'  => [
            'memory_cost' => 65536, // 64 MB
            'time_cost'   => 4,
            'threads'     => 1,
        ],
        'bcrypt_cost'     => 12,
    ],

    'login' => [
        'max_attempts'    => (int) env('LOGIN_MAX_ATTEMPTS', 5),
        'lockout_minutes' => (int) env('LOGIN_LOCKOUT_MINUTES', 15),
    ],

    'trusted_proxies' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TRUSTED_PROXIES', ''))
    ))),

    'headers' => [
        'X-Content-Type-Options'  => 'nosniff',
        'X-Frame-Options'         => 'SAMEORIGIN',
        'Referrer-Policy'         => 'strict-origin-when-cross-origin',
        'Permissions-Policy'      => 'geolocation=(self), camera=(self), microphone=()',
        'Cross-Origin-Opener-Policy' => 'same-origin',
    ],

    // Content-Security-Policy. {nonce} is replaced per request.
    'csp' => implode('; ', [
        "default-src 'self'",
        "script-src 'self' 'nonce-{nonce}' https://cdn.jsdelivr.net https://code.jquery.com",
        "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com",
        "font-src 'self' data: https://cdn.jsdelivr.net https://fonts.gstatic.com",
        "img-src 'self' data: blob:",
        "connect-src 'self'",
        "frame-ancestors 'self'",
        "form-action 'self'",
        "base-uri 'self'",
        "object-src 'none'",
    ]),

    'uploads' => [
        'allowed_images' => ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
        'allowed_sheets' => [
            'text/csv' => 'csv', 'text/plain' => 'csv', 'application/csv' => 'csv',
            'application/vnd.ms-excel' => 'csv',
        ],
        'allowed_documents' => ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'],
    ],
];
