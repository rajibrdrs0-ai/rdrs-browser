<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;

/**
 * Config-driven Master Entry CRUD (config/master.php).
 * Every master table supports active/inactive, duplicate validation and audit history.
 */
final class MasterController extends Controller
{
    private array $cfg;

    public function __construct()
    {
        $this->cfg = require ROOT_PATH . '/config/master.php';
    }

    private function resolve(): array
    {
        $g = (string)input('g', 'academic');
        $group = $this->cfg['groups'][$g] ?? null;
        if (!$group) {
            $this->notFound('Master group');
        }
        $t = (string)input('t', $group['tables'][0]);
        if (!in_array($t, $group['tables'], true)) {
            $this->notFound('Master table');
        }
        $perm = $group['perm'] ?? 'master';
        return [$g, $group, $t, $this->cfg['tables'][$t], $perm];
    }

    private function selectRows(array $f): array
    {
        $src = $f[3];
        if (str_starts_with(strtoupper($src), 'SELECT')) {
            return DB::all($src);
        }
        DB::assertIdent($src);
        if ($src === 'centers') {
            return Auth::centerOptions(false);
        }
        $order = DB::value("SHOW COLUMNS FROM `$src` LIKE 'sort_order'") ? 'sort_order, name' : 'name';
        return DB::all("SELECT id, name FROM `$src` ORDER BY $order");
    }

    public function index(): void
    {
        [$g, $group, $t, $def, $perm] = $this->resolve();
        $this->authorize("$perm.view");
        $lookups = [];
        foreach ($def['fields'] as $name => $f) {
            if ($f[1] === 'select') {
                $lookups[$name] = $this->selectRows($f);
            }
        }
        $where = 'WHERE 1=1';
        $params = [];
        if (!empty($def['scoped'])) {
            $where .= Auth::scope('m.' . $def['scoped']);
        }
        if (!empty($def['filter']) && ($fv = input_id('parent'))) {
            $where .= ' AND m.' . $def['filter'] . ' = :pf';
            $params['pf'] = $fv;
        }
        $order = $def['order'] ?? 'name';
        $rows = DB::all("SELECT m.* FROM `$t` m $where ORDER BY " . preg_replace('/[^a-z_, ]/i', '', $order) . ' LIMIT 3000', $params);
        $hasActive = (bool)DB::value("SHOW COLUMNS FROM `$t` LIKE 'is_active'");
        $this->render('master/index', [
            'title' => 'Master Entry – ' . $group['label'], 'g' => $g, 'group' => $group, 't' => $t, 'def' => $def, 'rows' => $rows,
            'lookups' => $lookups, 'tables' => $this->cfg['tables'], 'perm' => $perm, 'hasActive' => $hasActive,
            'breadcrumbs' => ['Master Entry' => null, $group['label'] => null],
        ]);
    }

