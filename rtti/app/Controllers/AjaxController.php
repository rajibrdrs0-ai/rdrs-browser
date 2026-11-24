<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;

/**
 * Lookup endpoints for dependent dropdowns and Select2.
 */
final class AjaxController extends Controller
{
    public function districts(): void
    {
        json_response(DB::all('SELECT id, name FROM districts WHERE division_id = :p AND is_active = 1 ORDER BY name', ['p' => input_int('parent')]));
    }

    public function upazilas(): void
    {
        json_response(DB::all('SELECT id, name FROM upazilas WHERE district_id = :p AND is_active = 1 ORDER BY name', ['p' => input_int('parent')]));
    }

    public function unions(): void
    {
        json_response(DB::all('SELECT id, name FROM unions WHERE upazila_id = :p AND is_active = 1 ORDER BY name', ['p' => input_int('parent')]));
    }

    public function qualifications(): void
    {
        json_response(DB::all('SELECT id, name FROM qualifications WHERE education_level_id = :p AND is_active = 1 ORDER BY name', ['p' => input_int('parent')]));
    }

    /** Batches open for enrollment for a course (within the user's centers) */
    public function batches(): void
    {
        $this->authorizeAny(['enrollments.view', 'batches.view']);
        $rows = DB::all('SELECT b.id, CONCAT(b.batch_code, " – ", b.name, " (", c.name, ")") AS name
            FROM batches b JOIN centers c ON c.id = b.center_id JOIN batch_statuses bs ON bs.id = b.batch_status_id
            WHERE b.deleted_at IS NULL AND b.course_id = :p AND bs.allows_enrollment = 1' . Auth::scope('b.center_id') . ' ORDER BY b.start_date DESC',
            ['p' => input_int('parent')]);
        json_response($rows);
    }

    /** Batches of a center (any status) for selection lists */
    public function centerbatches(): void
    {
        $this->authorizeAny(['batches.view', 'attendance.view', 'exams.view']);
        $cid = input_int('parent');
        $this->authorizeCenter($cid);
        json_response(DB::all('SELECT id, CONCAT(batch_code, " – ", name) AS name FROM batches WHERE deleted_at IS NULL AND center_id = :c ORDER BY start_date DESC', ['c' => $cid]));
    }

    /** Trainers of a center */
    public function trainers(): void
    {
        $cid = input_int('parent');
        $this->authorizeCenter($cid);
        json_response(DB::all("SELECT id, CONCAT(name, ' (', employee_code, ')') AS name FROM staff WHERE deleted_at IS NULL AND is_trainer = 1 AND employment_status = 'active' AND center_id = :c ORDER BY name", ['c' => $cid]));
    }

    /** Select2 student search */
    public function students(): void
    {
        $this->authorizeAny(['students.view', 'enrollments.create', 'fees.collect']);
        $q = '%' . input('q', '') . '%';
        $rows = DB::all('SELECT s.id, CONCAT(s.student_code, " – ", s.name, " (", s.mobile, ")") AS text FROM students s
            WHERE s.deleted_at IS NULL AND (s.student_code LIKE :q OR s.name LIKE :q2 OR s.mobile LIKE :q3 OR s.nid LIKE :q4)'
            . Auth::scope('s.center_id') . ' ORDER BY s.id DESC LIMIT 20', ['q' => $q, 'q2' => $q, 'q3' => $q, 'q4' => $q]);
        json_response(['results' => $rows]);
    }

    /** Select2 enrollment search (for fee collection) */
    public function enrollments(): void
    {
        $this->authorizeAny(['fees.collect', 'enrollments.view']);
        $q = '%' . input('q', '') . '%';
        $rows = DB::all("SELECT e.id, CONCAT(e.enrollment_code, ' – ', s.name, ' (', s.student_code, ') – ', c.name, ' | Due: ', FORMAT(e.due_amount, 2)) AS text
            FROM enrollments e JOIN students s ON s.id = e.student_id JOIN courses c ON c.id = e.course_id
            WHERE e.status IN ('pending','active','completed') AND (e.enrollment_code LIKE :q OR s.student_code LIKE :q2 OR s.name LIKE :q3 OR s.mobile LIKE :q4)"
            . Auth::scope('e.center_id') . ' ORDER BY e.due_amount DESC, e.id DESC LIMIT 20', ['q' => $q, 'q2' => $q, 'q3' => $q, 'q4' => $q]);
        json_response(['results' => $rows]);
    }
}
