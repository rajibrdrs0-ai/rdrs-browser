<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\Auth;
use App\Services\Rbac;

/** Role-aware landing page: counts are limited to the centers the user may see. */
final class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();
        [$centerSql, $centerParams] = Rbac::centerFilter('c.id');
        [$userSql, $userParams] = Rbac::centerFilter('center_id');
        [$studentSql, $studentParams] = Rbac::centerFilter('center_id');

        $centers = Database::all(
            "SELECT c.id, c.code, c.name_en, c.name_bn, d.name_en AS district_en, d.name_bn AS district_bn
               FROM training_centers c JOIN districts d ON d.id = c.district_id
              WHERE c.is_active = 1 AND {$centerSql} ORDER BY c.center_type, c.name_en",
            $centerParams
        );

        $stats = [
            'centers'  => count($centers),
            'users'    => Rbac::isGlobal()
                ? (int) Database::value('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL')
                : (int) Database::value("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL AND {$userSql}", $userParams),
            'students' => (int) Database::value("SELECT COUNT(*) FROM students WHERE deleted_at IS NULL AND {$studentSql}", $studentParams),
            'batches'  => (int) Database::value("SELECT COUNT(*) FROM batches WHERE status = 'running' AND {$studentSql}", $studentParams),
        ];

        $finance = null;
        if (Rbac::can('fee.view')) {
            [$pSql, $pParams] = Rbac::centerFilter('p.center_id');
            [$dSql, $dParams] = Rbac::centerFilter('d.center_id');
            $finance = [
                'today'   => (float) Database::value("SELECT COALESCE(SUM(p.amount), 0) FROM fee_payments p WHERE p.status = 'valid' AND p.payment_date = CURRENT_DATE AND {$pSql}", $pParams),
                'due'     => (float) Database::value("SELECT COALESCE(SUM(d.due), 0) FROM v_enrollment_dues d WHERE d.due > 0 AND {$dSql}", $dParams),
                'overdue' => (float) Database::value("SELECT COALESCE(SUM(d.overdue), 0) FROM v_enrollment_dues d WHERE {$dSql}", $dParams),
            ];
        }
        $approvals = 0;
        $me = (int) $user['id'];
        if (Rbac::can('waiver.approve')) {
            [$sql, $p] = Rbac::centerFilter('e.center_id');
            $approvals += (int) Database::value("SELECT COUNT(*) FROM fee_waivers w JOIN enrollments e ON e.id = w.enrollment_id WHERE w.status = 'pending' AND w.requested_by <> ? AND {$sql}", array_merge([$me], $p));
        }
        if (Rbac::can('refund.approve')) {
            [$sql, $p] = Rbac::centerFilter('r.center_id');
            $approvals += (int) Database::value("SELECT COUNT(*) FROM refunds r WHERE r.status = 'pending' AND r.requested_by <> ? AND {$sql}", array_merge([$me], $p));
        }
        if (Rbac::can('expense.approve')) {
            [$sql, $p] = Rbac::centerFilter('x.center_id');
            $approvals += (int) Database::value("SELECT COUNT(*) FROM expenses x WHERE x.status = 'pending' AND x.requested_by <> ? AND {$sql}", array_merge([$me], $p));
        }

        $modules = [];
        foreach (Database::all('SELECT slug, module FROM permissions ORDER BY id') as $p) {
            if (Rbac::can((string) $p['slug'])) {
                $modules[(string) $p['module']] = ($modules[(string) $p['module']] ?? 0) + 1;
            }
        }

        return $this->view('dashboard', [
            'user'      => $user,
            'stats'     => $stats,
            'centers'   => $centers,
            'modules'   => $modules,
            'permCount' => count(Rbac::permissions()),
            'finance'   => $finance,
            'approvals' => $approvals,
        ]);
    }
}
