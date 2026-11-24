<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Export;
use App\Core\Sequence;

final class AssetsController extends Controller
{
    public const STATUSES = ['in_use' => 'In use', 'in_store' => 'In store', 'under_maintenance' => 'Under maintenance', 'disposed' => 'Disposed', 'lost' => 'Lost'];
    public const CONDITIONS = ['new' => 'New', 'good' => 'Good', 'fair' => 'Fair', 'poor' => 'Poor', 'damaged' => 'Damaged'];

    public function index(): void
    {
        $this->authorize('assets.view');
        [$where, $params] = $this->centerFilter('a.center_id');
        if ($q = input('q')) {
            $where .= ' AND (a.asset_code LIKE :q OR a.name LIKE :q2 OR a.serial_no LIKE :q3 OR a.supplier LIKE :q4)';
            $params += ['q' => "%$q%", 'q2' => "%$q%", 'q3' => "%$q%", 'q4' => "%$q%"];
        }
        if ($c = input_id('asset_category_id')) {
            $where .= ' AND a.asset_category_id = :c';
            $params['c'] = $c;
        }
        if (isset(self::STATUSES[$s = (string)input('status')])) {
            $where .= ' AND a.status = :s';
            $params['s'] = $s;
        }
        if (input('warranty') === '1') {
            $where .= ' AND a.warranty_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY)';
        }
        $sql = "SELECT a.*, ac.name category, ctr.name center, st.name staff FROM assets a JOIN asset_categories ac ON ac.id = a.asset_category_id
            JOIN centers ctr ON ctr.id = a.center_id LEFT JOIN staff st ON st.id = a.assigned_staff_id WHERE a.deleted_at IS NULL $where ORDER BY a.id DESC";
        $tot = DB::row("SELECT COUNT(*) n, COALESCE(SUM(a.purchase_price),0) value FROM assets a WHERE a.deleted_at IS NULL AND a.status <> 'disposed' $where", $params);
        if ($fmt = Export::requested()) {
            $this->authorize('assets.export');
            Export::send($fmt, 'Asset Register', ['asset_code' => 'Asset ID', 'name' => 'Asset', 'category' => 'Category', 'brand_model' => 'Brand/Model', 'serial_no' => 'Serial No',
                'purchase_date' => 'Purchase Date', 'purchase_price' => 'Price', 'supplier' => 'Supplier', 'center' => 'Center', 'staff' => 'Assigned To',
                'asset_condition' => 'Condition', 'warranty_expiry' => 'Warranty', 'status' => 'Status'], DB::all($sql, $params));
        }
        $this->render('assets/index', ['title' => 'Asset Register', 'p' => DB::paginate($sql, $params), 'tot' => $tot, 'statuses' => self::STATUSES,
            'categories' => DB::all('SELECT id, name FROM asset_categories ORDER BY name'), 'breadcrumbs' => ['Administration' => null, 'Asset Register' => null]]);
    }

    private function lists(?int $centerId): array
    {
        return ['categories' => DB::all('SELECT id, name FROM asset_categories WHERE is_active = 1 ORDER BY name'), 'centers' => Auth::centerOptions(),
            'staff' => DB::all("SELECT st.id, CONCAT(st.name, ' (', st.employee_code, ', ', c.code, ')') name FROM staff st JOIN centers c ON c.id = st.center_id WHERE st.deleted_at IS NULL AND st.employment_status = 'active'" . Auth::scope('st.center_id') . ' ORDER BY st.name'),
            'statuses' => self::STATUSES, 'conditions' => self::CONDITIONS];
    }

    public function create(): void
    {
        $this->authorize('assets.create');
        $this->render('assets/form', ['title' => 'Register Asset', 'a' => null, 'lists' => $this->lists(null), 'breadcrumbs' => ['Assets' => url('assets/index'), 'New' => null]]);
    }

    public function edit(): void
    {
        $this->authorize('assets.edit');
        $a = $this->load();
        $this->render('assets/form', ['title' => 'Edit Asset', 'a' => $a, 'lists' => $this->lists((int)$a['center_id']), 'breadcrumbs' => ['Assets' => url('assets/index'), $a['asset_code'] => url('assets/view', ['id' => $a['id']]), 'Edit' => null]]);
    }

