<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Upload;

final class AssignmentsController extends Controller
{
    /** Visible assignments: batch-specific ones in scope, or course-wide ones */
    private function scopeSql(): string
    {
        $s = Auth::scope('bx.center_id');
        return $s === '' ? '' : " AND (a.batch_id IS NULL OR EXISTS (SELECT 1 FROM batches bx WHERE bx.id = a.batch_id $s))";
    }

    public function index(): void
    {
        $this->authorize('assignments.view');
        $where = $this->scopeSql();
        $params = [];
        if ($q = input('q')) {
            $where .= ' AND a.title LIKE :q';
            $params['q'] = "%$q%";
        }
        if ($c = input_id('course_id')) {
            $where .= ' AND a.course_id = :c';
            $params['c'] = $c;
        }
        $sql = "SELECT a.*, c.name course, b.batch_code, u.name creator,
                (SELECT COUNT(*) FROM assignment_submissions s WHERE s.assignment_id = a.id AND s.status <> 'pending') submitted,
                (SELECT COUNT(*) FROM assignment_submissions s WHERE s.assignment_id = a.id AND s.status = 'evaluated') evaluated
            FROM assignments a JOIN courses c ON c.id = a.course_id LEFT JOIN batches b ON b.id = a.batch_id LEFT JOIN users u ON u.id = a.created_by
            WHERE a.deleted_at IS NULL $where ORDER BY a.deadline DESC";
        $this->render('assignments/index', ['title' => 'Assignments', 'p' => DB::paginate($sql, $params),
            'courses' => DB::all('SELECT id, name FROM courses WHERE deleted_at IS NULL ORDER BY name'),
            'batches' => DB::all("SELECT b.id, CONCAT(b.batch_code, ' – ', c.name) name, b.course_id FROM batches b JOIN courses c ON c.id = b.course_id JOIN batch_statuses bs ON bs.id = b.batch_status_id
                WHERE b.deleted_at IS NULL AND bs.code IN ('RUNNING','UPCOMING')" . Auth::scope('b.center_id') . ' ORDER BY b.batch_code'),
            'breadcrumbs' => ['Academics' => null, 'Assignments' => null]]);
    }

    public function save(): void
    {
        $this->requirePost();
        $this->authorize('assignments.create');
        $this->validate(['title|Title' => 'required|maxlen:200', 'course_id|Course' => 'required|exists:courses', 'batch_id|Batch' => 'exists:batches',
            'deadline|Submission deadline' => 'required', 'total_marks|Total marks' => 'required|numeric|min:1']);
        $batchId = input_id('batch_id');
        if ($batchId) {
            $b = DB::row('SELECT * FROM batches WHERE id = :id', ['id' => $batchId]);
            $this->authorizeCenter((int)$b['center_id']);
            if ((int)$b['course_id'] !== input_int('course_id')) {
                $this->fail('The batch does not belong to the selected course.');
            }
        } elseif (!Auth::isHeadOffice()) {
            $this->fail('Center users must assign to a specific batch.');
        }
        $deadline = date('Y-m-d H:i:s', strtotime((string)input('deadline')) ?: time());
        try {
            $file = Upload::store('attachment', 'assignments', Upload::DOCS, 10);
        } catch (\RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        $id = DB::insert('assignments', ['title' => input('title'), 'course_id' => input_id('course_id'), 'batch_id' => $batchId, 'instructions' => nullify(input('instructions')),
            'deadline' => $deadline, 'total_marks' => input_money('total_marks'), 'attachment' => $file['path'] ?? null, 'created_by' => Auth::id()]);
        Audit::log('assignments', 'create', $id, 'Assignment created: ' . input('title'));
        flash('success', 'Assignment created.');
        redirect(url('assignments/view', ['id' => $id]));
    }

    private function load(): array
    {
        $a = DB::row('SELECT a.*, c.name course, b.batch_code, b.center_id FROM assignments a JOIN courses c ON c.id = a.course_id LEFT JOIN batches b ON b.id = a.batch_id
            WHERE a.id = :id AND a.deleted_at IS NULL', ['id' => input_int('id')]) ?? $this->notFound('Assignment');
        if ($a['batch_id']) {
            $this->authorizeCenter((int)$a['center_id']);
        }
        return $a;
    }

    public function view(): void
    {
        $this->authorize('assignments.view');
        $a = $this->load();
        $batchId = $a['batch_id'] ?: input_int('batch_id');
        $batchOptions = $a['batch_id'] ? [] : DB::all("SELECT b.id, CONCAT(b.batch_code, ' – ', ctr.name) name FROM batches b JOIN centers ctr ON ctr.id = b.center_id WHERE b.deleted_at IS NULL AND b.course_id = :c" . Auth::scope('b.center_id') . ' ORDER BY b.start_date DESC', ['c' => $a['course_id']]);
        $rows = [];
        if ($batchId) {
            $b = DB::row('SELECT * FROM batches WHERE id = :id', ['id' => $batchId]);
            $this->authorizeCenter((int)$b['center_id']);
            $rows = DB::all("SELECT e.id enrollment_id, st.student_code, st.name, s.id sub_id, s.status, s.submitted_at, s.marks, s.feedback, s.file_path, s.submission_text
                FROM enrollments e JOIN students st ON st.id = e.student_id LEFT JOIN assignment_submissions s ON s.enrollment_id = e.id AND s.assignment_id = :a
                WHERE e.batch_id = :b AND e.status IN ('active','completed') ORDER BY st.name", ['a' => $a['id'], 'b' => $batchId]);
        }
        $this->render('assignments/view', ['title' => $a['title'], 'a' => $a, 'rows' => $rows, 'batchId' => $batchId, 'batchOptions' => $batchOptions,
            'breadcrumbs' => ['Assignments' => url('assignments/index'), $a['title'] => null]]);
    }

    /** Record a submission (file / text) on behalf of a student */
    public function submit(): void
    {
        $this->requirePost();
        $this->authorizeAny(['assignments.evaluate', 'assignments.edit']);
        $a = $this->load();
        $eid = input_int('enrollment_id');
        $e = DB::row('SELECT e.*, b.center_id FROM enrollments e JOIN batches b ON b.id = e.batch_id WHERE e.id = :id AND e.course_id = :c', ['id' => $eid, 'c' => $a['course_id']]) ?? $this->notFound('Enrollment');
        $this->authorizeCenter((int)$e['center_id']);
        try {
            $file = Upload::store('file', 'assignments', Upload::DOCS, 10);
        } catch (\RuntimeException $ex) {
            $this->fail($ex->getMessage());
        }
        $when = input('submitted_at') ? date('Y-m-d H:i:s', strtotime((string)input('submitted_at'))) : date('Y-m-d H:i:s');
        $status = $when > $a['deadline'] ? 'late' : 'submitted';
        DB::query('INSERT INTO assignment_submissions (assignment_id, enrollment_id, submitted_at, submission_text, file_path, status)
            VALUES (:a,:e,:t,:x,:f,:s) ON DUPLICATE KEY UPDATE submitted_at = VALUES(submitted_at), submission_text = VALUES(submission_text),
            file_path = COALESCE(VALUES(file_path), file_path), status = IF(status = "evaluated", status, VALUES(status))',
            ['a' => $a['id'], 'e' => $eid, 't' => $when, 'x' => nullify(input('submission_text')), 'f' => $file['path'] ?? null, 's' => $status]);
        Audit::log('assignments', 'submission', $a['id'], "Submission recorded for enrollment #$eid", null, null, (int)$e['center_id']);
        flash('success', 'Submission recorded' . ($status === 'late' ? ' (late).' : '.'));
        redirect(url('assignments/view', ['id' => $a['id'], 'batch_id' => $e['batch_id']]));
    }

    /** Bulk evaluation: marks + feedback */
    public function evaluate(): void
    {
        $this->requirePost();
        $this->authorize('assignments.evaluate');
        $a = $this->load();
        $batchId = input_int('batch_id');
        $b = DB::row('SELECT * FROM batches WHERE id = :id', ['id' => $batchId]) ?? $this->notFound('Batch');
        $this->authorizeCenter((int)$b['center_id']);
        $valid = array_map('intval', DB::column('SELECT id FROM enrollments WHERE batch_id = :b', ['b' => $batchId]));
        $n = 0;
        foreach ((array)($_POST['marks'] ?? []) as $eid => $m) {
            if (!in_array((int)$eid, $valid, true) || $m === '') {
                continue;
            }
            $m = (float)$m;
            if ($m < 0 || $m > (float)$a['total_marks']) {
                flash('warning', "Marks for enrollment #$eid must be between 0 and {$a['total_marks']}.");
                continue;
            }
            DB::query('INSERT INTO assignment_submissions (assignment_id, enrollment_id, marks, feedback, status, evaluated_by, evaluated_at)
                VALUES (:a,:e,:m,:f,"evaluated",:u,NOW()) ON DUPLICATE KEY UPDATE marks = VALUES(marks), feedback = VALUES(feedback), status = "evaluated", evaluated_by = VALUES(evaluated_by), evaluated_at = NOW()',
                ['a' => $a['id'], 'e' => (int)$eid, 'm' => $m, 'f' => nullify($_POST['feedback'][$eid] ?? null), 'u' => Auth::id()]);
            $n++;
        }
        Audit::log('assignments', 'evaluate', $a['id'], "Evaluated $n submissions: {$a['title']}", null, null, (int)$b['center_id']);
        flash('success', "$n evaluation(s) saved.");
        redirect(url('assignments/view', ['id' => $a['id'], 'batch_id' => $batchId]));
    }

    public function delete(): void
    {
        $this->requirePost();
        $this->authorize('assignments.delete');
        $a = $this->load();
        DB::update('assignments', ['deleted_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $a['id']]);
        Audit::log('assignments', 'delete', $a['id'], "Assignment deleted: {$a['title']}");
        flash('success', 'Assignment deleted.');
        redirect(url('assignments/index'));
    }
}
