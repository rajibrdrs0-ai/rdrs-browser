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

    /**
     * Simple LIMIT/OFFSET pagination.
     * @param string $from   "FROM … WHERE …" part (no ORDER/LIMIT)
     * @return array{rows:list<array<string,mixed>>,total:int,page:int,pages:int}
     */
    protected function paginate(string $select, string $from, array $params, string $orderBy, int $perPage = 25): array
    {
        $total = (int) Database::value('SELECT COUNT(*) ' . $from, $params);
        $pages = (int) max(1, ceil($total / $perPage));
        $page = min($pages, max(1, (int) $this->request->query('page', 1)));
        $rows = Database::all(
            'SELECT ' . $select . ' ' . $from . ' ORDER BY ' . $orderBy . ' LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
            $params
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /** Integer query/route value or null. */
    protected function intOrNull(mixed $value): ?int
    {
        $v = filter_var($value, FILTER_VALIDATE_INT);
        return $v === false || $v === null ? null : (int) $v;
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
