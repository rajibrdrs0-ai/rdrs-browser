<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Settings;
use App\Services\Backup;

final class SettingsController extends Controller
{
    /** key => [label, type, group, help] */
    public const FIELDS = [
        'institute_name' => ['Institute name', 'text', 'Institute'], 'institute_name_bn' => ['Institute name (বাংলা)', 'text', 'Institute'],
        'institute_short' => ['Short name', 'text', 'Institute'], 'organization_name' => ['Organization', 'text', 'Institute'],
        'institute_address' => ['Address', 'text', 'Institute'], 'institute_phone' => ['Phone', 'text', 'Institute'], 'institute_email' => ['Email', 'text', 'Institute'],
        'institute_website' => ['Website', 'text', 'Institute'],
        'currency' => ['Currency', 'text', 'Regional'], 'currency_symbol' => ['Currency symbol', 'text', 'Regional'],
        'date_format' => ['Date format', 'select:d/m/Y=31/12/2026,d-m-Y=31-12-2026,d M Y=31 Dec 2026,Y-m-d=2026-12-31', 'Regional'],
        'timezone' => ['Time zone', 'select:Asia/Dhaka=Asia/Dhaka', 'Regional'], 'default_language' => ['Default language', 'select:en=English,bn=বাংলা', 'Regional'],
        'student_id_format' => ['Student ID format', 'text', 'Numbering', 'Tokens: {YYYY} {YY} {MM} {CENTER} {SEQn}. Lifetime ID – changing it affects new students only.'],
        'enrollment_id_format' => ['Enrollment ID format', 'text', 'Numbering'], 'receipt_no_format' => ['Receipt number format', 'text', 'Numbering'],
        'voucher_no_format' => ['Expense voucher format', 'text', 'Numbering'], 'certificate_no_format' => ['Certificate number format', 'text', 'Numbering'],
        'batch_code_format' => ['Batch ID format', 'text', 'Numbering', 'Also supports {COURSE}.'], 'employee_id_format' => ['Employee ID format', 'text', 'Numbering'],
        'asset_code_format' => ['Asset ID format', 'text', 'Numbering'],
        'cert_require_pass' => ['Require pass in final exam', 'bool', 'Certificate'], 'cert_require_attendance' => ['Require course minimum attendance', 'bool', 'Certificate'],
        'cert_require_full_payment' => ['Require full fee payment', 'bool', 'Certificate'], 'cert_require_batch_completed' => ['Require batch completed', 'bool', 'Certificate'],
        'enrollment_requires_approval' => ['Enrollments need approval (users without approve permission)', 'bool', 'Workflow'],
        'expense_self_approval' => ['Allow approving own expense vouchers', 'bool', 'Workflow'],
        'receipt_footer' => ['Money receipt footer', 'text', 'Receipt'],
        'max_login_attempts' => ['Max failed logins before lock', 'number', 'Security'], 'lockout_minutes' => ['Lockout minutes', 'number', 'Security'],
        'session_timeout_minutes' => ['Session timeout (minutes)', 'number', 'Security'],
        'backup_keep_days' => ['Keep backups for (days)', 'number', 'Backup'],
    ];

    public function index(): void
    {
        $this->authorize('settings.view');
        $this->render('settings/index', ['title' => 'System Settings', 'fields' => self::FIELDS, 'values' => Settings::all(),
            'backups' => can('settings.backup') ? Backup::list() : [], 'fy' => DB::row('SELECT * FROM financial_years WHERE is_current = 1'),
            'info' => ['PHP' => PHP_VERSION, 'Database' => DB::value('SELECT VERSION()'), 'Upload limit' => ini_get('upload_max_filesize'),
                'Students' => DB::value('SELECT COUNT(*) FROM students'), 'Disk free' => round(@disk_free_space(STORAGE_PATH) / 1073741824, 1) . ' GB'],
            'breadcrumbs' => ['Administration' => null, 'Settings' => null]]);
    }

    public function save(): void
    {
        $this->requirePost();
        $this->authorize('settings.manage');
        $old = Settings::all();
        $changed = [];
        foreach (self::FIELDS as $key => $f) {
            $type = $f[1];
            if ($type === 'bool') {
                $v = !empty($_POST[$key]) ? '1' : '0';
            } elseif (!array_key_exists($key, $_POST)) {
                continue;
            } else {
                $v = trim((string)$_POST[$key]);
                if ($type === 'number') {
                    $v = (string)max(1, (int)$v);
                }
                if (str_starts_with($type, 'select:')) {
                    $opts = array_map(fn($x) => explode('=', $x, 2)[0], explode(',', substr($type, 7)));
                    if (!in_array($v, $opts, true)) {
                        continue;
                    }
                }
                if (str_ends_with($key, '_format') && !preg_match('/\{SEQ\d*\}/', $v)) {
                    $this->fail($f[0] . ' must contain a {SEQn} token, e.g. {SEQ6}.');
                }
            }
            if (($old[$key] ?? null) !== $v) {
                Settings::set($key, mb_substr($v, 0, 1000), strtolower($f[2]));
                $changed[$key] = $v;
            }
        }
        if (!empty($_FILES['logo']['name'])) {
            try {
                $up = \App\Core\Upload::store('logo', 'settings', \App\Core\Upload::IMAGE, 2);
                $ext = pathinfo($up['path'], PATHINFO_EXTENSION);
                $dest = PUBLIC_PATH . '/assets/img/custom-logo.' . $ext;
                copy(STORAGE_PATH . '/uploads/' . $up['path'], $dest);
                Settings::set('institute_logo', 'assets/img/custom-logo.' . $ext . '?v=' . time());
                $changed['institute_logo'] = 'uploaded';
            } catch (\RuntimeException $e) {
                $this->fail('Logo: ' . $e->getMessage());
            }
        }
        Audit::log('settings', 'update', null, 'Settings changed: ' . implode(', ', array_keys($changed)), array_intersect_key($old, $changed), $changed);
        flash('success', $changed ? 'Settings saved.' : 'No changes.');
        redirect(url('settings/index'));
    }

    public function backup(): void
    {
        $this->requirePost();
        $this->authorize('settings.backup');
        @set_time_limit(600);
        $name = Backup::create('manual');
        Audit::log('settings', 'backup', null, "Database backup created: $name");
        flash('success', "Backup created: $name");
        redirect(url('settings/index') . '#backup');
    }

    public function download(): void
    {
        $this->authorize('settings.backup');
        $p = Backup::path((string)input('f')) ?? $this->notFound('Backup');
        Audit::log('settings', 'backup_download', null, 'Backup downloaded: ' . basename($p));
        header('Content-Type: application/octet-stream');
        header('Content-Length: ' . filesize($p));
        header('Content-Disposition: attachment; filename="' . basename($p) . '"');
        readfile($p);
        exit;
    }

    public function restore(): void
    {
        $this->requirePost();
        if (!Auth::isSuperAdmin()) {
            $this->deny('Only the super administrator can restore a backup.');
        }
        if (input('confirm') !== 'RESTORE') {
            $this->fail('Type RESTORE to confirm.');
        }
        @set_time_limit(1800);
        $safety = Backup::create('before_restore');
        try {
            Backup::restore((string)input('f'));
        } catch (\Throwable $e) {
            $this->fail('Restore failed: ' . $e->getMessage() . " A safety backup was taken first: $safety");
        }
        Audit::log('settings', 'restore', null, 'Database restored from ' . input('f') . " (safety backup $safety)");
        flash('success', 'Database restored from ' . input('f') . ". A safety backup of the previous state was saved as $safety.");
        redirect(url('settings/index'));
    }
}
