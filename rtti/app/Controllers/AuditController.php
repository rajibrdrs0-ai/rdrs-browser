<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Export;

final class AuditController extends Controller
{
    public function index(): void
    {
        $this->authorize('audit.view');
        $where = Auth::isHeadOffice() ? '' : ' AND (a.center_id IS NULL OR 1=1' . Auth::scope('a.center_id') . ')' . ' AND (a.center_id IS NOT NULL OR a.user_id IN (SELECT uc.user_id FROM user_centers uc WHERE 1=1' . Auth::scope('uc.center_id') . '))';
        $params = [];
        if ($q = input('q')) {
            $where .= ' AND (a.description LIKE :q OR a.record_id = :q2)';
            $params += ['q' => "%$q%", 'q2' => $q];
        }
        foreach (['module' => 'a.module', 'action' => 'a.action'] as $k => $col) {
            if ($v = input($k)) {
                $where .= " AND $col = :$k";
                $params[$k] = $v;
            }
        }
        if ($u = input_id('user_id')) {
            $where .= ' AND a.user_id = :u';
            $params['u'] = $u;
        }
        if ($from = input_date('from')) {
            $where .= ' AND a.created_at >= :from';
            $params['from'] = $from;
        }
        if ($to = input_date('to')) {
            $where .= ' AND a.created_at < DATE_ADD(:to, INTERVAL 1 DAY)';
            $params['to'] = $to;
        }
        $sql = "SELECT a.*, u.name uname, c.name center FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id LEFT JOIN centers c ON c.id = a.center_id WHERE 1=1 $where ORDER BY a.id DESC";
        if ($fmt = Export::requested()) {
            Export::send($fmt, 'User Activity Log', ['created_at' => 'Time', 'uname' => 'User', 'center' => 'Center', 'module' => 'Module', 'action' => 'Action', 'record_id' => 'Record', 'description' => 'Description', 'ip_address' => 'IP'], DB::all($sql . ' LIMIT 20000', $params));
        }
        $this->render('audit/index', ['title' => 'Audit Log', 'p' => DB::paginate($sql, $params, 50),
            'modules' => DB::column('SELECT DISTINCT module FROM audit_logs ORDER BY module'), 'users' => DB::all('SELECT id, name FROM users ORDER BY name'),
            'breadcrumbs' => ['Administration' => null, 'Audit Log' => null]]);
    }
}
