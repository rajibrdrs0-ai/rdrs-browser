<?php
declare(strict_types=1);

namespace App\Core;

/**
 * HTTP response value object. Built by controllers, sent once by the front controller.
 */
class Response
{
    public const STATUS_TEXTS = [
        200 => 'OK', 201 => 'Created', 204 => 'No Content',
        301 => 'Moved Permanently', 302 => 'Found', 303 => 'See Other', 304 => 'Not Modified',
        400 => 'Bad Request', 401 => 'Unauthorized', 403 => 'Forbidden', 404 => 'Not Found',
        405 => 'Method Not Allowed', 409 => 'Conflict', 413 => 'Payload Too Large',
        419 => 'Page Expired', 422 => 'Unprocessable Entity', 429 => 'Too Many Requests',
        500 => 'Internal Server Error', 503 => 'Service Unavailable',
    ];

    /** @var array<string,string> */
    private array $headers = [];
    /** @var list<array{name:string,value:string,options:array<string,mixed>}> */
    private array $cookies = [];
    private ?string $filePath = null;

    public function __construct(
        private string $content = '',
        private int $status = 200,
        array $headers = []
    ) {
        foreach ($headers as $name => $value) {
            $this->header((string) $name, (string) $value);
        }
    }

    public static function html(string $html, int $status = 200): self
    {
        return new self($html, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        return new self($body, $status, ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    public static function redirect(string $url, int $status = 302): self
    {
        // Only allow relative or same-application redirects (open-redirect protection).
        if (preg_match('#^https?://#i', $url)) {
            $appUrl = (string) Config::get('app.url', '');
            if ($appUrl === '' || !str_starts_with($url, $appUrl)) {
                $url = '/';
            }
        }
        return new self('', $status, ['Location' => $url]);
    }

    /** Stream a file to the browser (labels, CSV exports, PDFs). */
    public static function download(string $path, string $downloadName, string $mime = 'application/octet-stream', bool $inline = false): self
    {
        if (!is_file($path)) {
            throw new HttpException(404);
        }
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $downloadName) ?: 'download';
        $response = new self('', 200, [
            'Content-Type'        => $mime,
            'Content-Length'      => (string) filesize($path),
            'Content-Disposition' => ($inline ? 'inline' : 'attachment') . '; filename="' . $safeName . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->filePath = $path;
        return $response;
    }

    public function header(string $name, string $value): static
    {
        // Strip CR/LF to prevent header injection.
        $this->headers[$name] = str_replace(["\r", "\n"], '', $value);
        return $this;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headers[$name]);
    }

    /** @param array<string,mixed> $options */
    public function cookie(string $name, string $value, array $options = []): static
    {
        $this->cookies[] = ['name' => $name, 'value' => $value, 'options' => $options + [
            'expires'  => 0,
            'path'     => '/',
            'secure'   => false,
            'httponly' => true,
            'samesite' => 'Lax',
        ]];
        return $this;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function content(): string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value, true);
            }
            foreach ($this->cookies as $cookie) {
                setcookie($cookie['name'], $cookie['value'], $cookie['options']);
            }
        }
        if ($this->filePath !== null) {
            readfile($this->filePath);
            return;
        }
        echo $this->content;
    }
}
