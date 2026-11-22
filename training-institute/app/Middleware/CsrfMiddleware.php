<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Config;
use App\Core\HttpException;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use App\Security\Csrf;

/**
 * Rejects state-changing requests (POST/PUT/PATCH/DELETE) without a valid
 * CSRF token. Token-authenticated API paths listed in security.csrf.except
 * are exempt (they don't use cookies).
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, array $params = []): Response
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }
        foreach ((array) Config::get('security.csrf.except', []) as $prefix) {
            if (str_starts_with($request->path(), (string) $prefix)) {
                return $next($request);
            }
        }
        if (!Csrf::verify($request)) {
            throw new HttpException(419, 'Your session has expired or the form is invalid. Please reload the page and try again.');
        }
        return $next($request);
    }
}
