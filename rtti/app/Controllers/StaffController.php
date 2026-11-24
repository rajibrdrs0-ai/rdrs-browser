<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Export;
use App\Core\Sequence;
use App\Core\Upload;

final class StaffController extends Controller
{
    public const STATUSES = ['active' => 'Active', 'on_leave' => 'On leave', 'resigned' => 'Resigned', 'terminated' => 'Terminated', 'retired' => 'Retired'];

    public function index(): void
    {
        $this->authorize('staff.view');
        [$where, $params] = $this->centerFilter('st.center_id');
        if ($q = input('q')) {
            $where .= ' AND (st.employee_code LIKE :q OR st.name LIKE :q2 OR st.mobile LIKE :q3 OR st.nid LIKE :q4)';
            $params += ['q' => "%$q%", 'q2' => "%$q%", 'q3' => "%$q%", 'q4' => "%$q%"];
        }
        if (isset(self::STATUSES[$s = (string)input('status')])) {
            $where .= ' AND st.employment_status = :s';
            $params['s'] = $s;
        }
        if (input('trainer') === '1') {
            $where .= ' AND st.is_trainer = 1';
        }
        if ($d = input_id('designation_id')) {
            $where .= ' AND st.designation_id = :d';
            $params['d'] = $d;
        }
        $sql = "SELECT st.*, dg.name designation, dp.name department, ctr.name center, dt.name district, t.name trade,
                (SELECT COUNT(*) FROM batches b JOIN batch_statuses bs ON bs.id = b.batch_status_id WHERE b.trainer_id = st.id AND bs.code = 'RUNNING' AND b.deleted_at IS NULL) running_batches
            FROM staff st JOIN centers ctr ON ctr.id = st.center_id JOIN districts dt ON dt.id = ctr.district_id LEFT JOIN designations dg ON dg.id = st.designation_id
            LEFT JOIN departments dp ON dp.id = st.department_id LEFT JOIN trades t ON t.id = st.trainer_trade_id WHERE st.deleted_at IS NULL $where ORDER BY ctr.name, st.name";
        if ($fmt = Export::requested()) {
            $this->authorize('staff.export');
            Export::send($fmt, 'Staff Register', ['employee_code' => 'Employee ID', 'name' => 'Name', 'designation' => 'Designation', 'department' => 'Department', 'center' => 'Center',
                'district' => 'District', 'mobile' => 'Mobile', 'email' => 'Email', 'joining_date' => 'Joining Date', 'is_trainer' => ['Trainer', fn($r) => $r['is_trainer'] ? 'Yes' : 'No'],
                'employment_status' => 'Status'], DB::all($sql, $params));
        }
        $this->render('staff/index', ['title' => 'Staff Register', 'p' => DB::paginate($sql, $params), 'statuses' => self::STATUSES,
            'designations' => DB::all('SELECT id, name FROM designations ORDER BY name'), 'breadcrumbs' => ['Administration' => null, 'Staff Register' => null]]);
    }

    private function lists(): array
    {
        return [
            'designations' => DB::all('SELECT id, name FROM designations WHERE is_active = 1 ORDER BY name'),
            'departments' => DB::all('SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name'),
            'centers' => Auth::centerOptions(), 'genders' => DB::all('SELECT id, name FROM genders WHERE is_active = 1'),
            'trades' => DB::all('SELECT id, name FROM trades WHERE is_active = 1 ORDER BY name'), 'statuses' => self::STATUSES,
        ];
    }

    public function create(): void
    {
        $this->authorize('staff.create');
        $this->render('staff/form', ['title' => 'New Staff', 's' => null, 'lists' => $this->lists(), 'breadcrumbs' => ['Staff' => url('staff/index'), 'New' => null]]);
    }

    public function edit(): void
    {
        $this->authorize('staff.edit');
        $s = $this->load();
        $this->render('staff/form', ['title' => 'Edit Staff', 's' => $s, 'lists' => $this->lists(), 'breadcrumbs' => ['Staff' => url('staff/index'), $s['employee_code'] => url('staff/view', ['id' => $s['id']]), 'Edit' => null]]);
    }