    public function save(): void
    {
        $this->requirePost();
        [$g, $group, $t, $def, $perm] = $this->resolve();
        $this->authorize("$perm.manage");
        $id = input_int('id');
        $old = $id ? DB::find($t, $id) : null;
        if ($id && !$old) {
            $this->notFound();
        }
        if ($old && !empty($def['scoped'])) {
            $this->authorizeCenter((int)$old[$def['scoped']]);
        }
        $data = [];
        $rules = [];
        foreach ($def['fields'] as $name => $f) {
            [$label, $type] = $f;
            $req = ($f[2] ?? '') === 'required';
            $r = $req ? 'required' : '';
            if ($type === 'checkbox') {
                $data[$name] = !empty($_POST[$name]) ? 1 : 0;
                continue;
            }
            $v = input($name);
            switch ($type) {
                case 'number': $r .= '|int'; $data[$name] = $v === '' ? 0 : (int)$v; break;
                case 'decimal': $r .= '|numeric'; $data[$name] = $v === '' ? ($req ? null : 0) : round((float)str_replace(',', '', (string)$v), 2); break;
                case 'date': $r .= '|date'; $data[$name] = nullify($v); break;
                case 'email': $r .= '|email'; $data[$name] = nullify($v); break;
                case 'select': $data[$name] = input_id($name); break;
                case 'enum':
                    $r .= '|in:' . implode(',', array_keys($f[3]));
                    $data[$name] = nullify($v);
                    break;
                default:
                    $r .= '|maxlen:' . ($type === 'textarea' ? 5000 : 255);
                    if (($f[2] ?? '') === 'mobile') {
                        $r .= '|mobile';
                    }
                    $data[$name] = nullify($v);
            }
            $rules["$name|$label"] = ltrim($r, '|');
        }
        $this->validate($rules);
        if (!empty($def['locked_code']) && $old) {
            $data['code'] = $old['code']; // system codes cannot be changed
        }
        if (isset($data['code']) && is_string($data['code'])) {
            $data['code'] = strtoupper(preg_replace('/\s+/', '_', $data['code']));
        }
        // duplicate checks
        foreach ($def['unique'] ?? [] as $u) {
            $cols = (array)$u;
            $sql = "SELECT id FROM `$t` WHERE " . implode(' AND ', array_map(fn($c) => "`$c` <=> :$c", $cols)) . ($id ? ' AND id <> :id' : '');
            $params = array_intersect_key($data, array_flip($cols)) + ($id ? ['id' => $id] : []);
            if (DB::value($sql, $params)) {
                $this->fail('Duplicate: a record with the same ' . implode(' + ', array_map(fn($c) => $def['fields'][$c][0] ?? $c, $cols)) . ' already exists.');
            }
        }
        if ($t === 'centers') {
            if (!Auth::isHeadOffice()) {
                $this->deny('Only head-office users can create or change centers.');
            }
        }
        if ($t === 'grade_scales' && $data['min_percent'] > $data['max_percent']) {
            $this->fail('Min % cannot be greater than Max %.');
        }
        if ($t === 'financial_years' && $data['start_date'] >= $data['end_date']) {
            $this->fail('End date must be after start date.');
        }
        DB::transaction(function () use ($t, $data, $id, $old, $def) {
            if (in_array($t, ['certificate_templates'], true) && $data['is_default']) {
                DB::query("UPDATE `$t` SET is_default = 0");
            }
            if ($t === 'financial_years' && $data['is_current']) {
                DB::query('UPDATE financial_years SET is_current = 0');
            }
            if ($id) {
                DB::update($t, $data, 'id = :id', ['id' => $id]);
                Audit::log('master', 'update', "$t#$id", ($def['title']) . ' updated: ' . ($data['name'] ?? $data['code'] ?? $id), $old, $data);
            } else {
                $newId = DB::insert($t, $data);
                Audit::log('master', 'create', "$t#$newId", $def['title'] . ' created: ' . ($data['name'] ?? $data['code'] ?? $newId), null, $data);
            }
        });
        flash('success', $def['title'] . ' saved.');
        redirect(url('master/index', ['g' => $g, 't' => $t]));
    }

    public function toggle(): void
    {
        $this->requirePost();
        [$g, $group, $t, $def, $perm] = $this->resolve();
        $this->authorize("$perm.manage");
        $row = DB::find($t, input_int('id')) ?? $this->notFound();
        if (!empty($def['scoped'])) {
            $this->authorizeCenter((int)$row[$def['scoped']]);
        }
        $new = (int)!$row['is_active'];
        DB::update($t, ['is_active' => $new], 'id = :id', ['id' => $row['id']]);
        Audit::log('master', $new ? 'activate' : 'deactivate', "$t#{$row['id']}", $def['title'] . ' ' . ($new ? 'activated' : 'deactivated') . ': ' . ($row['name'] ?? $row['id']));
        flash('success', 'Status updated.');
        redirect(url('master/index', ['g' => $g, 't' => $t]));
    }

    public function delete(): void
    {
        $this->requirePost();
        [$g, $group, $t, $def, $perm] = $this->resolve();
        $this->authorize("$perm.manage");
        $row = DB::find($t, input_int('id')) ?? $this->notFound();
        if (!empty($def['locked_code']) && in_array($row['code'], ['ACTIVE', 'INACTIVE', 'ARCHIVED', 'UPCOMING', 'RUNNING', 'COMPLETED', 'CANCELLED', 'ON_HOLD'], true)) {
            $this->fail('System status codes cannot be deleted. You may rename them instead.');
        }
        try {
            DB::query("DELETE FROM `$t` WHERE id = :id", ['id' => $row['id']]);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                $this->fail('This record is used by other data and cannot be deleted. Deactivate it instead – history is preserved.');
            }
            throw $e;
        }
        Audit::log('master', 'delete', "$t#{$row['id']}", $def['title'] . ' deleted: ' . ($row['name'] ?? $row['id']), $row, null);
        flash('success', 'Record deleted.');
        redirect(url('master/index', ['g' => $g, 't' => $t]));
    }
}
