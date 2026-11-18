<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Every middleware receives the request, optional parameters from the
 * route definition (e.g. "permission:parcel.create" -> ['parcel.create'])
 * and the next handler in the pipeline.
 */
interface MiddlewareInterface
{
    /**
     * @param callable(Request):Response $next
     * @param list<string> $params
     */
    public function handle(Request $request, callable $next, array $params = []): Response;
}
