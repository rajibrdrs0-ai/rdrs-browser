<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use App\Services\Rbac;

/**
 * Route guard: 'permission:user.view' or any-of 'permission:user.create,user.update'.
 */
final class PermissionMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, array $params = []): Response
    {
        if ($params === [] || !Rbac::canAny($params)) {
            throw new HttpException(403, __('errors.forbidden'));
        }
        return $next($request);
    }
}
