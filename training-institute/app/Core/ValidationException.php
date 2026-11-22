<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Thrown by Validator::validate(). The Application turns it into a
 * redirect-back with errors (forms) or a 422 JSON response (AJAX).
 */
final class ValidationException extends HttpException
{
    /** @param array<string,list<string>> $errors */
    public function __construct(private readonly array $errors, string $message = 'The given data was invalid.')
    {
        parent::__construct(422, $message);
    }

    /** @return array<string,list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }
}
