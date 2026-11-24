<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;

final class NotificationsController extends Controller
{
    /** SQL condition for notifications addressed to the current user */
    public static function condition(): string
    {
        $perms = Auth::permissions();
        $permSql = isset($perms['*']) ? '1=1' : (count($perms) ? "permission_slug IS NULL OR permission_slug IN ('" . implode("','", array_map(fn($p) => preg_replace('/[^a-z_.]/', '', $p), array_keys($perms))) . "')" : 'permission_slug IS NULL');
        return '(user_id = ' . (int)Auth::id() . ' OR (user_id IS NULL AND (' . $permSql . ')' . Auth::scope('center_id') . '))';
    }

    public function index(): void
    {
        $rows = DB::all('SELECT * FROM notifications WHERE ' . self::condition() . ' ORDER BY id DESC LIMIT 100');
        $this->render('notifications/index', ['title' => 'Notifications', 'rows' => $rows]);
    }

    public function read(): void
    {
        $this->requirePost();
        $id = input_int('id');
        DB::query('UPDATE notifications SET is_read = 1 WHERE ' . self::condition() . ($id ? ' AND id = :id' : ''), $id ? ['id' => $id] : []);
        $n = $id ? DB::row('SELECT link FROM notifications WHERE id = :id', ['id' => $id]) : null;
        redirect($n && $n['link'] ? $n['link'] : url('notifications/index'));
    }
}
