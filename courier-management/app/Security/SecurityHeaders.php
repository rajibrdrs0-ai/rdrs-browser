<?php
declare(strict_types=1);

namespace App\Security;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;

/**
 * Adds hardening headers (CSP with per-request nonce, HSTS on HTTPS,
 * clickjacking/MIME-sniffing protection) to every response.
 * Inline <script> tags must carry nonce="<?= csp_nonce() ?>".
 */
final class SecurityHeaders
{
    private static ?string $nonce = null;

    public static function nonce(): string
    {
        return self::$nonce ??= base64_encode(random_bytes(16));
    }

    public static function apply(Response $response, Request $request): Response
    {
        foreach ((array) Config::get('security.headers', []) as $name => $value) {
            if (!$response->hasHeader((string) $name)) {
                $response->header((string) $name, (string) $value);
            }
        }

        $csp = (string) Config::get('security.csp', '');
        if ($csp !== '' && !$response->hasHeader('Content-Security-Policy')) {
            $response->header('Content-Security-Policy', str_replace('{nonce}', self::nonce(), $csp));
        }

        if ($request->isSecure()) {
            $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Authenticated pages must never be cached by shared proxies.
        if (!$response->hasHeader('Cache-Control')) {
            $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
            $response->header('Pragma', 'no-cache');
        }

        header_remove('X-Powered-By');
        return $response;
    }
}
