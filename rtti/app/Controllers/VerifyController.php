<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;

/**
 * Public certificate verification (no login). Shows only what is needed to confirm authenticity.
 */
final class VerifyController extends Controller
{
    private function throttle(): bool
    {
        $now = time();
        $hits = array_filter($_SESSION['verify_hits'] ?? [], fn($t) => $t > $now - 60);
        $hits[] = $now;
        $_SESSION['verify_hits'] = $hits;
        return count($hits) > 20;
    }

    private function lookup(string $col, string $val): ?array
    {
        return DB::row("SELECT ce.certificate_no, ce.issue_date, ce.completion_date, ce.grade, ce.status, ce.cancelled_at,
                s.name student, s.father_name, c.name course, c.duration, c.duration_unit, b.start_date, b.end_date, ctr.name center, d.name district
            FROM certificates ce JOIN students s ON s.id = ce.student_id JOIN courses c ON c.id = ce.course_id JOIN batches b ON b.id = ce.batch_id
            JOIN centers ctr ON ctr.id = ce.center_id JOIN districts d ON d.id = ctr.district_id WHERE ce.$col = :v LIMIT 1", ['v' => $val]);
    }

    public function index(): void
    {
        $no = trim((string)input('no', ''));
        $result = null;
        $searched = $no !== '';
        if ($searched) {
            if ($this->throttle()) {
                flash('warning', 'Too many verification attempts. Please wait a minute.');
                $searched = false;
            } else {
                $result = $this->lookup('certificate_no', $no);
            }
        }
        $this->render('verify/index', ['title' => 'Certificate Verification', 'no' => $no, 'result' => $result, 'searched' => $searched], 'guest');
    }

    public function qr(): void
    {
        $t = (string)input('t', '');
        $result = preg_match('/^[a-f0-9]{32}$/', $t) && !$this->throttle() ? $this->lookup('verification_token', $t) : null;
        $this->render('verify/index', ['title' => 'Certificate Verification', 'no' => $result['certificate_no'] ?? '', 'result' => $result, 'searched' => true], 'guest');
    }
}
