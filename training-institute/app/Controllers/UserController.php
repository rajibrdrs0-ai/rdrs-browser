<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;
use App\Security\Password;
use App\Services\Auth;
use App\Services\Rbac;

/**
 * User management with role & scope rules:
 *  - you only see users of centers inside your scope (HO sees all);
 *  - you can only assign roles with a higher level number than your own;
 *  - center-scoped roles must be tied to a center you can access,
 *    district roles to a district (global users only).
 */
final class UserController extends Controller
{
    private const PER_PAGE = 25;

    public function index(Request $request): Response
    {
        $where = ['u.deleted_at IS NULL'];
        $params = [];

        if (!Rbac::isGlobal()) {
            [$sql, $p] = Rbac::centerFilter('u.center_id');
            $where[] = $sql;
            $params = array_merge($params, $p);
        }
        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $where[] = '(u.name LIKE ? OR u.name_bn LIKE ? OR u.username LIKE ? OR u.mobile LIKE ? OR u.email LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }
        foreach (['role_id' => 'u.role_id', 'center_id' => 'u.center_id'] as $key => $column) {
            $value = filter_var($request->query($key), FILTER_VALIDATE_INT);
            if ($value !== false && $value !== null) {
                $where[] = "{$column} = ?";
                $params[] = $value;
            }
        }
        $status = (string) $request->query('status', '');
        if (in_array($status, ['active', 'inactive', 'suspended'], true)) {
            $where[] = 'u.status = ?';
            $params[] = $status;
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) Database::value("SELECT COUNT(*) FROM users u WHERE {$whereSql}", $params);
        $page = max(1, (int) $request->query('page', 1));
        $offset = ($page - 1) * self::PER_PAGE;

        $users = Database::all(
            "SELECT u.id, u.name, u.name_bn, u.username, u.mobile, u.email, u.status, u.center_id, u.last_login_at,
                    u.locked_until, r.id AS role_id, r.slug AS role_slug, r.name_en AS role_name_en, r.name_bn AS role_name_bn,
                    r.level AS role_level, r.scope AS role_scope,
                    c.name_en AS center_name_en, c.name_bn AS center_name_bn, d.name_en AS district_name_en, d.name_bn AS district_name_bn
               FROM users u
               JOIN roles r ON r.id = u.role_id
               LEFT JOIN training_centers c ON c.id = u.center_id
               LEFT JOIN districts d ON d.id = u.district_id
              WHERE {$whereSql}
              ORDER BY r.level, u.name
              LIMIT " . self::PER_PAGE . " OFFSET {$offset}",
            $params
        );
        foreach ($users as &$row) {
            $row['manageable'] = Rbac::canManageUser($row);
        }
        unset($row);

        return $this->view('users/index', [
            'users'   => $users,
            'total'   => $total,
            'page'    => $page,
            'pages'   => (int) max(1, ceil($total / self::PER_PAGE)),
            'roles'   => Database::all('SELECT id, name_en, name_bn FROM roles ORDER BY level'),
            'centers' => $this->accessibleCenters(),
            'filters' => ['q' => $q, 'role_id' => $request->query('role_id', ''), 'center_id' => $request->query('center_id', ''), 'status' => $status],
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->view('users/form', $this->formData(null));
    }

    public function store(Request $request): Response
    {
        $data = $this->validateUser($request, null);
        $password = (string) $request->input('password', '');
        $violations = Password::policyViolations($password);
        if ($violations !== []) {
            throw new ValidationException(['password' => array_map(static fn(string $k): string => __($k), $violations)]);
        }

        $id = Database::transaction(function () use ($data, $password, $request): int {
            Database::query(
                'INSERT INTO users (role_id, center_id, district_id, name, name_bn, username, email, mobile, password_hash,
                                    locale, status, must_change_password, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)',
                [$data['role_id'], $data['center_id'], $data['district_id'], $data['name'], $data['name_bn'], $data['username'],
                 $data['email'], $data['mobile'], Password::hash($password), $data['locale'], 'active', Auth::id()]
            );
            $id = Database::lastInsertId();
            $this->syncScopes($id, $data);
            $this->syncOverrides($id, $request);
            $this->audit('create', $id, null, $data);
            return $id;
        });

        Session::flash('success', __('users.created', ['name' => $data['name']]));
        return $this->redirect('/users/' . $id . '/edit');
    }

    public function edit(Request $request, string $id): Response
    {
        $target = $this->findManageable((int) $id);
        return $this->view('users/form', $this->formData($target));
    }

    public function update(Request $request, string $id): Response
    {
        $target = $this->findManageable((int) $id);
        $data = $this->validateUser($request, $target);

        Database::transaction(function () use ($target, $data, $request): void {
            Database::query(
                'UPDATE users SET role_id = ?, center_id = ?, district_id = ?, name = ?, name_bn = ?, username = ?, email = ?,
                                  mobile = ?, locale = ?, status = ?, updated_by = ? WHERE id = ?',
                [$data['role_id'], $data['center_id'], $data['district_id'], $data['name'], $data['name_bn'], $data['username'],
                 $data['email'], $data['mobile'], $data['locale'], $data['status'], Auth::id(), $target['id']]
            );
            $this->syncScopes((int) $target['id'], $data);
            $this->syncOverrides((int) $target['id'], $request);
        });

        Session::flash('success', __('users.saved', ['name' => $data['name']]));
        return $this->redirect('/users');
    }

    public function toggleStatus(Request $request, string $id): Response
    {
        $target = $this->findManageable((int) $id);
        $new = $target['status'] === 'active' ? 'inactive' : 'active';
        Database::query('UPDATE users SET status = ?, failed_login_count = 0, locked_until = NULL, updated_by = ? WHERE id = ?',
            [$new, Auth::id(), $target['id']]);
        Session::flash('success', __($new === 'active' ? 'users.activated' : 'users.deactivated'));
        return $this->back();
    }

    public function resetPassword(Request $request, string $id): Response
    {
        $target = $this->findManageable((int) $id);
        $temporary = $this->temporaryPassword();
        Database::query(
            'UPDATE users SET password_hash = ?, must_change_password = 1, failed_login_count = 0, locked_until = NULL, updated_by = ? WHERE id = ?',
            [Password::hash($temporary), Auth::id(), $target['id']]
        );
        Session::flash('warning', __('users.reset_done', ['name' => $target['name'], 'password' => $temporary]));
        return $this->back();
    }

    // -----------------------------------------------------------------

    /** @return array<string,mixed> */
    private function findManageable(int $id): array
    {
        $target = Database::first(
            'SELECT u.*, r.level AS role_level, r.scope AS role_scope, r.slug AS role_slug
               FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND u.deleted_at IS NULL',
            [$id]
        );
        if ($target === null) {
            throw new HttpException(404);
        }
        if (!Rbac::canManageUser($target)) {
            throw new HttpException(403, __('users.cannot_manage'));
        }
        return $target;
    }

    /** @return array<string,mixed> */
    private function validateUser(Request $request, ?array $target): array
    {
        $input = $request->all();
        $input['mobile'] = normalize_phone((string) ($input['mobile'] ?? ''));
        $input['username'] = mb_strtolower(trim((string) ($input['username'] ?? '')));
        $input['email'] = mb_strtolower(trim((string) ($input['email'] ?? '')));

        $rules = [
            'name'        => 'required|max:120',
            'name_bn'     => 'nullable|max:120',
            'username'    => ['required', 'regex:/^[a-z0-9][a-z0-9._-]{2,49}$/'],
            'email'       => 'nullable|email|max:150',
            'mobile'      => 'required|bd_phone',
            'role_id'     => 'required|integer',
            'center_id'   => 'nullable|integer',
            'district_id' => 'nullable|integer',
            'locale'      => 'required|in:bn,en',
        ];
        if ($target === null) {
            $rules['password'] = 'required|max:200';
        } else {
            $rules['status'] = 'required|in:active,inactive,suspended';
        }
        $data = $this->validate($rules, $input);
        $errors = [];

        $roles = [];
        foreach (Rbac::assignableRoles() as $role) {
            $roles[(int) $role['id']] = $role;
        }
        $role = $roles[(int) $data['role_id']] ?? null;
        if ($role === null) {
            $errors['role_id'][] = __('users.role_not_allowed');
        }

        $centerId = ($data['center_id'] ?? '') !== '' ? (int) $data['center_id'] : null;
        $districtId = ($data['district_id'] ?? '') !== '' ? (int) $data['district_id'] : null;

        if ($role !== null) {
            if (in_array($role['scope'], ['center', 'self'], true)) {
                $districtId = null;
                if ($centerId === null) {
                    $errors['center_id'][] = __('users.center_required');
                } elseif (!Rbac::canAccessCenter($centerId)) {
                    $errors['center_id'][] = __('users.center_not_allowed');
                }
            } elseif ($role['scope'] === 'district') {
                $centerId = null;
                if ($districtId === null || Database::value('SELECT id FROM districts WHERE id = ?', [$districtId]) === null) {
                    $errors['district_id'][] = __('users.district_required');
                }
            } else { // global roles sit at Head Office
                $districtId = null;
                $centerId = (int) Database::value("SELECT id FROM training_centers WHERE center_type = 'head_office' ORDER BY id LIMIT 1");
            }
        }

        $excludeId = $target !== null ? (int) $target['id'] : 0;
        foreach (['username', 'email', 'mobile'] as $field) {
            if ($data[$field] !== '' && $data[$field] !== null
                && Database::value("SELECT id FROM users WHERE {$field} = ? AND id <> ?", [$data[$field], $excludeId]) !== null) {
                $errors[$field][] = __('validation.unique', ['field' => __('fields.' . $field)]);
            }
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [
            'name'        => trim((string) $data['name']),
            'name_bn'     => trim((string) ($data['name_bn'] ?? '')) ?: null,
            'username'    => $data['username'],
            'email'       => $data['email'] !== '' ? $data['email'] : null,
            'mobile'      => $data['mobile'],
            'role_id'     => (int) $data['role_id'],
            'role_scope'  => (string) $role['scope'],
            'center_id'   => $centerId,
            'district_id' => $districtId,
            'locale'      => $data['locale'],
            'status'      => $data['status'] ?? 'active',
            'extra_centers'   => array_map('intval', (array) $request->input('extra_centers', [])),
            'extra_districts' => array_map('intval', (array) $request->input('extra_districts', [])),
        ];
    }

    /** Additional centers / districts (only within the editor's own scope). */
    private function syncScopes(int $userId, array $data): void
    {
        Database::query('DELETE FROM user_center_access WHERE user_id = ?', [$userId]);
        Database::query('DELETE FROM user_district_access WHERE user_id = ?', [$userId]);

        if ($data['role_scope'] === 'center') {
            foreach (array_unique($data['extra_centers']) as $centerId) {
                if ($centerId !== $data['center_id'] && Rbac::canAccessCenter($centerId)) {
                    Database::query('INSERT INTO user_center_access (user_id, center_id) VALUES (?, ?)', [$userId, $centerId]);
                }
            }
        }
        if ($data['role_scope'] === 'district' && Rbac::isGlobal()) {
            foreach (array_unique($data['extra_districts']) as $districtId) {
                if ($districtId !== $data['district_id']) {
                    Database::query('INSERT IGNORE INTO user_district_access (user_id, district_id)
                                     SELECT ?, id FROM districts WHERE id = ?', [$userId, $districtId]);
                }
            }
        }
    }

    /** Per-user permission overrides — only for users who may manage roles. */
    private function syncOverrides(int $userId, Request $request): void
    {
        if (!Rbac::can('role.manage')) {
            return;
        }
        $overrides = (array) $request->input('overrides', []);
        Database::query('DELETE FROM user_permissions WHERE user_id = ?', [$userId]);
        foreach ($overrides as $permissionId => $mode) {
            if (!in_array($mode, ['grant', 'revoke'], true)) {
                continue;
            }
            Database::query(
                'INSERT INTO user_permissions (user_id, permission_id, is_granted, granted_by)
                 SELECT ?, id, ?, ? FROM permissions WHERE id = ?',
                [$userId, $mode === 'grant' ? 1 : 0, Auth::id(), (int) $permissionId]
            );
        }
    }

    /** @return array<string,mixed> */
    private function formData(?array $target): array
    {
        $extraCenters = $extraDistricts = $overrides = [];
        if ($target !== null) {
            $extraCenters = array_map('intval', array_column(Database::all('SELECT center_id FROM user_center_access WHERE user_id = ?', [$target['id']]), 'center_id'));
            $extraDistricts = array_map('intval', array_column(Database::all('SELECT district_id FROM user_district_access WHERE user_id = ?', [$target['id']]), 'district_id'));
            foreach (Database::all('SELECT permission_id, is_granted FROM user_permissions WHERE user_id = ?', [$target['id']]) as $o) {
                $overrides[(int) $o['permission_id']] = (int) $o['is_granted'] === 1 ? 'grant' : 'revoke';
            }
        }
        return [
            'target'         => $target,
            'roles'          => Rbac::assignableRoles(),
            'centers'        => $this->accessibleCenters(),
            'districts'      => Rbac::isGlobal() ? Database::all('SELECT id, name_en, name_bn FROM districts ORDER BY name_en') : [],
            'extraCenters'   => $extraCenters,
            'extraDistricts' => $extraDistricts,
            'overrides'      => $overrides,
            'permissions'    => Rbac::can('role.manage') ? Database::all('SELECT * FROM permissions ORDER BY module, id') : [],
        ];
    }

    /** @return list<array<string,mixed>> */
    private function accessibleCenters(): array
    {
        [$sql, $params] = Rbac::centerFilter('c.id');
        return Database::all(
            "SELECT c.id, c.code, c.name_en, c.name_bn, d.name_en AS district_en, d.name_bn AS district_bn
               FROM training_centers c JOIN districts d ON d.id = c.district_id
              WHERE c.is_active = 1 AND {$sql} ORDER BY c.center_type, c.name_en",
            $params
        );
    }

    private function temporaryPassword(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        do {
            $pwd = '';
            for ($i = 0; $i < 10; $i++) {
                $pwd .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (Password::policyViolations($pwd) !== []);
        return $pwd;
    }

    private function audit(string $action, int $id, ?array $old, array $new): void
    {
        unset($new['extra_centers'], $new['extra_districts']);
        Database::query(
            'INSERT INTO audit_logs (user_id, center_id, action, entity_type, entity_id, old_value, new_value, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [Auth::id(), Auth::user()['center_id'] ?? null, $action, 'user', $id,
             $old !== null ? json_encode($old, JSON_UNESCAPED_UNICODE) : null, json_encode($new, JSON_UNESCAPED_UNICODE),
             $this->request->ip(), $this->request->userAgent()]
        );
    }
}
