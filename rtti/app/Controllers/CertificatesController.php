<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Export;
use App\Services\CertificateService;
use App\Services\EnrollmentService;

final class CertificatesController extends Controller
{
    public function index(): void
    {
        $this->authorize('certificates.view');
        [$where, $params] = $this->centerFilter('ce.center_id');
        if ($q = input('q')) {
            $where .= ' AND (ce.certificate_no LIKE :q OR s.student_code LIKE :q2 OR s.name LIKE :q3)';
            $params += ['q' => "%$q%", 'q2' => "%$q%", 'q3' => "%$q%"];
        }
        if ($from = input_date('from')) {
            $where .= ' AND ce.issue_date >= :from';
            $params['from'] = $from;
        }
        if ($to = input_date('to')) {
            $where .= ' AND ce.issue_date <= :to';
            $params['to'] = $to;
        }
        if (in_array($st = input('status'), ['issued', 'cancelled'], true)) {
            $where .= ' AND ce.status = :st';
            $params['st'] = $st;
        }
        if ($c = input_id('course_id')) {
            $where .= ' AND ce.course_id = :c';
            $params['c'] = $c;
        }
        $sql = "SELECT ce.*, s.student_code, s.name student, c.name course, b.batch_code, ctr.name center
            FROM certificates ce JOIN students s ON s.id = ce.student_id JOIN courses c ON c.id = ce.course_id JOIN batches b ON b.id = ce.batch_id
            JOIN centers ctr ON ctr.id = ce.center_id WHERE 1=1 $where ORDER BY ce.id DESC";
        if ($fmt = Export::requested()) {
            Export::send($fmt, 'Certificates', ['certificate_no' => 'Certificate No', 'issue_date' => 'Issue Date', 'student_code' => 'Student ID', 'student' => 'Student',
                'course' => 'Course', 'batch_code' => 'Batch', 'center' => 'Center', 'grade' => 'Grade', 'status' => 'Status', 'print_count' => 'Prints'], DB::all($sql, $params));
        }
        $this->render('certificates/index', ['title' => 'Certificates', 'p' => DB::paginate($sql, $params),
            'courses' => DB::all('SELECT id, name FROM courses WHERE deleted_at IS NULL ORDER BY name'),
            'batches' => DB::all("SELECT b.id, CONCAT(b.batch_code, ' – ', c.name) name FROM batches b JOIN courses c ON c.id = b.course_id WHERE b.deleted_at IS NULL" . Auth::scope('b.center_id') . ' ORDER BY b.end_date DESC LIMIT 300'),
            'breadcrumbs' => ['Students' => null, 'Certificate' => null]]);
    }

