<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use App\Services\Auth;

/** Login page is only for visitors who are not signed in. */
final class GuestMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, array $params = []): Response
    {
        return Auth::check() ? Response::redirect(url('/')) : $next($request);
    }
}
