<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Roles overview + permission matrix; editing a role's permissions needs
 * role.manage. Super Admin is locked to "all permissions".
 */
final class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        $roles = Database::all(
            'SELECT r.*,
                    (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id AND u.deleted_at IS NULL) AS user_count,
                    (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = r.id) AS permission_count
               FROM roles r ORDER BY r.level, r.id'
        );
        $matrix = [];
        foreach (Database::all('SELECT role_id, permission_id FROM role_permissions') as $row) {
            $matrix[(int) $row['permission_id']][(int) $row['role_id']] = true;
        }
        return $this->view('roles/index', [
            'roles'       => $roles,
            'permissions' => Database::all('SELECT * FROM permissions ORDER BY module, id'),
            'matrix'      => $matrix,
        ]);
    }

    public function edit(Request $request, string $id): Response
    {
        $role = $this->findEditable((int) $id);
        $granted = array_map('intval', array_column(
            Database::all('SELECT permission_id FROM role_permissions WHERE role_id = ?', [$role['id']]), 'permission_id'));
        return $this->view('roles/edit', [
            'role'        => $role,
            'permissions' => Database::all('SELECT * FROM permissions ORDER BY module, id'),
            'granted'     => array_flip($granted),
        ]);
    }

    public function update(Request $request, string $id): Response
    {
        $role = $this->findEditable((int) $id);
        $selected = array_values(array_unique(array_map('intval', (array) $request->input('permissions', []))));

        Database::transaction(static function () use ($role, $selected): void {
            Database::query('DELETE FROM role_permissions WHERE role_id = ?', [$role['id']]);
            if ($selected !== []) {
                Database::query(
                    'INSERT INTO role_permissions (role_id, permission_id) SELECT ?, id FROM permissions WHERE id IN ' . Database::in($selected),
                    array_merge([$role['id']], $selected)
                );
            }
        });

        Session::flash('success', __('roles.saved', ['name' => loc($role, 'name')]));
        return $this->redirect('/roles');
    }

    /** @return array<string,mixed> */
    private function findEditable(int $id): array
    {
        $role = Database::first('SELECT * FROM roles WHERE id = ?', [$id]);
        if ($role === null) {
            throw new HttpException(404);
        }
        if ($role['slug'] === 'super_admin') {
            throw new HttpException(403, __('roles.locked'));
        }
        return $role;
    }
}
