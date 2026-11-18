<?php
declare(strict_types=1);

namespace App\Core;

use App\Security\Validator;

/**
 * Base controller: rendering, JSON, redirects and validation helpers.
 */
abstract class Controller
{
    public function __construct(protected Request $request)
    {
    }

    /** @param array<string,mixed> $data */
    protected function view(string $template, array $data = [], int $status = 200): Response
    {
        return Response::html(View::make($template, $data), $status);
    }

    protected function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    /** Uniform JSON envelope for AJAX endpoints. */
    protected function success(mixed $data = null, string $message = 'OK', int $status = 200): Response
    {
        return Response::json(['success' => true, 'message' => $message, 'data' => $data], $status);
    }

    protected function error(string $message, int $status = 400, array $errors = []): Response
    {
        return Response::json(['success' => false, 'message' => $message, 'errors' => $errors], $status);
    }

    protected function redirect(string $path, int $status = 302): Response
    {
        return Response::redirect(url($path), $status);
    }

    protected function back(): Response
    {
        $referer = $this->request->referer();
        return Response::redirect($referer !== null && $referer !== '' ? $referer : url('/'));
    }

    /**
     * Validate request input; on failure throws ValidationException which the
     * kernel converts into redirect-back-with-errors or a 422 JSON response.
     *
     * @param array<string,string|list<string>> $rules
     * @return array<string,mixed> only the validated keys
     */
    protected function validate(array $rules, ?array $data = null): array
    {
        return Validator::make($data ?? $this->request->all(), $rules)->validate();
    }
}
