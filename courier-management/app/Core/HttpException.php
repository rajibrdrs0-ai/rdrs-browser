<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Exception carrying an HTTP status code (403, 404, 419, 422, 429 ...).
 */
class HttpException extends RuntimeException
{
    /** @param array<string,string> $headers */
    public function __construct(
        private readonly int $statusCode,
        string $message = '',
        private readonly array $headers = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message !== '' ? $message : (Response::STATUS_TEXTS[$statusCode] ?? 'Error'), $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /** @return array<string,string> */
    public function getHeaders(): array
    {
        return $this->headers;
    }
}
