<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Export;
use App\Core\Sequence;
use App\Core\Upload;
use App\Services\EnrollmentService;

final class StudentsController extends Controller
{
    private const STATUSES = ['active' => 'Active', 'inactive' => 'Inactive', 'alumni' => 'Alumni', 'blacklisted' => 'Blacklisted'];

    public function index(): void
    {
        $this->authorize('students.view');
        [$where, $params] = $this->centerFilter('s.center_id');
        if ($q = input('q')) {
            $where .= ' AND (s.student_code LIKE :q OR s.name LIKE :q2 OR s.father_name LIKE :q3 OR s.mobile LIKE :q4 OR s.nid LIKE :q5 OR s.birth_reg_no LIKE :q6)';
            $params += ['q' => "%$q%", 'q2' => "%$q%", 'q3' => "%$q%", 'q4' => "%$q%", 'q5' => "%$q%", 'q6' => "%$q%"];
        }
        if ($from = input_date('from')) {
            $where .= ' AND s.registration_date >= :from';
            $params['from'] = $from;
        }
        if ($to = input_date('to')) {
            $where .= ' AND s.registration_date <= :to';
            $params['to'] = $to;
        }
        if (($st = input('status')) && isset(self::STATUSES[$st])) {
            $where .= ' AND s.status = :st';
            $params['st'] = $st;
        }
        if ($g = input_id('gender_id')) {
            $where .= ' AND s.gender_id = :g';
            $params['g'] = $g;
        }
        if ($c = input_id('course_id')) {
            $where .= " AND EXISTS (SELECT 1 FROM enrollments e WHERE e.student_id = s.id AND e.course_id = :c AND e.status NOT IN ('cancelled','transferred'))";
            $params['c'] = $c;
        }
        $sql = "SELECT s.*, g.name gender, ctr.name center, d.name district, el.name education,
                (SELECT COUNT(*) FROM enrollments e WHERE e.student_id = s.id AND e.status NOT IN ('cancelled','transferred')) courses
            FROM students s JOIN centers ctr ON ctr.id = s.center_id LEFT JOIN genders g ON g.id = s.gender_id
            LEFT JOIN districts d ON d.id = s.district_id LEFT JOIN education_levels el ON el.id = s.education_level_id
            WHERE s.deleted_at IS NULL $where ORDER BY s.id DESC";

        if ($fmt = Export::requested()) {
            $this->authorize('students.export');
            Export::send($fmt, 'Student Register', [
                'student_code' => 'Student ID', 'name' => 'Name', 'father_name' => "Father's Name", 'mother_name' => "Mother's Name",
                'date_of_birth' => 'Date of Birth', 'gender' => 'Gender', 'nid' => 'NID', 'birth_reg_no' => 'Birth Reg.', 'mobile' => 'Mobile',
                'email' => 'Email', 'present_address' => 'Present Address', 'district' => 'District', 'education' => 'Education',
                'occupation' => 'Occupation', 'registration_date' => 'Registration Date', 'center' => 'Center', 'courses' => 'Courses', 'status' => 'Status',
            ], DB::all($sql, $params));
        }
        $this->render('students/index', [
            'title' => 'Student Register', 'p' => DB::paginate($sql, $params),
            'genders' => DB::all('SELECT id, name FROM genders WHERE is_active = 1'),
            'courses' => DB::all('SELECT id, name FROM courses WHERE deleted_at IS NULL ORDER BY name'),
            'statuses' => self::STATUSES, 'breadcrumbs' => ['Students' => null, 'Student Register' => null],
        ]);
    }

    private function formData(): array
    {
        return [
            'genders' => DB::all('SELECT id, name FROM genders WHERE is_active = 1 ORDER BY id'),
            'divisions' => DB::all('SELECT id, name FROM divisions WHERE is_active = 1 ORDER BY name'),
            'districts' => DB::all('SELECT id, name FROM districts WHERE is_active = 1 ORDER BY name'),
            'education' => DB::all('SELECT id, name FROM education_levels WHERE is_active = 1 ORDER BY sort_order'),
            'centers' => Auth::centerOptions(),
            'statuses' => self::STATUSES,
        ];
    }

