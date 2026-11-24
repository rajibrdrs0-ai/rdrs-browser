<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Export;
use App\Services\ExamService;

final class ExamsController extends Controller
{
    public const TYPES = ['class_test' => 'Class test', 'midterm' => 'Mid-term', 'final' => 'Final', 'practical' => 'Practical', 'assessment' => 'Assessment'];

    public function index(): void
    {
        $this->authorize('exams.view');
        [$where, $params] = $this->centerFilter('b.center_id');
        if ($bid = input_id('batch_id')) {
            $where .= ' AND x.batch_id = :b';
            $params['b'] = $bid;
        }
        $sql = "SELECT x.*, b.batch_code, c.name course, ctr.name center,
                (SELECT COUNT(DISTINCT r.enrollment_id) FROM examination_results r WHERE r.examination_id = x.id) marked
            FROM examinations x JOIN batches b ON b.id = x.batch_id JOIN courses c ON c.id = b.course_id JOIN centers ctr ON ctr.id = b.center_id
            WHERE b.deleted_at IS NULL $where ORDER BY x.exam_date DESC, x.id DESC";
        $this->render('exams/index', ['title' => 'Examinations', 'p' => DB::paginate($sql, $params), 'types' => self::TYPES,
            'batches' => DB::all("SELECT b.id, CONCAT(b.batch_code, ' – ', c.name) name FROM batches b JOIN courses c ON c.id = b.course_id WHERE b.deleted_at IS NULL" . Auth::scope('b.center_id') . ' ORDER BY b.start_date DESC'),
            'breadcrumbs' => ['Academics' => null, 'Examinations' => null]]);
    }

    public function save(): void
    {
        $this->requirePost();
        $this->authorize('exams.create');
        $this->validate(['batch_id|Batch' => 'required|exists:batches', 'name|Exam name' => 'required|maxlen:150', 'exam_type|Type' => 'required|in:' . implode(',', array_keys(self::TYPES)),
            'exam_date|Exam date' => 'required|date', 'theory_full_marks|Theory full marks' => 'numeric|min:0', 'practical_full_marks|Practical full marks' => 'numeric|min:0']);
        $b = DB::row('SELECT * FROM batches WHERE id = :id', ['id' => input_int('batch_id')]);
        $this->authorizeCenter((int)$b['center_id']);
        $isFinal = !empty($_POST['is_final']) ? 1 : 0;
        if ($isFinal && DB::exists('SELECT 1 FROM examinations WHERE batch_id = :b AND is_final = 1', ['b' => $b['id']])) {
            $this->fail('This batch already has a final examination.');
        }
        $id = DB::insert('examinations', ['batch_id' => $b['id'], 'name' => input('name'), 'exam_type' => input('exam_type'), 'exam_date' => input_date('exam_date'),
            'theory_full_marks' => input_money('theory_full_marks'), 'practical_full_marks' => input_money('practical_full_marks'), 'is_final' => $isFinal, 'created_by' => Auth::id()]);
        Audit::log('exams', 'create', $id, "Exam created for {$b['batch_code']}: " . input('name'), null, null, (int)$b['center_id']);
        flash('success', 'Examination created. Enter marks now.');
        redirect(url('exams/marks', ['id' => $id]));
    }

