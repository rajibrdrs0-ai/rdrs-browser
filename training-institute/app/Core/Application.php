<?php
declare(strict_types=1);

namespace App\Core;

use App\Security\SecurityHeaders;
use Throwable;

/**
 * HTTP kernel: starts the session, loads routes, runs global middleware,
 * dispatches to the router and converts exceptions into responses.
 */
final class Application
{
    private static ?self $instance = null;
    private Router $router;
    private ?Request $request = null;

    public function __construct(private readonly string $basePath)
    {
        self::$instance = $this;
        $this->router = new Router();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('Application has not been bootstrapped.');
        }
        return self::$instance;
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function request(): ?Request
    {
        return $this->request;
    }

    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }

    private function loadRoutes(): void
    {
        $router = $this->router;
        foreach (['web.php', 'api.php'] as $file) {
            $path = $this->basePath('routes/' . $file);
            if (is_file($path)) {
                (static function (Router $router, string $__path): void {
                    require $__path;
                })($router, $path);
            }
        }
    }

    public function handle(Request $request): Response
    {
        $this->request = $request;
        return SecurityHeaders::apply($this->process($request), $request);
    }

    private function process(Request $request): Response
    {
        try {
            if (!str_starts_with($request->path(), '/api/')) {
                Session::start($request);
            }
            Lang::setLocale((string) (Session::get('locale') ?? Config::get('app.locale', 'bn')));
            View::share('request', $request);
            $this->loadRoutes();

            $global = array_values(array_filter(
                (array) Config::get('app.global_middleware', []),
                'class_exists'
            ));

            $core = fn(Request $req): Response => $this->router->dispatch($req);
            $pipeline = array_reduce(
                array_reverse($global),
                static fn(callable $next, string $class): callable =>
                    static fn(Request $req): Response => (new $class())->handle($req, $next, []),
                $core
            );
            return $pipeline($request);
        } catch (ValidationException $e) {
            if ($request->wantsJson()) {
                return ErrorHandler::render($e, true);
            }
            Session::flash('errors', $e->errors());
            Session::flash('old', array_diff_key($request->all(), array_flip(['password', 'password_confirmation', '_token'])));
            $referer = $request->referer();
            return Response::redirect($referer !== null && $referer !== '' ? $referer : url('/'));
        } catch (Throwable $e) {
            ErrorHandler::report($e);
            return ErrorHandler::render($e, $request->wantsJson());
        }
    }
}