    public function create(): void
    {
        $this->authorize('students.create');
        $this->render('students/form', $this->formData() + ['title' => 'New Student Registration', 's' => null,
            'breadcrumbs' => ['Students' => url('students/index'), 'New' => null]]);
    }

    public function edit(): void
    {
        $this->authorize('students.edit');
        $s = $this->load(input_int('id'));
        $this->render('students/form', $this->formData() + ['title' => 'Edit Student', 's' => $s,
            'breadcrumbs' => ['Students' => url('students/index'), $s['student_code'] => url('students/view', ['id' => $s['id']]), 'Edit' => null]]);
    }

    private function load(int $id): array
    {
        $s = DB::row('SELECT s.*, g.name gender, ctr.name center, ctr.code center_code, d.name district, u.name upazila, el.name education,
                cu.name created_by_name
            FROM students s JOIN centers ctr ON ctr.id = s.center_id LEFT JOIN genders g ON g.id = s.gender_id
            LEFT JOIN districts d ON d.id = s.district_id LEFT JOIN upazilas u ON u.id = s.upazila_id
            LEFT JOIN education_levels el ON el.id = s.education_level_id LEFT JOIN users cu ON cu.id = s.created_by
            WHERE s.id = :id AND s.deleted_at IS NULL', ['id' => $id]);
        if (!$s) {
            $this->notFound('Student');
        }
        $this->authorizeCenter((int)$s['center_id']);
        return $s;
    }

    /** Returns students that share NID / birth reg / mobile (+ name) with the input */
    private function duplicates(array $d, int $ignoreId = 0): array
    {
        $conds = [];
        $params = ['ig' => $ignoreId];
        if (!empty($d['nid'])) {
            $conds[] = 's.nid = :nid';
            $params['nid'] = $d['nid'];
        }
        if (!empty($d['birth_reg_no'])) {
            $conds[] = 's.birth_reg_no = :brn';
            $params['brn'] = $d['birth_reg_no'];
        }
        if (!empty($d['mobile'])) {
            $conds[] = 's.mobile = :mob';
            $params['mob'] = $d['mobile'];
        }
        if (!$conds) {
            return [];
        }
        // NOTE: duplicate detection is deliberately NOT center-scoped – a person keeps one lifetime ID across all centers.
        return DB::all('SELECT s.id, s.center_id, s.student_code, s.name, s.father_name, s.mobile, s.nid, s.birth_reg_no, c.name center
            FROM students s JOIN centers c ON c.id = s.center_id
            WHERE s.deleted_at IS NULL AND s.id <> :ig AND (' . implode(' OR ', $conds) . ') LIMIT 10', $params);
    }

    /** AJAX duplicate check used by the registration form */
    public function checkdup(): void
    {
        $this->authorizeAny(['students.create', 'students.edit']);
        $d = ['nid' => preg_replace('/\D/', '', (string)input('nid')), 'birth_reg_no' => preg_replace('/\D/', '', (string)input('birth_reg_no')), 'mobile' => $this->normMobile((string)input('mobile'))];
        $rows = $this->duplicates($d, input_int('id'));
        foreach ($rows as &$r) {
            $r['url'] = Auth::canAccessCenter((int)$r['center_id']) ? url('students/view', ['id' => $r['id']]) : '';
            $r['same_nid'] = $d['nid'] && $r['nid'] === $d['nid'];
            $r['same_brn'] = $d['birth_reg_no'] && $r['birth_reg_no'] === $d['birth_reg_no'];
        }
        json_response(['ok' => true, 'duplicates' => $rows]);
    }

    private function normMobile(string $m): string
    {
        $m = preg_replace('/[^\d]/', '', $m);
        if (str_starts_with($m, '880')) {
            $m = substr($m, 2);
        }
        return $m;
    }

    private function collect(?array $existing): array
    {
        $_POST['mobile'] = $this->normMobile((string)input('mobile'));
        $_POST['alt_mobile'] = $this->normMobile((string)input('alt_mobile'));
        $_POST['emergency_contact_mobile'] = $this->normMobile((string)input('emergency_contact_mobile'));
        $_POST['nid'] = preg_replace('/\D/', '', (string)input('nid'));
        $_POST['birth_reg_no'] = preg_replace('/\D/', '', (string)input('birth_reg_no'));
        $this->validate([
            'name|Student name' => 'required|maxlen:150',
            'father_name|Father\'s name' => 'required|maxlen:150',
            'mother_name|Mother\'s name' => 'maxlen:150',
            'date_of_birth|Date of birth' => 'required|date',
            'gender_id|Gender' => 'required|exists:genders',
            'nid|NID' => 'nid',
            'birth_reg_no|Birth registration no.' => 'brn',
            'mobile|Mobile' => 'required|mobile',
            'alt_mobile|Alternative mobile' => 'mobile',
            'emergency_contact_mobile|Emergency contact mobile' => 'mobile',
            'email|Email' => 'email|maxlen:120',
            'registration_date|Registration date' => 'required|date',
            'district_id|District' => 'exists:districts',
            'education_level_id|Education' => 'exists:education_levels',
            'status|Status' => 'in:' . implode(',', array_keys(self::STATUSES)),
        ]);
        $dob = input_date('date_of_birth');
        if ($dob && (age($dob) === '' || (int)age($dob) < 10 || $dob > date('Y-m-d'))) {
            $this->fail('Date of birth looks wrong – the student must be at least 10 years old.');
        }
        if (input_date('registration_date') > date('Y-m-d')) {
            $this->fail('Registration date cannot be in the future.');
        }
        if (!input('nid') && !input('birth_reg_no')) {
            $this->fail('Enter either the National ID or the Birth Registration number.');
        }
        return [
            'name' => input('name'), 'name_bn' => nullify(input('name_bn')), 'father_name' => input('father_name'), 'mother_name' => nullify(input('mother_name')),
            'date_of_birth' => $dob, 'gender_id' => input_id('gender_id'), 'nid' => nullify(input('nid')), 'birth_reg_no' => nullify(input('birth_reg_no')),
            'mobile' => input('mobile'), 'alt_mobile' => nullify(input('alt_mobile')), 'email' => nullify(input('email')),
            'present_address' => nullify(input('present_address')), 'permanent_address' => nullify(input('same_address') ? input('present_address') : input('permanent_address')),
            'district_id' => input_id('district_id'), 'upazila_id' => input_id('upazila_id'), 'education_level_id' => input_id('education_level_id'),
            'qualification_details' => nullify(input('qualification_details')), 'occupation' => nullify(input('occupation')),
            'emergency_contact_name' => nullify(input('emergency_contact_name')), 'emergency_contact_mobile' => nullify(input('emergency_contact_mobile')),
            'registration_date' => input_date('registration_date'), 'remarks' => nullify(input('remarks')),
            'status' => input('status') ?: ($existing['status'] ?? 'active'),
        ];
    }

    private function checkDuplicatesOrFail(array $data, int $ignore = 0): void
    {
        $dups = $this->duplicates($data, $ignore);
        foreach ($dups as $d) {
            if (($data['nid'] && $d['nid'] === $data['nid']) || ($data['birth_reg_no'] && $d['birth_reg_no'] === $data['birth_reg_no'])) {
                $this->fail("A student with the same NID / Birth Registration number already exists: {$d['student_code']} – {$d['name']} ({$d['center']}). "
                    . 'A person keeps one lifetime Student ID – enroll the existing student in the new course instead.');
            }
        }
        if ($dups && !input('confirm_duplicate')) {
            $list = implode(', ', array_map(fn($d) => "{$d['student_code']} {$d['name']}", $dups));
            $this->fail("Possible duplicate – same mobile number used by: $list. If this is a different person (e.g. family member sharing a phone), tick “This is a different person” and save again.");
        }
    }

    public function store(): void
    {
        $this->requirePost();
        $this->authorize('students.create');
        $data = $this->collect(null);
        $data['center_id'] = $this->resolveCenter(input_id('center_id'));
        $this->checkDuplicatesOrFail($data);
        try {
            $photo = Upload::store('photo', 'photos', Upload::IMAGE, 2);
        } catch (\RuntimeException $e) {
            $this->fail('Photo: ' . $e->getMessage());
        }
        $id = DB::transaction(function () use ($data, $photo) {
            $data['student_code'] = Sequence::next('student_id_format', 'RTTI-{YY}-{SEQ6}', ['center' => DB::value('SELECT code FROM centers WHERE id = :c', ['c' => $data['center_id']])]);
            $data['photo'] = $photo['path'] ?? null;
            $data['created_by'] = Auth::id();
            $id = DB::insert('students', $data);
            Audit::log('students', 'create', $id, "Student registered: {$data['student_code']} {$data['name']}", null, $data, $data['center_id']);
            return $id;
        });
        clear_old();
        flash('success', 'Student registered successfully. Student ID: ' . DB::value('SELECT student_code FROM students WHERE id = :id', ['id' => $id]));
        redirect(input('then') === 'enroll' && can('enrollments.create') ? url('enrollments/create', ['student_id' => $id]) : url('students/view', ['id' => $id]));
    }

    public function update(): void
    {
        $this->requirePost();
        $this->authorize('students.edit');
        $old = $this->load(input_int('id'));
        $data = $this->collect($old);
        if (($cid = input_id('center_id')) && $cid !== (int)$old['center_id']) {
            $this->authorizeCenter($cid);
            $data['center_id'] = $cid;
        }
        $this->checkDuplicatesOrFail($data, (int)$old['id']);
        try {
            if ($photo = Upload::store('photo', 'photos', Upload::IMAGE, 2)) {
                $data['photo'] = $photo['path'];
            }
        } catch (\RuntimeException $e) {
            $this->fail('Photo: ' . $e->getMessage());
        }
        $data['updated_by'] = Auth::id();
        DB::update('students', $data, 'id = :id', ['id' => $old['id']]);
        Audit::log('students', 'update', $old['id'], "Student updated: {$old['student_code']}", $old, $data, (int)$old['center_id']);
        clear_old();
        flash('success', 'Student information updated.');
        redirect(url('students/view', ['id' => $old['id']]));
    }

    public function view(): void
    {
        $this->authorize('students.view');
        $s = $this->load(input_int('id'));
        $enrollments = DB::all("SELECT e.*, c.name course, c.code course_code, c.duration, c.duration_unit, b.batch_code, b.name batch, b.start_date, b.end_date,
                bs.name batch_status, bs.color batch_color, ctr.name center,
                (SELECT certificate_no FROM certificates ce WHERE ce.enrollment_id = e.id AND ce.status = 'issued' LIMIT 1) certificate_no,
                (SELECT id FROM certificates ce WHERE ce.enrollment_id = e.id AND ce.status = 'issued' LIMIT 1) certificate_id
            FROM enrollments e JOIN courses c ON c.id = e.course_id JOIN batches b ON b.id = e.batch_id JOIN batch_statuses bs ON bs.id = b.batch_status_id
            JOIN centers ctr ON ctr.id = e.center_id WHERE e.student_id = :s ORDER BY e.enrollment_date DESC, e.id DESC", ['s' => $s['id']]);
        foreach ($enrollments as &$e) {
            $e['attendance'] = EnrollmentService::attendancePercent((int)$e['id']);
        }
        unset($e);
        $payments = can('fees.view') ? DB::all('SELECT f.*, pm.name method, e.enrollment_code, u.name received_by_name FROM fee_collections f
            JOIN payment_methods pm ON pm.id = f.payment_method_id JOIN enrollments e ON e.id = f.enrollment_id JOIN users u ON u.id = f.received_by
            WHERE f.student_id = :s ORDER BY f.payment_date DESC, f.id DESC', ['s' => $s['id']]) : [];
        $documents = can('documents.view') ? DB::all('SELECT d.*, dc.name category FROM student_documents d JOIN document_categories dc ON dc.id = d.document_category_id
            WHERE d.student_id = :s AND d.deleted_at IS NULL ORDER BY d.id DESC', ['s' => $s['id']]) : [];
        $history = DB::all('SELECT a.*, u.name uname FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id WHERE a.module = "students" AND a.record_id = :id ORDER BY a.id DESC LIMIT 20', ['id' => (string)$s['id']]);
        $this->render('students/view', [
            'title' => $s['name'], 's' => $s, 'enrollments' => $enrollments, 'payments' => $payments, 'documents' => $documents, 'history' => $history,
            'docCategories' => DB::all('SELECT id, name FROM document_categories WHERE is_active = 1 ORDER BY name'),
            'breadcrumbs' => ['Students' => url('students/index'), $s['student_code'] => null],
        ]);
    }

    public function delete(): void
    {
        $this->requirePost();
        $this->authorize('students.delete');
        $s = $this->load(input_int('id'));
        $reason = trim((string)input('reason'));
        if (mb_strlen($reason) < 3) {
            $this->fail('A reason is required to delete a student.');
        }
        if (DB::exists("SELECT 1 FROM enrollments WHERE student_id = :s AND status IN ('active','completed','pending')", ['s' => $s['id']])
            || DB::exists("SELECT 1 FROM fee_collections WHERE student_id = :s", ['s' => $s['id']])) {
            $this->fail('This student has enrollments or financial records and cannot be deleted. Set the status to Inactive instead.');
        }
        DB::update('students', ['deleted_at' => date('Y-m-d H:i:s'), 'deleted_by' => Auth::id(), 'status' => 'inactive'], 'id = :id', ['id' => $s['id']]);
        Audit::log('students', 'delete', $s['id'], "Student deleted (soft): {$s['student_code']} {$s['name']} – $reason", $s, null, (int)$s['center_id']);
        flash('success', 'Student record deleted. It remains in the audit trail.');
        redirect(url('students/index'));
    }

    /** Printable ID cards – single (id) or a whole batch (batch_id) */
    public function idcard(): void
    {
        $this->authorize('students.print');
        if ($bid = input_int('batch_id')) {
            $batch = DB::row('SELECT * FROM batches WHERE id = :id', ['id' => $bid]) ?? $this->notFound('Batch');
            $this->authorizeCenter((int)$batch['center_id']);
            $students = DB::all("SELECT s.*, ctr.name center, c.name course, b.batch_code, b.end_date FROM enrollments e JOIN students s ON s.id = e.student_id
                JOIN centers ctr ON ctr.id = e.center_id JOIN courses c ON c.id = e.course_id JOIN batches b ON b.id = e.batch_id
                WHERE e.batch_id = :b AND e.status IN ('active','pending') ORDER BY s.name", ['b' => $bid]);
        } else {
            $s = $this->load(input_int('id'));
            $last = DB::row("SELECT c.name course, b.batch_code, b.end_date FROM enrollments e JOIN courses c ON c.id = e.course_id JOIN batches b ON b.id = e.batch_id
                WHERE e.student_id = :s AND e.status IN ('active','pending','completed') ORDER BY e.id DESC LIMIT 1", ['s' => $s['id']]) ?? [];
            $students = [array_merge($s, $last)];
        }
        Audit::log('students', 'print_idcard', input_int('id') ?: null, 'ID card printed (' . count($students) . ')');
        $this->render('students/idcard', ['title' => 'Student ID Card', 'students' => $students], 'print');
    }
}
