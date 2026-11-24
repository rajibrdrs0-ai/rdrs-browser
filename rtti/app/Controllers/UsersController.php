<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;

final class UsersController extends Controller
{
    /** Users visible to the current user (center managers see users of their centers only) */
    private function scopeSql(): string
    {
        if (Auth::isHeadOffice()) {
            return '';
        }
        return ' AND u.id IN (SELECT uc.user_id FROM user_centers uc WHERE 1=1' . Auth::scope('uc.center_id') . ')
                 AND u.id NOT IN (SELECT ur.user_id FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE r.all_centers = 1)';
    }

    public function index(): void
    {
        $this->authorize('users.view');
        $where = $this->scopeSql();
        $params = [];
        if ($q = input('q')) {
            $where .= ' AND (u.name LIKE :q OR u.username LIKE :q2 OR u.email LIKE :q3 OR u.mobile LIKE :q4)';
            $params += ['q' => "%$q%", 'q2' => "%$q%", 'q3' => "%$q%", 'q4' => "%$q%"];
        }
        if ($r = input_id('role_id')) {
            $where .= ' AND EXISTS (SELECT 1 FROM user_roles ur WHERE ur.user_id = u.id AND ur.role_id = :r)';
            $params['r'] = $r;
        }
        $sql = "SELECT u.*, (SELECT GROUP_CONCAT(r.name SEPARATOR ', ') FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = u.id) roles,
                (SELECT GROUP_CONCAT(c.code SEPARATOR ', ') FROM user_centers uc JOIN centers c ON c.id = uc.center_id WHERE uc.user_id = u.id) centers
            FROM users u WHERE 1=1 $where ORDER BY u.is_active DESC, u.name";
        $this->render('users/index', ['title' => 'User Accounts', 'p' => DB::paginate($sql, $params), 'roles' => DB::all('SELECT id, name FROM roles ORDER BY name'),
            'breadcrumbs' => ['Administration' => null, 'User Accounts' => null]]);
    }

    /** Roles the current user may grant (never more powerful than themselves) */
    private function grantableRoles(): array
    {
        if (Auth::isSuperAdmin()) {
            return DB::all('SELECT * FROM roles ORDER BY name');
        }
        $mine = Auth::permissions();
        $roles = DB::all("SELECT * FROM roles WHERE slug <> 'super_admin'" . (Auth::isHeadOffice() ? '' : ' AND all_centers = 0') . ' ORDER BY name');
        return array_values(array_filter($roles, function ($r) use ($mine) {
            $perms = DB::column('SELECT p.slug FROM permissions p JOIN role_permissions rp ON rp.permission_id = p.id WHERE rp.role_id = :r', ['r' => $r['id']]);
            return !array_diff($perms, array_keys($mine));
        }));
    }

    private function form(?array $u): void
    {
        $staffId = input_int('staff_id');
        $staff = $staffId ? DB::row('SELECT * FROM staff WHERE id = :id', ['id' => $staffId]) : null;
        $this->render('users/form', ['title' => $u ? 'Edit User' : 'New User', 'u' => $u, 'staffPre' => $staff,
            'roles' => $this->grantableRoles(), 'centers' => Auth::centerOptions(),
            'userRoles' => $u ? DB::column('SELECT role_id FROM user_roles WHERE user_id = :u', ['u' => $u['id']]) : [],
            'userCenters' => $u ? DB::column('SELECT center_id FROM user_centers WHERE user_id = :u', ['u' => $u['id']]) : ($staff ? [$staff['center_id']] : []),
            'staff' => DB::all("SELECT st.id, CONCAT(st.name, ' (', st.employee_code, ')') name FROM staff st WHERE st.deleted_at IS NULL" . Auth::scope('st.center_id') . ' ORDER BY st.name'),
            'breadcrumbs' => ['Users' => url('users/index'), $u ? $u['username'] : 'New' => null]]);
    }

    public function create(): void
    {
        $this->authorize('users.create');
        $this->form(null);
    }

    public function edit(): void
    {
        $this->authorize('users.edit');
        $this->form($this->load());
    }

    private function load(): array
    {
        $u = DB::row('SELECT u.* FROM users u WHERE u.id = :id' . $this->scopeSql(), ['id' => input_int('id')]) ?? $this->notFound('User');
        return $u;
    }

