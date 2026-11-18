<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Immutable-ish HTTP request wrapper around PHP superglobals.
 */
final class Request
{
    /** @var array<string,string> route parameters filled by the Router */
    private array $routeParams = [];
    /** @var array<string,mixed> per-request attributes (authenticated user, branch scope ...) */
    private array $attributes = [];
    private ?array $jsonBody = null;

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed> $post
     * @param array<string,mixed> $server
     * @param array<string,mixed> $files
     * @param array<string,string> $cookies
     */
    public function __construct(
        private readonly array $query,
        private readonly array $post,
        private readonly array $server,
        private readonly array $files,
        private readonly array $cookies,
        private readonly string $rawBody = ''
    ) {
    }

    public static function capture(): self
    {
        return new self($_GET, $_POST, $_SERVER, $_FILES, $_COOKIE, (string) file_get_contents('php://input'));
    }

    /** Real method, honouring a "_method" override on POST forms (PUT/PATCH/DELETE). */
    public function method(): string
    {
        $method = strtoupper((string) ($this->server['REQUEST_METHOD'] ?? 'GET'));
        if ($method === 'POST') {
            $override = strtoupper((string) ($this->post['_method'] ?? $this->header('X-HTTP-Method-Override') ?? ''));
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $override;
            }
        }
        return $method;
    }

    /** Sub-directory the app is installed in, e.g. "/courier-management/public". */
    public function basePath(): string
    {
        $script = str_replace('\\', '/', (string) ($this->server['SCRIPT_NAME'] ?? ''));
        $dir = rtrim(dirname($script), '/');
        return $dir === '.' ? '' : $dir;
    }

    /** Request path relative to the installation, always starting with "/". */
    public function path(): string
    {
        $uri = (string) ($this->server['REQUEST_URI'] ?? '/');
        $path = rawurldecode((string) parse_url($uri, PHP_URL_PATH));
        $base = $this->basePath();
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }
        if (str_starts_with($path, '/index.php')) {
            $path = substr($path, strlen('/index.php'));
        }
        $path = '/' . trim($path, '/');
        return $path;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /** Body input (form or JSON) falling back to the query string. */
    public function input(string $key, mixed $default = null): mixed
    {
        $all = $this->all();
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        return array_replace($this->query, $this->post, $this->json());
    }

    /**
     * @param list<string> $keys
     * @return array<string,mixed>
     */
    public function only(array $keys): array
    {
        $all = $this->all();
        return array_intersect_key($all, array_flip($keys));
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    /** @return array<string,mixed> */
    public function json(): array
    {
        if ($this->jsonBody === null) {
            $this->jsonBody = [];
            if ($this->isJson() && $this->rawBody !== '') {
                $decoded = json_decode($this->rawBody, true);
                $this->jsonBody = is_array($decoded) ? $decoded : [];
            }
        }
        return $this->jsonBody;
    }

    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;
        return is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE ? $file : null;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (isset($this->server[$key])) {
            return (string) $this->server[$key];
        }
        $plain = strtoupper(str_replace('-', '_', $name));
        return isset($this->server[$plain]) ? (string) $this->server[$plain] : null;
    }

    public function bearerToken(): ?string
    {
        $auth = $this->header('Authorization') ?? (string) ($this->server['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        return preg_match('/^Bearer\s+(\S+)$/i', $auth, $m) ? $m[1] : null;
    }

    public function cookie(string $key): ?string
    {
        return $this->cookies[$key] ?? null;
    }

    public function isJson(): bool
    {
        return str_contains(strtolower((string) $this->header('Content-Type')), 'application/json');
    }

    public function isAjax(): bool
    {
        return strtolower((string) $this->header('X-Requested-With')) === 'xmlhttprequest';
    }

    public function wantsJson(): bool
    {
        return $this->isAjax()
            || $this->isJson()
            || str_contains(strtolower((string) $this->header('Accept')), 'application/json')
            || str_starts_with($this->path(), '/api/');
    }

    public function isSecure(): bool
    {
        if (!empty($this->server['HTTPS']) && strtolower((string) $this->server['HTTPS']) !== 'off') {
            return true;
        }
        if ((int) ($this->server['SERVER_PORT'] ?? 0) === 443) {
            return true;
        }
        return $this->fromTrustedProxy()
            && strtolower((string) $this->header('X-Forwarded-Proto')) === 'https';
    }

    /** Client IP; X-Forwarded-For is only honoured from configured trusted proxies. */
    public function ip(): string
    {
        $remote = (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
        if ($this->fromTrustedProxy()) {
            $forwarded = (string) $this->header('X-Forwarded-For');
            foreach (array_map('trim', explode(',', $forwarded)) as $candidate) {
                if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                    return $candidate;
                }
            }
        }
        return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';
    }

    private function fromTrustedProxy(): bool
    {
        $trusted = (array) Config::get('security.trusted_proxies', []);
        return $trusted !== [] && in_array((string) ($this->server['REMOTE_ADDR'] ?? ''), $trusted, true);
    }

    public function userAgent(): string
    {
        return mb_substr((string) ($this->server['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public function fullUrl(): string
    {
        $scheme = $this->isSecure() ? 'https' : 'http';
        $host = (string) ($this->server['HTTP_HOST'] ?? 'localhost');
        return $scheme . '://' . $host . (string) ($this->server['REQUEST_URI'] ?? '/');
    }

    public function referer(): ?string
    {
        return $this->header('Referer');
    }

    /** @param array<string,string> $params */
    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    /** @return array<string,string> */
    public function routeParams(): array
    {
        return $this->routeParams;
    }

    public function route(string $key, ?string $default = null): ?string
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }
}