    private function load(): array
    {
        $x = DB::row('SELECT x.*, b.batch_code, b.center_id, b.course_id, c.name course, c.pass_mark_percent, ctr.name center
            FROM examinations x JOIN batches b ON b.id = x.batch_id JOIN courses c ON c.id = b.course_id JOIN centers ctr ON ctr.id = b.center_id WHERE x.id = :id', ['id' => input_int('id')]) ?? $this->notFound('Examination');
        $this->authorizeCenter((int)$x['center_id']);
        return $x;
    }

    /** Subject-wise marks entry grid */
    public function marks(): void
    {
        $this->authorize('exams.view');
        $x = $this->load();
        $subjects = ExamService::subjects($x);
        $students = DB::all("SELECT e.id, s.student_code, s.name FROM enrollments e JOIN students s ON s.id = e.student_id WHERE e.batch_id = :b AND e.status IN ('active','completed') ORDER BY s.name", ['b' => $x['batch_id']]);
        $marks = [];
        foreach (DB::all('SELECT * FROM examination_results WHERE examination_id = :x', ['x' => $x['id']]) as $r) {
            $marks[$r['enrollment_id']][$r['subject_id']] = $r;
        }
        $this->render('exams/marks', ['title' => 'Marks entry – ' . $x['name'], 'x' => $x, 'subjects' => $subjects, 'students' => $students, 'marks' => $marks, 'types' => self::TYPES,
            'breadcrumbs' => ['Examinations' => url('exams/index'), $x['batch_code'] => url('batches/view', ['id' => $x['batch_id']]), $x['name'] => null]]);
    }

    public function savemarks(): void
    {
        $this->requirePost();
        $this->authorize('exams.marks');
        $x = $this->load();
        if ($x['status'] === 'published' && !can('exams.publish')) {
            $this->fail('Results are already published. Only a user with publish permission can change marks.');
        }
        $subjects = [];
        foreach (ExamService::subjects($x) as $s) {
            $subjects[(int)$s['id']] = $s;
        }
        $valid = array_map('intval', DB::column("SELECT id FROM enrollments WHERE batch_id = :b AND status IN ('active','completed')", ['b' => $x['batch_id']]));
        $errors = [];
        DB::transaction(function () use ($x, $subjects, $valid, &$errors) {
            foreach ((array)($_POST['m'] ?? []) as $eid => $subs) {
                if (!in_array((int)$eid, $valid, true)) {
                    continue;
                }
                foreach ((array)$subs as $sid => $vals) {
                    $sid = (int)$sid;
                    if (!isset($subjects[$sid])) {
                        continue;
                    }
                    $th = ($vals['t'] ?? '') === '' ? null : (float)$vals['t'];
                    $pr = ($vals['p'] ?? '') === '' ? null : (float)$vals['p'];
                    $abs = !empty($vals['a']) ? 1 : 0;
                    if ($th === null && $pr === null && !$abs) {
                        continue;
                    }
                    if ($th !== null && ($th < 0 || $th > (float)$subjects[$sid]['theory_full_marks'])) {
                        $errors[] = "Theory marks out of range for enrollment #$eid ({$subjects[$sid]['name']}).";
                        continue;
                    }
                    if ($pr !== null && ($pr < 0 || $pr > (float)$subjects[$sid]['practical_full_marks'])) {
                        $errors[] = "Practical marks out of range for enrollment #$eid ({$subjects[$sid]['name']}).";
                        continue;
                    }
                    DB::query('INSERT INTO examination_results (examination_id, enrollment_id, subject_id, theory_marks, practical_marks, is_absent, entered_by)
                        VALUES (:x,:e,:s,:t,:p,:a,:u) ON DUPLICATE KEY UPDATE theory_marks = VALUES(theory_marks), practical_marks = VALUES(practical_marks), is_absent = VALUES(is_absent), entered_by = VALUES(entered_by)',
                        ['x' => $x['id'], 'e' => (int)$eid, 's' => $sid, 't' => $abs ? 0 : $th, 'p' => $abs ? 0 : $pr, 'a' => $abs, 'u' => Auth::id()]);
                }
            }
            if ($x['status'] === 'scheduled') {
                DB::update('examinations', ['status' => 'marks_entry'], 'id = :id', ['id' => $x['id']]);
            }
            Audit::log('exams', 'marks', $x['id'], "Marks saved: {$x['name']} ({$x['batch_code']})", null, null, (int)$x['center_id']);
        });
        if ($errors) {
            flash('warning', implode("\n", array_slice($errors, 0, 10)));
        } else {
            flash('success', 'Marks saved.');
        }
        if ($x['status'] === 'published') {
            ExamService::publish((int)$x['id']); // keep published results in sync
        }
        redirect(url('exams/marks', ['id' => $x['id']]));
    }

    public function publish(): void
    {
        $this->requirePost();
        $this->authorize('exams.publish');
        $x = $this->load();
        try {
            $n = ExamService::publish((int)$x['id']);
        } catch (\DomainException $e) {
            $this->fail($e->getMessage());
        }
        flash('success', "Results published for $n trainees." . ($x['is_final'] ? ' Final results were written to each enrollment (used for certificates).' : ''));
        redirect(url('exams/result', ['id' => $x['id']]));
    }

    /** Result sheet + merit list (print / export) */
    public function result(): void
    {
        $this->authorize('exams.view');
        $x = $this->load();
        $rows = ExamService::results($x);
        if (input('merit') === '1') {
            usort($rows, fn($a, $b) => $a['position'] <=> $b['position']);
        }
        if ($fmt = Export::requested()) {
            Export::send($fmt, ($_GET['merit'] ?? '') === '1' ? "Merit List – {$x['name']}" : "Result Sheet – {$x['name']}", [
                'position' => 'Position', 'student_code' => 'Student ID', 'name' => 'Name', 'theory' => 'Theory', 'practical' => 'Practical', 'total' => 'Total',
                'full' => 'Full Marks', 'percent' => '%', 'grade' => 'Grade', 'result' => 'Result',
            ], $rows, ['subtitle' => "{$x['course']} · {$x['batch_code']} · {$x['center']} · " . fmt_date($x['exam_date'])]);
        }
        $this->render('exams/result', ['title' => 'Result sheet', 'x' => $x, 'rows' => $rows,
            'breadcrumbs' => ['Examinations' => url('exams/index'), $x['name'] => url('exams/marks', ['id' => $x['id']]), 'Result' => null]]);
    }
}