    private function load(): array
    {
        $s = DB::row('SELECT st.*, dg.name designation, dp.name department, ctr.name center, g.name gender, t.name trade
            FROM staff st JOIN centers ctr ON ctr.id = st.center_id LEFT JOIN designations dg ON dg.id = st.designation_id LEFT JOIN departments dp ON dp.id = st.department_id
            LEFT JOIN genders g ON g.id = st.gender_id LEFT JOIN trades t ON t.id = st.trainer_trade_id WHERE st.id = :id AND st.deleted_at IS NULL', ['id' => input_int('id')]) ?? $this->notFound('Staff');
        $this->authorizeCenter((int)$s['center_id']);
        return $s;
    }

    public function save(): void
    {
        $this->requirePost();
        $id = input_int('id');
        $this->authorize($id ? 'staff.edit' : 'staff.create');
        $old = $id ? $this->load() : null;
        $_POST['mobile'] = preg_replace('/[^\d]/', '', (string)input('mobile'));
        $this->validate(['name|Name' => 'required|maxlen:150', 'center_id|Center' => 'required', 'mobile|Mobile' => 'required|mobile', 'email|Email' => 'email',
            'joining_date|Joining date' => 'date', 'employment_status|Status' => 'required|in:' . implode(',', array_keys(self::STATUSES)), 'nid|NID' => 'nid']);
        $centerId = $this->resolveCenter(input_id('center_id'));
        if (input('nid') && DB::exists('SELECT 1 FROM staff WHERE nid = :n AND id <> :id AND deleted_at IS NULL', ['n' => input('nid'), 'id' => $id])) {
            $this->fail('Another staff member already has this NID.');
        }
        if (DB::exists('SELECT 1 FROM staff WHERE mobile = :m AND id <> :id AND deleted_at IS NULL', ['m' => input('mobile'), 'id' => $id])) {
            $this->fail('Another staff member already uses this mobile number.');
        }
        $data = [
            'name' => input('name'), 'designation_id' => input_id('designation_id'), 'department_id' => input_id('department_id'), 'center_id' => $centerId,
            'gender_id' => input_id('gender_id'), 'mobile' => input('mobile'), 'email' => nullify(input('email')), 'nid' => nullify(input('nid')), 'joining_date' => input_date('joining_date'),
            'present_address' => nullify(input('present_address')), 'permanent_address' => nullify(input('permanent_address')), 'is_trainer' => input('is_trainer') ? 1 : 0,
            'trainer_trade_id' => input('is_trainer') ? input_id('trainer_trade_id') : null, 'employment_status' => input('employment_status'), 'remarks' => nullify(input('remarks')),
        ];
        try {
            if ($photo = Upload::store('photo', 'photos', Upload::IMAGE, 2)) {
                $data['photo'] = $photo['path'];
            }
        } catch (\RuntimeException $e) {
            $this->fail('Photo: ' . $e->getMessage());
        }
        if ($old) {
            DB::update('staff', $data, 'id = :id', ['id' => $id]);
            Audit::log('staff', 'update', $id, "Staff updated: {$old['employee_code']}", $old, $data, $centerId);
            if ($data['employment_status'] !== 'active' && $old['employment_status'] === 'active') {
                $n = DB::query('UPDATE users SET is_active = 0, session_token = NULL WHERE staff_id = :s', ['s' => $id])->rowCount();
                if ($n) {
                    flash('info', 'The linked user account was deactivated because the staff member is no longer active.');
                }
            }
        } else {
            $id = DB::transaction(function () use ($data) {
                $data['employee_code'] = Sequence::next('employee_id_format', 'EMP-{SEQ4}');
                $data['created_by'] = Auth::id();
                $id = DB::insert('staff', $data);
                Audit::log('staff', 'create', $id, "Staff registered: {$data['employee_code']} {$data['name']}", null, $data, $data['center_id']);
                return $id;
            });
        }
        clear_old();
        flash('success', 'Staff record saved.');
        redirect(url('staff/view', ['id' => $id]));
    }

    public function view(): void
    {
        $this->authorize('staff.view');
        $s = $this->load();
        $batches = DB::all('SELECT b.id, b.batch_code, b.start_date, b.end_date, c.name course, bs.name status, bs.color FROM batches b JOIN courses c ON c.id = b.course_id
            JOIN batch_statuses bs ON bs.id = b.batch_status_id WHERE b.trainer_id = :s AND b.deleted_at IS NULL ORDER BY b.start_date DESC', ['s' => $s['id']]);
        $assets = DB::all("SELECT id, asset_code, name, status FROM assets WHERE assigned_staff_id = :s AND deleted_at IS NULL AND status <> 'disposed'", ['s' => $s['id']]);
        $user = DB::row('SELECT id, username, is_active, last_login_at FROM users WHERE staff_id = :s', ['s' => $s['id']]);
        $classes = (int)DB::value('SELECT COUNT(*) FROM attendance WHERE trainer_id = :s', ['s' => $s['id']]);
        $att = DB::row("SELECT COUNT(*) total, SUM(status IN ('present','late')) present FROM staff_attendance WHERE staff_id = :s AND attendance_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)", ['s' => $s['id']]);
        $this->render('staff/view', ['title' => $s['name'], 's' => $s, 'batches' => $batches, 'assets' => $assets, 'user' => $user, 'classes' => $classes, 'att' => $att,
            'breadcrumbs' => ['Staff' => url('staff/index'), $s['employee_code'] => null]]);
    }

    public function delete(): void
    {
        $this->requirePost();
        $this->authorize('staff.delete');
        $s = $this->load();
        if (DB::exists('SELECT 1 FROM batches WHERE trainer_id = :s', ['s' => $s['id']]) || DB::exists('SELECT 1 FROM users WHERE staff_id = :s', ['s' => $s['id']])) {
            $this->fail('This staff member has history (batches or a user account). Change the employment status instead.');
        }
        DB::update('staff', ['deleted_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $s['id']]);
        Audit::log('staff', 'delete', $s['id'], "Staff deleted: {$s['employee_code']}", $s, null, (int)$s['center_id']);
        flash('success', 'Staff record deleted.');
        redirect(url('staff/index'));
    }
}