    public function save(): void
    {
        $this->requirePost();
        $id = input_int('id');
        $this->authorize($id ? 'users.edit' : 'users.create');
        $old = $id ? $this->load() : null;
        $this->validate([
            'name|Full name' => 'required|maxlen:120',
            'username|Username' => 'required|maxlen:60|unique:users,username' . ($id ? ",$id" : ''),
            'email|Email' => 'email|unique:users,email' . ($id ? ",$id" : ''),
            'mobile|Mobile' => 'mobile',
        ]);
        if (!preg_match('/^[A-Za-z0-9_.]{3,60}$/', (string)input('username'))) {
            $this->fail('Username may contain letters, numbers, dot and underscore (3-60 characters).');
        }
        $roleIds = array_map('intval', (array)($_POST['roles'] ?? []));
        $centerIds = array_map('intval', (array)($_POST['centers'] ?? []));
        if (!$roleIds) {
            $this->fail('Assign at least one role.');
        }
        $grantable = array_map('intval', array_column($this->grantableRoles(), 'id'));
        if (array_diff($roleIds, $grantable)) {
            $this->deny('You cannot grant a role with more permissions than you have.');
        }
        $allCenterRole = (bool)DB::value('SELECT COUNT(*) FROM roles WHERE all_centers = 1 AND id IN (' . DB::inInts($roleIds) . ')');
        if (!$allCenterRole && !$centerIds) {
            $this->fail('Center-level users must be assigned to at least one center.');
        }
        foreach ($centerIds as $c) {
            $this->authorizeCenter($c);
        }
        if ($id === Auth::id() && !input('is_active')) {
            $this->fail('You cannot deactivate your own account.');
        }
        $data = ['name' => input('name'), 'username' => input('username'), 'email' => nullify(input('email')), 'mobile' => nullify(input('mobile')),
            'staff_id' => input_id('staff_id'), 'default_center_id' => $centerIds[0] ?? Auth::defaultCenterId(), 'is_active' => input('is_active') ? 1 : 0];
        $password = (string)($_POST['password'] ?? '');
        if (!$id || $password !== '') {
            if ($msg = AuthController::passwordProblem($password)) {
                $this->fail($msg);
            }
            $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            $data['must_change_password'] = input('must_change') ? 1 : 0;
        }
        if (!$data['is_active']) {
            $data['session_token'] = null;
        }
        $id = DB::transaction(function () use ($id, $data, $roleIds, $centerIds, $old) {
            if ($id) {
                DB::update('users', $data, 'id = :id', ['id' => $id]);
            } else {
                $id = DB::insert('users', $data);
            }
            DB::query('DELETE FROM user_roles WHERE user_id = :u', ['u' => $id]);
            foreach ($roleIds as $r) {
                DB::insert('user_roles', ['user_id' => $id, 'role_id' => $r]);
            }
            DB::query('DELETE FROM user_centers WHERE user_id = :u', ['u' => $id]);
            foreach (array_unique($centerIds) as $c) {
                DB::insert('user_centers', ['user_id' => $id, 'center_id' => $c]);
            }
            Audit::log('users', $old ? 'update' : 'create', $id, ($old ? 'User updated: ' : 'User created: ') . $data['username'] . ' roles=' . implode(',', $roleIds) . ' centers=' . implode(',', $centerIds), $old, $data);
            return $id;
        });
        clear_old();
        flash('success', 'User account saved.');
        redirect(url('users/index'));
    }

    public function reset(): void
    {
        $this->requirePost();
        $this->authorize('users.reset_password');
        $u = $this->load();
        $temp = 'Rtti@' . random_int(100000, 999999);
        DB::update('users', ['password_hash' => password_hash($temp, PASSWORD_DEFAULT), 'must_change_password' => 1, 'failed_attempts' => 0, 'locked_until' => null, 'session_token' => null], 'id = :id', ['id' => $u['id']]);
        Audit::log('users', 'reset_password', $u['id'], "Password reset for {$u['username']}");
        flash('success', "Temporary password for {$u['username']}: $temp  — give it to the user securely. They must change it at next sign-in.");
        back();
    }

    public function toggle(): void
    {
        $this->requirePost();
        $this->authorize('users.edit');
        $u = $this->load();
        if ((int)$u['id'] === Auth::id()) {
            $this->fail('You cannot deactivate your own account.');
        }
        $new = (int)!$u['is_active'];
        DB::update('users', ['is_active' => $new, 'session_token' => $new ? $u['session_token'] : null, 'locked_until' => null, 'failed_attempts' => 0], 'id = :id', ['id' => $u['id']]);
        Audit::log('users', $new ? 'activate' : 'deactivate', $u['id'], 'User ' . ($new ? 'activated' : 'deactivated') . ": {$u['username']}");
        flash('success', 'User ' . ($new ? 'activated.' : 'deactivated and signed out.'));
        back();
    }

    /** Session management: force sign-out */
    public function logout(): void
    {
        $this->requirePost();
        $this->authorize('users.edit');
        $u = $this->load();
        DB::update('users', ['session_token' => null], 'id = :id', ['id' => $u['id']]);
        Audit::log('users', 'force_logout', $u['id'], "Forced sign-out: {$u['username']}");
        flash('success', "{$u['username']} has been signed out of all sessions.");
        back();
    }

    public function logins(): void
    {
        $this->authorize('users.logs');
        $where = Auth::isHeadOffice() ? '' : ' AND l.user_id IN (SELECT uc.user_id FROM user_centers uc WHERE 1=1' . Auth::scope('uc.center_id') . ')';
        $params = [];
        if ($q = input('q')) {
            $where .= ' AND (l.username LIKE :q OR l.ip_address LIKE :q2)';
            $params += ['q' => "%$q%", 'q2' => "%$q%"];
        }
        if ($from = input_date('from')) {
            $where .= ' AND l.created_at >= :from';
            $params['from'] = $from;
        }
        if ($to = input_date('to')) {
            $where .= ' AND l.created_at < DATE_ADD(:to, INTERVAL 1 DAY)';
            $params['to'] = $to;
        }
        if (input('failed') === '1') {
            $where .= ' AND l.success = 0';
        }
        $this->render('users/logins', ['title' => 'Login History', 'p' => DB::paginate("SELECT l.*, u.name FROM login_logs l LEFT JOIN users u ON u.id = l.user_id WHERE 1=1 $where ORDER BY l.id DESC", $params, 50),
            'breadcrumbs' => ['Users' => url('users/index'), 'Login history' => null]]);
    }
}