    private function load(): array
    {
        $a = DB::row('SELECT a.*, ac.name category, ctr.name center, ctr.code center_code, st.name staff FROM assets a JOIN asset_categories ac ON ac.id = a.asset_category_id
            JOIN centers ctr ON ctr.id = a.center_id LEFT JOIN staff st ON st.id = a.assigned_staff_id WHERE a.id = :id AND a.deleted_at IS NULL', ['id' => input_int('id')]) ?? $this->notFound('Asset');
        $this->authorizeCenter((int)$a['center_id']);
        return $a;
    }

    public function save(): void
    {
        $this->requirePost();
        $id = input_int('id');
        $this->authorize($id ? 'assets.edit' : 'assets.create');
        $old = $id ? $this->load() : null;
        $this->validate(['name|Asset name' => 'required|maxlen:150', 'asset_category_id|Category' => 'required|exists:asset_categories', 'purchase_date|Purchase date' => 'date',
            'purchase_price|Price' => 'numeric|min:0', 'warranty_expiry|Warranty' => 'date', 'asset_condition|Condition' => 'required|in:' . implode(',', array_keys(self::CONDITIONS)),
            'assigned_staff_id|Assigned employee' => 'exists:staff']);
        if (($sn = input('serial_no')) && DB::exists('SELECT 1 FROM assets WHERE serial_no = :s AND id <> :id AND deleted_at IS NULL', ['s' => $sn, 'id' => $id])) {
            $this->fail('Another asset already has this serial number.');
        }
        $data = ['name' => input('name'), 'asset_category_id' => input_id('asset_category_id'), 'serial_no' => nullify($sn), 'brand_model' => nullify(input('brand_model')),
            'purchase_date' => input_date('purchase_date'), 'purchase_price' => input('purchase_price') === '' ? null : input_money('purchase_price'), 'supplier' => nullify(input('supplier')),
            'assigned_staff_id' => input_id('assigned_staff_id'), 'asset_condition' => input('asset_condition'), 'warranty_expiry' => input_date('warranty_expiry'), 'remarks' => nullify(input('remarks'))];
        if ($old) {
            DB::update('assets', $data, 'id = :id', ['id' => $id]);
            Audit::log('assets', 'update', $id, "Asset updated: {$old['asset_code']}", $old, $data, (int)$old['center_id']);
        } else {
            $centerId = $this->resolveCenter(input_id('center_id'));
            $qty = max(1, min(100, input_int('quantity', 1)));
            $id = DB::transaction(function () use ($data, $centerId, $qty) {
                $first = 0;
                for ($i = 0; $i < $qty; $i++) {
                    $row = $data + ['center_id' => $centerId, 'status' => input('status') && isset(self::STATUSES[input('status')]) ? input('status') : 'in_use', 'created_by' => Auth::id()];
                    if ($i > 0) {
                        $row['serial_no'] = null;
                    }
                    $row['asset_code'] = Sequence::next('asset_code_format', 'AST-{CENTER}-{SEQ5}', ['center' => DB::value('SELECT code FROM centers WHERE id = :c', ['c' => $centerId])]);
                    $aid = DB::insert('assets', $row);
                    DB::insert('asset_assignments', ['asset_id' => $aid, 'movement_type' => 'register', 'to_center_id' => $centerId, 'staff_id' => $row['assigned_staff_id'],
                        'movement_date' => $row['purchase_date'] ?: date('Y-m-d'), 'note' => 'Registered', 'created_by' => Auth::id()]);
                    Audit::log('assets', 'create', $aid, "Asset registered: {$row['asset_code']} {$row['name']}", null, null, $centerId);
                    $first = $first ?: $aid;
                }
                return $first;
            });
            if ($qty > 1) {
                flash('info', "$qty assets registered with consecutive asset IDs.");
            }
        }
        clear_old();
        flash('success', 'Asset saved.');
        redirect(url('assets/view', ['id' => $id]));
    }

    public function view(): void
    {
        $this->authorize('assets.view');
        $a = $this->load();
        $moves = DB::all('SELECT m.*, fc.name from_center, tc.name to_center, st.name staff, u.name uname FROM asset_assignments m LEFT JOIN centers fc ON fc.id = m.from_center_id
            LEFT JOIN centers tc ON tc.id = m.to_center_id LEFT JOIN staff st ON st.id = m.staff_id LEFT JOIN users u ON u.id = m.created_by WHERE m.asset_id = :a ORDER BY m.movement_date, m.id', ['a' => $a['id']]);
        $maint = DB::all('SELECT m.*, u.name uname FROM asset_maintenance m LEFT JOIN users u ON u.id = m.created_by WHERE m.asset_id = :a ORDER BY m.maintenance_date DESC', ['a' => $a['id']]);
        $this->render('assets/view', ['title' => $a['name'], 'a' => $a, 'moves' => $moves, 'maint' => $maint, 'lists' => $this->lists((int)$a['center_id']),
            'allCenters' => DB::all('SELECT id, name FROM centers WHERE is_active = 1 ORDER BY name'),
            'breadcrumbs' => ['Assets' => url('assets/index'), $a['asset_code'] => null]]);
    }

    /** Assign to an employee or transfer to another center */
    public function transfer(): void
    {
        $this->requirePost();
        $this->authorize('assets.transfer');
        $a = $this->load();
        if (in_array($a['status'], ['disposed', 'lost'], true)) {
            $this->fail('Disposed or lost assets cannot be moved.');
        }
        $to = input_id('to_center_id') ?? (int)$a['center_id'];
        if (!DB::exists('SELECT 1 FROM centers WHERE id = :id AND is_active = 1', ['id' => $to])) {
            $this->fail('Invalid destination center.');
        }
        $staff = input_id('staff_id');
        if ($staff && (int)DB::value('SELECT center_id FROM staff WHERE id = :s', ['s' => $staff]) !== $to) {
            $this->fail('The employee does not work at the destination center.');
        }
        $type = $to !== (int)$a['center_id'] ? 'transfer' : ($staff ? 'assign' : 'return');
        DB::transaction(function () use ($a, $to, $staff, $type) {
            DB::update('assets', ['center_id' => $to, 'assigned_staff_id' => $staff, 'status' => $staff ? 'in_use' : ($type === 'return' ? 'in_store' : $a['status'])], 'id = :id', ['id' => $a['id']]);
            DB::insert('asset_assignments', ['asset_id' => $a['id'], 'movement_type' => $type, 'from_center_id' => $a['center_id'], 'to_center_id' => $to, 'staff_id' => $staff,
                'movement_date' => input_date('movement_date') ?? date('Y-m-d'), 'note' => nullify(input('note')), 'created_by' => Auth::id()]);
            Audit::log('assets', $type, $a['id'], "Asset {$a['asset_code']} $type", null, null, (int)$a['center_id']);
        });
        flash('success', 'Asset movement recorded.');
        redirect(Auth::canAccessCenter($to) ? url('assets/view', ['id' => $a['id']]) : url('assets/index'));
    }

    public function maintenance(): void
    {
        $this->requirePost();
        $this->authorize('assets.maintenance');
        $a = $this->load();
        $this->validate(['maintenance_date|Date' => 'required|date', 'description|Description' => 'required|maxlen:255', 'cost|Cost' => 'numeric|min:0', 'next_due_date|Next due' => 'date']);
        DB::insert('asset_maintenance', ['asset_id' => $a['id'], 'maintenance_date' => input_date('maintenance_date'), 'description' => input('description'), 'cost' => input_money('cost'),
            'vendor' => nullify(input('vendor')), 'next_due_date' => input_date('next_due_date'), 'created_by' => Auth::id()]);
        $status = input('status');
        if (in_array($status, ['in_use', 'under_maintenance', 'in_store'], true)) {
            DB::update('assets', ['status' => $status, 'asset_condition' => in_array(input('condition'), array_keys(self::CONDITIONS), true) ? input('condition') : $a['asset_condition']], 'id = :id', ['id' => $a['id']]);
        }
        Audit::log('assets', 'maintenance', $a['id'], "Maintenance recorded for {$a['asset_code']}: " . input('description'), null, null, (int)$a['center_id']);
        flash('success', 'Maintenance recorded.');
        redirect(url('assets/view', ['id' => $a['id']]));
    }

    public function dispose(): void
    {
        $this->requirePost();
        $this->authorize('assets.dispose');
        $a = $this->load();
        $reason = trim((string)input('reason'));
        if (mb_strlen($reason) < 3) {
            $this->fail('A disposal note is required.');
        }
        $status = input('as') === 'lost' ? 'lost' : 'disposed';
        DB::transaction(function () use ($a, $reason, $status) {
            DB::update('assets', ['status' => $status, 'disposal_date' => date('Y-m-d'), 'disposal_note' => $reason, 'assigned_staff_id' => null], 'id = :id', ['id' => $a['id']]);
            DB::insert('asset_assignments', ['asset_id' => $a['id'], 'movement_type' => 'dispose', 'from_center_id' => $a['center_id'], 'movement_date' => date('Y-m-d'), 'note' => "$status: $reason", 'created_by' => Auth::id()]);
            Audit::log('assets', $status, $a['id'], "Asset {$a['asset_code']} marked $status: $reason", null, null, (int)$a['center_id']);
        });
        flash('success', "Asset marked as $status.");
        redirect(url('assets/view', ['id' => $a['id']]));
    }
}
