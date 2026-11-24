<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Export;
use App\Core\Upload;

final class CoursesController extends Controller
{
    public function index(): void
    {
        $this->authorize('courses.view');
        $where = '';
        $params = [];
        if ($q = input('q')) {
            $where .= ' AND (c.code LIKE :q OR c.name LIKE :q2)';
            $params += ['q' => "%$q%", 'q2' => "%$q%"];
        }
        foreach (['trade_id' => 'c.trade_id', 'course_status_id' => 'c.course_status_id', 'academic_category_id' => 'c.academic_category_id'] as $k => $col) {
            if ($v = input_id($k)) {
                $where .= " AND $col = :$k";
                $params[$k] = $v;
            }
        }
        $scope = Auth::scope('b.center_id');
        $sql = "SELECT c.*, t.name trade, ac.name category, cs.name status_name, cs.color status_color,
                (SELECT COUNT(*) FROM batches b WHERE b.course_id = c.id AND b.deleted_at IS NULL $scope) batches,
                (SELECT COUNT(*) FROM enrollments e JOIN batches b ON b.id = e.batch_id WHERE e.course_id = c.id AND e.status IN ('active','completed','pending') $scope) students
            FROM courses c JOIN trades t ON t.id = c.trade_id JOIN course_statuses cs ON cs.id = c.course_status_id
            LEFT JOIN academic_categories ac ON ac.id = c.academic_category_id WHERE c.deleted_at IS NULL $where ORDER BY c.name";
        if ($fmt = Export::requested()) {
            $this->authorize('courses.export');
            Export::send($fmt, 'Course List', ['code' => 'Code', 'name' => 'Course', 'category' => 'Category', 'trade' => 'Trade', 'duration' => 'Duration',
                'duration_unit' => 'Unit', 'fee' => 'Fee', 'status_name' => 'Status', 'batches' => 'Batches', 'students' => 'Students'], DB::all($sql, $params));
        }
        $this->render('courses/index', ['title' => 'Courses', 'rows' => DB::all($sql, $params), 'lists' => $this->lists(),
            'breadcrumbs' => ['Academics' => null, 'Courses' => null]]);
    }

    private function lists(): array
    {
        return [
            'trades' => DB::all('SELECT id, name FROM trades WHERE is_active = 1 ORDER BY name'),
            'categories' => DB::all('SELECT id, name FROM academic_categories WHERE is_active = 1 ORDER BY name'),
            'statuses' => DB::all('SELECT id, name FROM course_statuses WHERE is_active = 1 ORDER BY id'),
            'education' => DB::all('SELECT id, name FROM education_levels WHERE is_active = 1 ORDER BY sort_order'),
        ];
    }

    public function create(): void
    {
        $this->authorize('courses.create');
        $this->render('courses/form', ['title' => 'New Course', 'c' => null, 'lists' => $this->lists(), 'breadcrumbs' => ['Courses' => url('courses/index'), 'New' => null]]);
    }

    public function edit(): void
    {
        $this->authorize('courses.edit');
        $c = $this->load();
        $this->render('courses/form', ['title' => 'Edit Course', 'c' => $c, 'lists' => $this->lists(), 'breadcrumbs' => ['Courses' => url('courses/index'), $c['code'] => url('courses/view', ['id' => $c['id']]), 'Edit' => null]]);
    }

