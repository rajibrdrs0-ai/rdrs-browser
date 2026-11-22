<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use App\Services\Auth;

/**
 * Requires a logged-in, active user. Forces a password change when
 * must_change_password is set, and sets the DB audit context.
 */
final class AuthMiddleware implements MiddlewareInterface
{
    private const ALLOWED_WHILE_PASSWORD_EXPIRED = ['/password', '/logout', '/lang/bn', '/lang/en'];

    public function handle(Request $request, callable $next, array $params = []): Response
    {
        $user = Auth::user();
        if ($user === null) {
            if ($request->wantsJson()) {
                throw new HttpException(401, 'Please log in.');
            }
            return Response::redirect(url('/login'));
        }

        Database::setAuditContext((int) $user['id'], $user['center_id'] !== null ? (int) $user['center_id'] : null, $request->ip());

        if ((int) $user['must_change_password'] === 1 && !in_array($request->path(), self::ALLOWED_WHILE_PASSWORD_EXPIRED, true)) {
            return Response::redirect(url('/password'));
        }
        return $next($request);
    }
}