    /** Batch-wise eligibility check & bulk issue */
    public function eligible(): void
    {
        $this->authorize('certificates.create');
        $bid = input_int('batch_id');
        $batch = DB::row('SELECT b.*, c.name course, ctr.name center FROM batches b JOIN courses c ON c.id = b.course_id JOIN centers ctr ON ctr.id = b.center_id WHERE b.id = :id AND b.deleted_at IS NULL', ['id' => $bid]) ?? $this->notFound('Batch');
        $this->authorizeCenter((int)$batch['center_id']);
        $rows = DB::all("SELECT e.*, s.student_code, s.name student FROM enrollments e JOIN students s ON s.id = e.student_id
            WHERE e.batch_id = :b AND e.status IN ('active','completed') ORDER BY s.name", ['b' => $bid]);
        foreach ($rows as &$r) {
            $r['problems'] = CertificateService::problems($r);
            $r['attendance'] = EnrollmentService::attendancePercent((int)$r['id']);
            $r['cert'] = DB::row("SELECT id, certificate_no FROM certificates WHERE enrollment_id = :e AND status = 'issued'", ['e' => $r['id']]);
        }
        unset($r);
        $this->render('certificates/eligible', ['title' => 'Issue Certificates', 'batch' => $batch, 'rows' => $rows,
            'templates' => DB::all('SELECT id, name, is_default FROM certificate_templates WHERE is_active = 1 ORDER BY is_default DESC, name'),
            'breadcrumbs' => ['Certificates' => url('certificates/index'), $batch['batch_code'] => null]]);
    }

    public function issue(): void
    {
        $this->requirePost();
        $this->authorize('certificates.create');
        $ids = array_map('intval', (array)($_POST['enrollment_ids'] ?? []));
        $date = input_date('issue_date') ?? date('Y-m-d');
        $tpl = input_id('template_id');
        if (!$ids) {
            $this->fail('Select at least one eligible student.');
        }
        $ok = 0;
        $errors = [];
        foreach ($ids as $id) {
            try {
                CertificateService::issue($id, $date, $tpl);
                $ok++;
            } catch (\DomainException $e) {
                $errors[] = DB::value('SELECT enrollment_code FROM enrollments WHERE id = :id', ['id' => $id]) . ': ' . $e->getMessage();
            }
        }
        if ($ok) {
            flash('success', "$ok certificate(s) issued.");
        }
        if ($errors) {
            flash('warning', implode("\n", $errors));
        }
        back();
    }

    private function load(int $id): array
    {
        $c = DB::row('SELECT ce.*, s.student_code, s.name student, s.name_bn, s.father_name, s.photo, c.name course, c.duration, c.duration_unit,
                b.batch_code, b.name batch, b.start_date, b.end_date, ctr.name center, d.name district, t.heading, t.body_text,
                t.signatory1_name, t.signatory1_title, t.signatory2_name, t.signatory2_title, iu.name issued_by_name, xu.name cancelled_by_name
            FROM certificates ce JOIN students s ON s.id = ce.student_id JOIN courses c ON c.id = ce.course_id JOIN batches b ON b.id = ce.batch_id
            JOIN centers ctr ON ctr.id = ce.center_id JOIN districts d ON d.id = ctr.district_id LEFT JOIN certificate_templates t ON t.id = ce.template_id
            LEFT JOIN users iu ON iu.id = ce.issued_by LEFT JOIN users xu ON xu.id = ce.cancelled_by WHERE ce.id = :id', ['id' => $id]) ?? $this->notFound('Certificate');
        $this->authorizeCenter((int)$c['center_id']);
        return $c;
    }

    public function view(): void
    {
        $this->authorize('certificates.view');
        $c = $this->load(input_int('id'));
        $prints = DB::all('SELECT p.*, u.name uname FROM certificate_prints p LEFT JOIN users u ON u.id = p.printed_by WHERE p.certificate_id = :c ORDER BY p.id DESC', ['c' => $c['id']]);
        $body = strtr($c['body_text'] ?? 'This is to certify that {student_name} has successfully completed the course {course_name}.', [
            '{student_name}' => $c['student'], '{father_name}' => $c['father_name'], '{student_code}' => $c['student_code'], '{course_name}' => $c['course'],
            '{course_duration}' => $c['duration'] . '-' . rtrim($c['duration_unit'], 's'), '{batch_name}' => $c['batch_code'], '{center_name}' => $c['center'],
            '{district}' => $c['district'], '{start_date}' => fmt_date($c['start_date']), '{end_date}' => fmt_date($c['end_date']), '{grade}' => $c['grade'] ?: 'N/A',
        ]);
        $verifyUrl = absolute_url(url('verify/qr', ['t' => $c['verification_token']]));
        $this->render('certificates/view', ['title' => 'Certificate ' . $c['certificate_no'], 'c' => $c, 'body' => $body, 'prints' => $prints, 'verifyUrl' => $verifyUrl, 'bodyClass' => 'landscape'], 'print');
    }

    /** Records each print (reprints need a reason) */
    public function logprint(): void
    {
        $this->requirePost();
        $this->authorize('certificates.print');
        $c = $this->load(input_int('id'));
        if ($c['status'] !== 'issued') {
            json_response(['ok' => false, 'message' => 'Cancelled certificates cannot be printed.'], 422);
        }
        $reason = trim((string)input('reason'));
        if ((int)$c['print_count'] > 0 && $reason === '') {
            json_response(['ok' => false, 'message' => 'Reprint reason is required.'], 422);
        }
        DB::insert('certificate_prints', ['certificate_id' => $c['id'], 'printed_by' => Auth::id(), 'reason' => $reason ?: 'Original print']);
        DB::query('UPDATE certificates SET print_count = print_count + 1 WHERE id = :id', ['id' => $c['id']]);
        Audit::log('certificates', (int)$c['print_count'] > 0 ? 'reprint' : 'print', $c['id'], "Certificate {$c['certificate_no']} printed" . ($reason ? ": $reason" : ''), null, null, (int)$c['center_id']);
        json_response(['ok' => true]);
    }

    public function cancel(): void
    {
        $this->requirePost();
        $this->authorize('certificates.cancel');
        $c = $this->load(input_int('id'));
        $reason = trim((string)input('reason'));
        if (mb_strlen($reason) < 3) {
            $this->fail('A reason is required.');
        }
        if ($c['status'] !== 'issued') {
            $this->fail('Certificate is already cancelled.');
        }
        DB::update('certificates', ['status' => 'cancelled', 'cancelled_by' => Auth::id(), 'cancelled_at' => date('Y-m-d H:i:s'), 'cancel_reason' => mb_substr($reason, 0, 255)], 'id = :id', ['id' => $c['id']]);
        Audit::log('certificates', 'cancel', $c['id'], "Certificate {$c['certificate_no']} cancelled: $reason", null, null, (int)$c['center_id']);
        flash('success', 'Certificate cancelled. Public verification will now show it as cancelled.');
        redirect(url('certificates/index'));
    }
}