    private function load(): array
    {
        return DB::row('SELECT c.*, t.name trade, ac.name category, cs.name status_name, cs.color status_color, el.name min_education
            FROM courses c JOIN trades t ON t.id = c.trade_id JOIN course_statuses cs ON cs.id = c.course_status_id
            LEFT JOIN academic_categories ac ON ac.id = c.academic_category_id LEFT JOIN education_levels el ON el.id = c.min_education_level_id
            WHERE c.id = :id AND c.deleted_at IS NULL', ['id' => input_int('id')]) ?? $this->notFound('Course');
    }

    public function save(): void
    {
        $this->requirePost();
        $id = input_int('id');
        $this->authorize($id ? 'courses.edit' : 'courses.create');
        $old = $id ? $this->load() : null;
        $this->validate([
            'code|Course code' => 'required|maxlen:30|unique:courses,code' . ($id ? ",$id" : ''),
            'name|Course name' => 'required|maxlen:150',
            'trade_id|Trade' => 'required|exists:trades',
            'course_status_id|Status' => 'required|exists:course_statuses',
            'duration|Duration' => 'required|int|min:1',
            'duration_unit|Duration unit' => 'required|in:hours,days,weeks,months',
            'fee|Course fee' => 'required|numeric|min:0',
            'pass_mark_percent|Pass mark' => 'required|numeric|min:0|max:100',
            'min_attendance_percent|Minimum attendance' => 'required|numeric|min:0|max:100',
        ]);
        $data = [
            'code' => strtoupper((string)input('code')), 'name' => input('name'), 'academic_category_id' => input_id('academic_category_id'), 'trade_id' => input_id('trade_id'),
            'description' => nullify(input('description')), 'duration' => input_int('duration'), 'duration_unit' => input('duration_unit'), 'fee' => input_money('fee'),
            'min_education_level_id' => input_id('min_education_level_id'), 'objectives' => nullify(input('objectives')), 'outline' => nullify(input('outline')),
            'pass_mark_percent' => input_money('pass_mark_percent'), 'min_attendance_percent' => input_money('min_attendance_percent'), 'course_status_id' => input_id('course_status_id'),
        ];
        if ($id) {
            DB::update('courses', $data, 'id = :id', ['id' => $id]);
            Audit::log('courses', 'update', $id, "Course updated: {$data['code']}", $old, $data);
            if ((float)$old['fee'] !== (float)$data['fee']) {
                flash('info', 'Fee change applies to new enrollments only. Existing enrollments keep their agreed fee.');
            }
        } else {
            $data['created_by'] = Auth::id();
            $id = DB::insert('courses', $data);
            Audit::log('courses', 'create', $id, "Course created: {$data['code']} {$data['name']}");
        }
        clear_old();
        flash('success', 'Course saved.');
        redirect(url('courses/view', ['id' => $id]));
    }

    public function view(): void
    {
        $this->authorize('courses.view');
        $c = $this->load();
        $scope = Auth::scope('b.center_id');
        $batches = DB::all("SELECT b.*, ctr.name center, bs.name status, bs.color, st.name trainer,
            (SELECT COUNT(*) FROM enrollments e WHERE e.batch_id = b.id AND e.status IN ('pending','active','completed')) enrolled
            FROM batches b JOIN centers ctr ON ctr.id = b.center_id JOIN batch_statuses bs ON bs.id = b.batch_status_id LEFT JOIN staff st ON st.id = b.trainer_id
            WHERE b.course_id = :c AND b.deleted_at IS NULL $scope ORDER BY b.start_date DESC", ['c' => $c['id']]);
        $students = DB::all("SELECT e.id, e.enrollment_code, e.status, e.result_grade, s.id sid, s.student_code, s.name, b.batch_code, ctr.name center
            FROM enrollments e JOIN students s ON s.id = e.student_id JOIN batches b ON b.id = e.batch_id JOIN centers ctr ON ctr.id = e.center_id
            WHERE e.course_id = :c AND e.status <> 'transferred' $scope ORDER BY e.id DESC LIMIT 300", ['c' => $c['id']]);
        $this->render('courses/view', ['title' => $c['name'], 'c' => $c, 'batches' => $batches, 'students' => $students,
            'subjects' => DB::all('SELECT * FROM course_subjects WHERE course_id = :c ORDER BY sort_order, id', ['c' => $c['id']]),
            'docs' => DB::all('SELECT d.*, u.name uname FROM course_documents d LEFT JOIN users u ON u.id = d.uploaded_by WHERE d.course_id = :c ORDER BY d.id DESC', ['c' => $c['id']]),
            'fees' => DB::all('SELECT f.*, ctr.name center FROM fee_structures f LEFT JOIN centers ctr ON ctr.id = f.center_id WHERE f.course_id = :c AND f.is_active = 1 ORDER BY f.effective_from DESC', ['c' => $c['id']]),
            'breadcrumbs' => ['Courses' => url('courses/index'), $c['code'] => null]]);
    }

    public function subject(): void
    {
        $this->requirePost();
        $this->authorize('courses.edit');
        $c = $this->load();
        if (input('action') === 'delete') {
            $sid = input_int('subject_id');
            if (DB::exists('SELECT 1 FROM examination_results WHERE subject_id = :s', ['s' => $sid])) {
                $this->fail('Marks have been recorded for this subject; it cannot be deleted.');
            }
            DB::query('DELETE FROM course_subjects WHERE id = :id AND course_id = :c', ['id' => $sid, 'c' => $c['id']]);
            Audit::log('courses', 'subject_delete', $c['id'], "Subject removed from {$c['code']}");
        } else {
            $this->validate(['name|Subject' => 'required|maxlen:150', 'theory_full_marks|Theory marks' => 'numeric|min:0', 'practical_full_marks|Practical marks' => 'numeric|min:0']);
            if (input_money('theory_full_marks') + input_money('practical_full_marks') <= 0) {
                $this->fail('Total full marks must be greater than zero.');
            }
            if (DB::exists('SELECT 1 FROM course_subjects WHERE course_id = :c AND name = :n', ['c' => $c['id'], 'n' => input('name')])) {
                $this->fail('This subject already exists in the course.');
            }
            DB::insert('course_subjects', ['course_id' => $c['id'], 'name' => input('name'), 'theory_full_marks' => input_money('theory_full_marks'),
                'practical_full_marks' => input_money('practical_full_marks'), 'sort_order' => (int)DB::value('SELECT COUNT(*) FROM course_subjects WHERE course_id = :c', ['c' => $c['id']])]);
            Audit::log('courses', 'subject_add', $c['id'], "Subject added to {$c['code']}: " . input('name'));
        }
        flash('success', 'Curriculum updated.');
        redirect(url('courses/view', ['id' => $c['id']]) . '#subjects');
    }

    public function document(): void
    {
        $this->requirePost();
        $this->authorize('courses.edit');
        $c = $this->load();
        try {
            $f = Upload::store('file', 'courses', Upload::DOCS, 10);
        } catch (\RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        if (!$f) {
            $this->fail('Choose a file.');
        }
        DB::insert('course_documents', ['course_id' => $c['id'], 'title' => input('title') ?: $f['original'], 'file_path' => $f['path'], 'uploaded_by' => Auth::id()]);
        Audit::log('courses', 'document', $c['id'], "Course document uploaded for {$c['code']}");
        flash('success', 'Document uploaded.');
        redirect(url('courses/view', ['id' => $c['id']]));
    }

    public function delete(): void
    {
        $this->requirePost();
        $this->authorize('courses.delete');
        $c = $this->load();
        if (DB::exists('SELECT 1 FROM batches WHERE course_id = :c AND deleted_at IS NULL', ['c' => $c['id']])) {
            $this->fail('This course has batches. Change its status to Inactive or Archived instead – history is preserved.');
        }
        DB::update('courses', ['deleted_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $c['id']]);
        Audit::log('courses', 'delete', $c['id'], "Course deleted: {$c['code']}", $c);
        flash('success', 'Course deleted.');
        redirect(url('courses/index'));
    }
}
