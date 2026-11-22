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
        ]);
    }
}
