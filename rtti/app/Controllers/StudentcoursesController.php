<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Export;
use App\Services\EnrollmentService;

/**
 * Student Courses: complete course history of students (one row per enrollment)
 * with attendance, result, completion, certificate and payment status.
 */
final class StudentcoursesController extends Controller
{
    public function index(): void
    {
        $this->authorize('enrollments.view');
        [$where, $params] = $this->centerFilter('e.center_id');
        if ($q = input('q')) {
            $where .= ' AND (s.student_code LIKE :q OR s.name LIKE :q2 OR s.mobile LIKE :q3 OR s.nid LIKE :q4)';
            $params += ['q' => "%$q%", 'q2' => "%$q%", 'q3' => "%$q%", 'q4' => "%$q%"];
        }
        if ($c = input_id('course_id')) {
            $where .= ' AND e.course_id = :c';
            $params['c'] = $c;
        }
        if (in_array($cs = input('cert'), ['issued', 'not_issued'], true)) {
            $where .= ($cs === 'issued' ? ' AND' : ' AND NOT') . " EXISTS (SELECT 1 FROM certificates ce WHERE ce.enrollment_id = e.id AND ce.status = 'issued')";
        }
        $sql = "SELECT e.id, e.enrollment_code, e.status, e.result_status, e.result_grade, e.result_percent, e.payable_amount, e.paid_amount, e.due_amount,
                s.id sid, s.student_code, s.name, s.mobile, c.name course, b.batch_code, b.start_date, b.end_date, ctr.name center,
                (SELECT ROUND(SUM(ad.status IN ('present','late'))*100/COUNT(*),1) FROM attendance_details ad WHERE ad.enrollment_id = e.id) attendance,
                (SELECT certificate_no FROM certificates ce WHERE ce.enrollment_id = e.id AND ce.status = 'issued' LIMIT 1) certificate_no
            FROM enrollments e JOIN students s ON s.id = e.student_id JOIN courses c ON c.id = e.course_id JOIN batches b ON b.id = e.batch_id
            JOIN centers ctr ON ctr.id = e.center_id WHERE s.deleted_at IS NULL AND e.status <> 'transferred' $where ORDER BY s.name, b.start_date";
        if ($fmt = Export::requested()) {
            Export::send($fmt, 'Student Course History', [
                'student_code' => 'Student ID', 'name' => 'Name', 'course' => 'Course', 'batch_code' => 'Batch', 'center' => 'Center',
                'start_date' => 'Start', 'end_date' => 'End', 'attendance' => 'Attendance %', 'result_grade' => 'Grade', 'status' => 'Completion',
                'certificate_no' => 'Certificate', 'due_amount' => 'Due',
            ], DB::all($sql, $params));
        }
        $this->render('studentcourses/index', ['title' => 'Student Courses', 'p' => DB::paginate($sql, $params),
            'courses' => DB::all('SELECT id, name FROM courses WHERE deleted_at IS NULL ORDER BY name'),
            'breadcrumbs' => ['Students' => null, 'Student Courses' => null]]);
    }

    /** Printable complete student profile with course history */
    public function profile(): void
    {
        $this->authorize('students.view');
        $s = DB::row('SELECT s.*, g.name gender, c.name center, d.name district, el.name education FROM students s JOIN centers c ON c.id = s.center_id
            LEFT JOIN genders g ON g.id = s.gender_id LEFT JOIN districts d ON d.id = s.district_id LEFT JOIN education_levels el ON el.id = s.education_level_id
            WHERE s.id = :id AND s.deleted_at IS NULL', ['id' => input_int('id')]) ?? $this->notFound('Student');
        $this->authorizeCenter((int)$s['center_id']);
        $rows = DB::all("SELECT e.*, c.name course, b.batch_code, b.start_date, b.end_date, ctr.name center,
            (SELECT certificate_no FROM certificates ce WHERE ce.enrollment_id = e.id AND ce.status = 'issued' LIMIT 1) certificate_no
            FROM enrollments e JOIN courses c ON c.id = e.course_id JOIN batches b ON b.id = e.batch_id JOIN centers ctr ON ctr.id = e.center_id
            WHERE e.student_id = :s ORDER BY b.start_date", ['s' => $s['id']]);
        foreach ($rows as &$r) {
            $r['attendance'] = EnrollmentService::attendancePercent((int)$r['id']);
        }
        unset($r);
        $this->render('studentcourses/profile', ['title' => 'Student Profile – ' . $s['student_code'], 's' => $s, 'rows' => $rows], 'print');
    }
}
