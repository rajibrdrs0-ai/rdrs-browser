<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Lightweight router with:
 *  - GET/POST/PUT/PATCH/DELETE + "any"
 *  - typed placeholders: /parcels/{id:\d+}, /track/{tracking}
 *  - nested groups with prefix + middleware
 *  - named routes and reverse URL generation
 *  - middleware pipeline with parameters ("permission:parcel.create")
 */
final class Router
{
    /** @var list<array{method:string,pattern:string,regex:string,params:list<string>,handler:mixed,middleware:list<string>,name:?string}> */
    private array $routes = [];
    /** @var array<string,int> name => index */
    private array $named = [];
    /** @var list<array{prefix:string,middleware:list<string>}> */
    private array $groupStack = [];
    private ?int $lastIndex = null;

    public function get(string $path, mixed $handler, array $middleware = []): self
    {
        return $this->add(['GET', 'HEAD'], $path, $handler, $middleware);
    }

    public function post(string $path, mixed $handler, array $middleware = []): self
    {
        return $this->add(['POST'], $path, $handler, $middleware);
    }

    public function put(string $path, mixed $handler, array $middleware = []): self
    {
        return $this->add(['PUT'], $path, $handler, $middleware);
    }

    public function patch(string $path, mixed $handler, array $middleware = []): self
    {
        return $this->add(['PATCH'], $path, $handler, $middleware);
    }

    public function delete(string $path, mixed $handler, array $middleware = []): self
    {
        return $this->add(['DELETE'], $path, $handler, $middleware);
    }

    public function any(string $path, mixed $handler, array $middleware = []): self
    {
        return $this->add(['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'], $path, $handler, $middleware);
    }

    /**
     * @param array{prefix?:string,middleware?:list<string>} $attributes
     * @param callable(Router):void $callback
     */
    public function group(array $attributes, callable $callback): void
    {
        $this->groupStack[] = [
            'prefix'     => trim((string) ($attributes['prefix'] ?? ''), '/'),
            'middleware' => (array) ($attributes['middleware'] ?? []),
        ];
        $callback($this);
        array_pop($this->groupStack);
    }

    /** Name the most recently added route. */
    public function name(string $name): self
    {
        if ($this->lastIndex !== null) {
            $this->routes[$this->lastIndex]['name'] = $name;
            $this->named[$name] = $this->lastIndex;
        }
        return $this;
    }

    /**
     * @param list<string> $methods
     * @param list<string> $middleware
     */
    private function add(array $methods, string $path, mixed $handler, array $middleware): self
    {
        $prefix = '';
        $groupMiddleware = [];
        foreach ($this->groupStack as $group) {
            if ($group['prefix'] !== '') {
                $prefix .= '/' . $group['prefix'];
            }
            $groupMiddleware = array_merge($groupMiddleware, $group['middleware']);
        }
        $pattern = '/' . trim($prefix . '/' . trim($path, '/'), '/');
        [$regex, $params] = $this->compile($pattern);

        foreach ($methods as $method) {
            $this->routes[] = [
                'method'     => $method,
                'pattern'    => $pattern,
                'regex'      => $regex,
                'params'     => $params,
                'handler'    => $handler,
                'middleware' => array_values(array_merge($groupMiddleware, $middleware)),
                'name'       => null,
            ];
        }
        // name() applies to the first method entry; url() only needs the pattern.
        $this->lastIndex = count($this->routes) - count($methods);
        return $this;
    }

    /** @return array{0:string,1:list<string>} */
    private function compile(string $pattern): array
    {
        $params = [];
        $regex = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}/',
            static function (array $m) use (&$params): string {
                $params[] = $m[1];
                $constraint = $m[2] ?? '[^/]+';
                return '(' . $constraint . ')';
            },
            $pattern
        );
        return ['#^' . $regex . '$#u', $params];
    }

    /** @param array<string,scalar> $params */
    public function url(string $name, array $params = []): string
    {
        if (!isset($this->named[$name])) {
            throw new \InvalidArgumentException("Route [{$name}] is not defined.");
        }
        $pattern = $this->routes[$this->named[$name]]['pattern'];
        $url = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::[^}]+)?\}/',
            static function (array $m) use (&$params, $name): string {
                if (!array_key_exists($m[1], $params)) {
                    throw new \InvalidArgumentException("Missing parameter [{$m[1]}] for route [{$name}].");
                }
                $value = rawurlencode((string) $params[$m[1]]);
                unset($params[$m[1]]);
                return $value;
            },
            $pattern
        );
        if ($params !== []) {
            $url .= '?' . http_build_query($params);
        }
        return (string) $url;
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $path = $request->path();
        $allowed = [];

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }
            if ($route['method'] !== $method) {
                $allowed[] = $route['method'];
                continue;
            }
            array_shift($matches);
            $request->setRouteParams(array_combine($route['params'], $matches) ?: []);
            return $this->runPipeline($request, $route['middleware'], $route['handler']);
        }

        if ($allowed !== []) {
            throw new HttpException(405, '', ['Allow' => implode(', ', array_unique($allowed))]);
        }
        throw new HttpException(404);
    }

    /** @param list<string> $middleware */
    private function runPipeline(Request $request, array $middleware, mixed $handler): Response
    {
        $aliases = (array) Config::get('app.middleware_aliases', []);
        $core = fn(Request $req): Response => $this->invoke($handler, $req);

        $pipeline = array_reduce(
            array_reverse($middleware),
            static function (callable $next, string $definition) use ($aliases): callable {
                [$alias, $paramString] = array_pad(explode(':', $definition, 2), 2, '');
                $class = $aliases[$alias] ?? $alias;
                if (!class_exists($class)) {
                    // Fail closed: a route that requires an unknown middleware must not run.
                    throw new \RuntimeException("Middleware [{$definition}] is not available.");
                }
                $params = $paramString === '' ? [] : array_map('trim', explode(',', $paramString));
                return static function (Request $req) use ($class, $next, $params): Response {
                    /** @var MiddlewareInterface $instance */
                    $instance = new $class();
                    return $instance->handle($req, $next, $params);
                };
            },
            $core
        );

        return $pipeline($request);
    }

    private function invoke(mixed $handler, Request $request): Response
    {
        if (is_array($handler) && count($handler) === 2 && is_string($handler[0])) {
            [$class, $method] = $handler;
            if (!class_exists($class) || !method_exists($class, $method)) {
                throw new \RuntimeException("Route handler {$class}::{$method} not found.");
            }
            $result = (new $class($request))->{$method}($request, ...array_values($request->routeParams()));
        } elseif (is_callable($handler)) {
            $result = $handler($request);
        } else {
            throw new \RuntimeException('Invalid route handler.');
        }

        if ($result instanceof Response) {
            return $result;
        }
        if (is_array($result)) {
            return Response::json($result);
        }
        return Response::html((string) $result);
    }
}
