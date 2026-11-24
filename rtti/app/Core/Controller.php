<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Base controller: rendering, authorization and common helpers.
 */
abstract class Controller
{
    /** Render a view inside the main layout */
    protected function render(string $view, array $data = [], string $layout = 'main'): void
    {
        View::render($view, $data, $layout);
    }

    /** Server-side permission check (never rely on hidden buttons only) */
    protected function authorize(string $perm): void
    {
        if (!Auth::can($perm)) {
            $this->deny('You do not have permission to perform this action (' . $perm . ').');
        }
    }

    protected function authorizeAny(array $perms): void
    {
        if (!Auth::canAny($perms)) {
            $this->deny('You do not have permission to access this page.');
        }
    }

    /** Ensure a record belongs to a center the user may access */
    protected function authorizeCenter($centerId): void
    {
        if (!Auth::canAccessCenter($centerId === null ? null : (int)$centerId)) {
            $this->deny('This record belongs to a center you are not assigned to.');
        }
    }

    protected function deny(string $msg): never
    {
        Audit::log('security', 'denied', null, mb_substr($msg . ' [' . ($_GET['r'] ?? '') . ']', 0, 255));
        http_response_code(403);
        if (is_ajax()) {
            json_response(['ok' => false, 'message' => $msg], 403);
        }
        View::render('errors/403', ['message' => $msg]);
        exit;
    }

    protected function notFound(string $what = 'Record'): never
    {
        http_response_code(404);
        if (is_ajax()) {
            json_response(['ok' => false, 'message' => "$what not found."], 404);
        }
        View::render('errors/404', ['message' => "$what not found."]);
        exit;
    }

    protected function requirePost(): void
    {
        if (!is_post()) {
            http_response_code(405);
            exit('Method not allowed');
        }
        csrf_check();
    }

    /** Validate or redirect back with errors + old input */
    protected function validate(array $rules, ?array $data = null): array
    {
        $v = new Validator($data ?? $_POST);
        $errors = $v->check($rules);
        if ($errors) {
            if (is_ajax()) {
                json_response(['ok' => false, 'message' => implode("\n", $errors), 'errors' => $errors], 422);
            }
            keep_old();
            foreach ($errors as $err) {
                flash('danger', $err);
            }
            back();
        }
        return $data ?? $_POST;
    }

    protected function fail(string $message): never
    {
        if (is_ajax()) {
            json_response(['ok' => false, 'message' => $message], 422);
        }
        keep_old();
        flash('danger', $message);
        back();
    }

    /**
     * Resolve list filters (district/center) restricted to user's scope.
     * Returns [whereSql, params, filters].
     */
    protected function centerFilter(string $centerCol, string $districtCol = 'ctr.district_id'): array
    {
        $where = Auth::scope($centerCol);
        $params = [];
        $centerId = input_id('center_id');
        $districtId = input_id('district_id');
        if ($centerId) {
            $this->authorizeCenter($centerId);
            $where .= " AND $centerCol = :f_center";
            $params['f_center'] = $centerId;
        }
        if ($districtId) {
            $where .= " AND $districtCol = :f_district";
            $params['f_district'] = $districtId;
        }
        return [$where, $params];
    }

    /** Pick a center for a new record: posted value (if allowed) or user's default */
    protected function resolveCenter(?int $posted): int
    {
        $cid = $posted ?: Auth::defaultCenterId();
        if (!$cid) {
            $this->fail('Please select a training center.');
        }
        $this->authorizeCenter($cid);
        return (int)$cid;
    }
}
