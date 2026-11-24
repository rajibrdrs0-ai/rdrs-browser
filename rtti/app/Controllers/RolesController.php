<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Services\PermissionSync;

/** Role & permission management (menu-wise Add/Edit/Delete/View/... matrix) */
final class RolesController extends Controller
{
    public function index(): void
    {
        $this->authorize('roles.view');
        $roles = DB::all('SELECT r.*, (SELECT COUNT(*) FROM user_roles ur WHERE ur.role_id = r.id) users, (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = r.id) perms FROM roles r ORDER BY r.id');
        $this->render('roles/index', ['title' => 'Roles & Permissions', 'roles' => $roles, 'breadcrumbs' => ['Administration' => null, 'Roles' => null]]);
    }

    public function edit(): void
    {
        $this->authorize('roles.view');
        $id = input_int('id');
        $role = $id ? (DB::find('roles', $id) ?? $this->notFound('Role')) : null;
        $cfg = require ROOT_PATH . '/config/permissions.php';
        $perms = DB::all('SELECT * FROM permissions ORDER BY id');
        $byModule = [];
        foreach ($perms as $p) {
            $byModule[$p['module']][$p['action']] = $p;
        }
        $granted = $role ? DB::column('SELECT permission_id FROM role_permissions WHERE role_id = :r', ['r' => $role['id']]) : [];
        $actions = [];
        foreach ($cfg['modules'] as $m) {
            foreach ($m['actions'] as $a) {
                $actions[$a] = true;
            }
        }
        $this->render('roles/edit', ['title' => $role ? 'Role: ' . $role['name'] : 'New role', 'role' => $role, 'modules' => $cfg['modules'], 'byModule' => $byModule,
            'granted' => array_map('intval', $granted), 'actions' => array_keys($actions), 'breadcrumbs' => ['Roles' => url('roles/index'), $role['name'] ?? 'New' => null]]);
    }

    public function save(): void
    {
        $this->requirePost();
        $this->authorize('roles.manage');
        $id = input_int('id');
        $role = $id ? (DB::find('roles', $id) ?? $this->notFound('Role')) : null;
        if ($role && $role['slug'] === 'super_admin') {
            $this->fail('The Super Administrator role always has every permission and cannot be edited.');
        }
        $this->validate(['name|Role name' => 'required|maxlen:80']);
        $slug = $role['slug'] ?? strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', (string)input('name')), '_'));
        if (!$role && DB::exists('SELECT 1 FROM roles WHERE slug = :s', ['s' => $slug])) {
            $this->fail('A role with this name already exists.');
        }
        $permIds = array_map('intval', (array)($_POST['perms'] ?? []));
        // A user cannot grant permissions they do not hold
        if (!Auth::isSuperAdmin()) {
            $mine = Auth::permissions();
            $slugs = $permIds ? DB::column('SELECT slug FROM permissions WHERE id IN (' . DB::inInts($permIds) . ')') : [];
            if (array_diff($slugs, array_keys($mine))) {
                $this->deny('You cannot grant permissions you do not have.');
            }
            if (input('all_centers') && !Auth::isHeadOffice()) {
                $this->deny('Only head-office users can create head-office roles.');
            }
        }
        DB::transaction(function () use (&$id, $role, $slug, $permIds) {
            $data = ['name' => input('name'), 'description' => nullify(input('description')), 'all_centers' => input('all_centers') ? 1 : 0];
            if ($role) {
                DB::update('roles', $data, 'id = :id', ['id' => $id]);
            } else {
                $id = DB::insert('roles', $data + ['slug' => $slug]);
            }
            $before = DB::column('SELECT p.slug FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = :r', ['r' => $id]);
            DB::query('DELETE FROM role_permissions WHERE role_id = :r', ['r' => $id]);
            foreach (array_unique($permIds) as $p) {
                DB::query('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (:r, :p)', ['r' => $id, 'p' => $p]);
            }
            $after = DB::column('SELECT p.slug FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = :r', ['r' => $id]);
            Audit::log('roles', $role ? 'update' : 'create', $id, 'Role ' . input('name') . ': +' . implode(',', array_diff($after, $before)) . ' -' . implode(',', array_diff($before, $after)));
        });
        flash('success', 'Role saved. Changes apply to users immediately.');
        redirect(url('roles/edit', ['id' => $id]));
    }

    public function delete(): void
    {
        $this->requirePost();
        $this->authorize('roles.manage');
        $role = DB::find('roles', input_int('id')) ?? $this->notFound('Role');
        if ($role['is_system']) {
            $this->fail('Built-in roles cannot be deleted.');
        }
        if (DB::exists('SELECT 1 FROM user_roles WHERE role_id = :r', ['r' => $role['id']])) {
            $this->fail('Remove this role from all users first.');
        }
        DB::query('DELETE FROM roles WHERE id = :id', ['id' => $role['id']]);
        Audit::log('roles', 'delete', $role['id'], 'Role deleted: ' . $role['name']);
        flash('success', 'Role deleted.');
        redirect(url('roles/index'));
    }

    /** Add any new permissions from config/permissions.php */
    public function sync(): void
    {
        $this->requirePost();
        if (!Auth::isSuperAdmin()) {
            $this->deny('Only the super administrator can synchronise permissions.');
        }
        $r = PermissionSync::run(input('reset') === '1');
        Audit::log('roles', 'sync', null, 'Permissions synchronised' . (input('reset') === '1' ? ' (role defaults restored)' : ''));
        flash('success', "Permissions synchronised ({$r['permissions_added']} new).");
        redirect(url('roles/index'));
    }
}
