<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;
use App\Security\FileUpload;
use App\Services\Audit;
use App\Services\Auth;
use App\Services\Rbac;
use App\Services\Sequence;

/**
 * Trainee (student) register: one record per person, with photo, documents
 * and education history. Courses taken are enrolments (EnrollmentController).
 */
final class StudentController extends Controller
{
    public const DOC_TYPES = ['photo', 'nid', 'birth_certificate', 'academic_certificate', 'medical', 'other'];
    public const DISABILITY = ['none', 'visual', 'hearing', 'physical', 'intellectual', 'multiple', 'other'];
    public const RELIGION = ['islam', 'hinduism', 'buddhism', 'christianity', 'other'];
    public const EMPLOYMENT = ['unemployed', 'employed', 'self_employed', 'student', 'other'];

    public function index(Request $request): Response
    {
        [$scopeSql, $params] = Rbac::studentFilter('s');
        $where = ['s.deleted_at IS NULL', $scopeSql];
        $centerId = $this->intOrNull($request->query('center_id'));
        if ($centerId !== null) {
            $where[] = 's.center_id = ?';
            $params[] = $centerId;
        }
        $batchId = $this->intOrNull($request->query('batch_id'));
        if ($batchId !== null) {
            $where[] = 's.id IN (SELECT student_id FROM enrollments WHERE batch_id = ?)';
            $params[] = $batchId;
        }
        $gender = (string) $request->query('gender', '');
        if (in_array($gender, ['male', 'female', 'other'], true)) {
            $where[] = 's.gender = ?';
            $params[] = $gender;
        }
        $q = trim(en_digits((string) $request->query('q', '')));
        if ($q !== '') {
            $where[] = '(s.name_en LIKE ? OR s.name_bn LIKE ? OR s.registration_no LIKE ? OR s.mobile LIKE ? OR s.nid_no = ? OR s.birth_reg_no = ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like, $like, $like, $q, $q);
        }
        $result = $this->paginate(
            "s.id, s.registration_no, s.name_en, s.name_bn, s.gender, s.mobile, s.photo_path, s.status, s.created_at, c.code AS center_code,
             (SELECT GROUP_CONCAT(b.batch_code ORDER BY e.id DESC SEPARATOR ', ') FROM enrollments e JOIN batches b ON b.id = e.batch_id
               WHERE e.student_id = s.id AND e.status IN ('admitted','running')) AS current_batches",
            'FROM students s JOIN training_centers c ON c.id = s.center_id WHERE ' . implode(' AND ', $where),
            $params, 's.id DESC'
        );
        return $this->view('students/index', $result + [
            'centers' => $this->centers(),
            'filters' => ['q' => $q, 'center_id' => $centerId, 'batch_id' => $batchId, 'gender' => $gender],
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $student = $this->find((int) $id);
        [$bSql, $bParams] = Rbac::batchFilter('b');
        $enrollments = Database::all(
            "SELECT e.*, b.batch_code, b.name AS batch_name, b.status AS batch_status, b.start_date, b.end_date, b.center_id AS batch_center,
                    co.name_en AS course_en, co.name_bn AS course_bn, c.code AS center_code,
                    COALESCE(d.paid, 0) AS paid, COALESCE(d.due, e.net_fee) AS due, ({$bSql}) AS in_scope
               FROM enrollments e
               JOIN batches b ON b.id = e.batch_id JOIN courses co ON co.id = b.course_id JOIN training_centers c ON c.id = b.center_id
               LEFT JOIN v_enrollment_dues d ON d.enrollment_id = e.id
              WHERE e.student_id = ? ORDER BY e.id DESC",
            array_merge($bParams, [$student['id']])
        );
        return $this->view('students/show', [
            'student'     => $student,
            'educations'  => Database::all('SELECT * FROM student_educations WHERE student_id = ? ORDER BY passing_year DESC, id DESC', [$student['id']]),
            'documents'   => Database::all('SELECT d.*, u.name AS uploader FROM student_documents d LEFT JOIN users u ON u.id = d.uploaded_by WHERE d.student_id = ? ORDER BY d.id DESC', [$student['id']]),
            'enrollments' => $enrollments,
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->view('students/form', $this->formData(null));
    }

    public function store(Request $request): Response
    {
        $data = $this->validated($request, null);
        $id = Database::transaction(function () use ($data, $request): int {
            $code = (string) Database::value('SELECT code FROM training_centers WHERE id = ?', [$data['center_id']]);
            $year = date('Y');
            $data['registration_no'] = $code . '-' . $year . '-' . str_pad((string) Sequence::next('student:' . $code, $year), 5, '0', STR_PAD_LEFT);
            $columns = array_keys($data);
            Database::query(
                'INSERT INTO students (' . implode(', ', $columns) . ', created_by) VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ', ?)',
                array_merge(array_values($data), [Auth::id()])
            );
            $id = Database::lastInsertId();
            $this->storePhoto($request, $id);
            Audit::log('create', 'student', $id, null, $data, $data['center_id']);
            return $id;
        });
        Session::flash('success', __('students.saved', ['name' => $data['name_en']]));
        return $this->redirect('/students/' . $id);
    }

    public function edit(Request $request, string $id): Response
    {
        return $this->view('students/form', $this->formData($this->find((int) $id)));
    }

    public function update(Request $request, string $id): Response
    {
        $student = $this->find((int) $id);
        $data = $this->validated($request, $student);
        Database::transaction(function () use ($student, $data, $request): void {
            $sets = implode(', ', array_map(static fn(string $c): string => "{$c} = ?", array_keys($data)));
            Database::query("UPDATE students SET {$sets}, updated_by = ? WHERE id = ?",
                array_merge(array_values($data), [Auth::id(), $student['id']]));
            $this->storePhoto($request, (int) $student['id']);
            [$old, $new] = Audit::diff($student, $data);
            if ($new !== []) {
                Audit::log('update', 'student', (int) $student['id'], $old, $new, (int) $data['center_id']);
            }
        });
        Session::flash('success', __('students.saved', ['name' => $data['name_en']]));
        return $this->redirect('/students/' . $student['id']);
    }

    /** Serve the photo (only to users who may see the trainee). */
    public function photo(Request $request, string $id): Response
    {
        $student = $this->find((int) $id);
        if ($student['photo_path'] === null) {
            throw new HttpException(404);
        }
        $path = FileUpload::absolutePath((string) $student['photo_path']);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
        return Response::download($path, 'photo-' . $student['registration_no'] . '.' . pathinfo($path, PATHINFO_EXTENSION), $mime, true)
            ->header('Cache-Control', 'private, max-age=300');
    }

    // ---- documents --------------------------------------------------------

    public function storeDocument(Request $request, string $id): Response
    {
        $student = $this->find((int) $id);
        $data = $this->validate(['doc_type' => 'required|in:' . implode(',', self::DOC_TYPES), 'title' => 'nullable|max:120']);
        $file = $request->file('document');
        if ($file === null) {
            throw new ValidationException(['document' => [__('students.file_required')]]);
        }
        $allowed = (array) Config::get('security.uploads.allowed_documents', []);
        $path = $this->upload($file, 'students/' . $student['id'], $allowed, null, 'document');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file(FileUpload::absolutePath($path)) ?: 'application/octet-stream';
        Database::query('INSERT INTO student_documents (student_id, doc_type, title, file_path, mime_type, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)',
            [$student['id'], $data['doc_type'], ($data['title'] ?? '') !== '' ? trim((string) $data['title']) : null, $path, $mime, Auth::id()]);
        Audit::log('upload', 'student_document', Database::lastInsertId(), null, ['student_id' => $student['id'], 'doc_type' => $data['doc_type']]);
        Session::flash('success', __('students.document_saved'));
        return $this->redirect('/students/' . $student['id'] . '#documents');
    }

    public function document(Request $request, string $id, string $docId): Response
    {
        $student = $this->find((int) $id);
        $doc = Database::first('SELECT * FROM student_documents WHERE id = ? AND student_id = ?', [(int) $docId, $student['id']]) ?? throw new HttpException(404);
        $path = FileUpload::absolutePath((string) $doc['file_path']);
        $name = $student['registration_no'] . '-' . $doc['doc_type'] . '.' . pathinfo($path, PATHINFO_EXTENSION);
        return Response::download($path, $name, (string) $doc['mime_type'], true);
    }

    public function deleteDocument(Request $request, string $id, string $docId): Response
    {
        $student = $this->find((int) $id);
        $doc = Database::first('SELECT * FROM student_documents WHERE id = ? AND student_id = ?', [(int) $docId, $student['id']]) ?? throw new HttpException(404);
        Database::query('DELETE FROM student_documents WHERE id = ?', [$doc['id']]);
        try {
            @unlink(FileUpload::absolutePath((string) $doc['file_path']));
        } catch (HttpException) {
            // file already gone
        }
        Audit::log('delete', 'student_document', (int) $doc['id'], $doc, null);
        Session::flash('success', __('students.document_deleted'));
        return $this->redirect('/students/' . $student['id'] . '#documents');
    }

    // ---- education ----------------------------------------------------------

    public function storeEducation(Request $request, string $id): Response
    {
        $student = $this->find((int) $id);
        $input = $request->all();
        $input['passing_year'] = en_digits(trim((string) ($input['passing_year'] ?? '')));
        $data = $this->validate([
            'exam_name'    => 'required|max:80',
            'institution'  => 'nullable|max:150',
            'board'        => 'nullable|max:60',
            'passing_year' => 'nullable|integer|min_value:1950|max_value:' . date('Y'),
            'result'       => 'nullable|max:30',
        ], $input);
        $n = static fn(string $k): ?string => ($data[$k] ?? '') !== '' ? trim((string) $data[$k]) : null;
        Database::query('INSERT INTO student_educations (student_id, exam_name, institution, board, passing_year, result) VALUES (?, ?, ?, ?, ?, ?)',
            [$student['id'], trim((string) $data['exam_name']), $n('institution'), $n('board'), $n('passing_year'), $n('result')]);
        Session::flash('success', __('students.education_saved'));
        return $this->redirect('/students/' . $student['id'] . '#education');
    }

    public function deleteEducation(Request $request, string $id, string $eduId): Response
    {
        $student = $this->find((int) $id);
        Database::query('DELETE FROM student_educations WHERE id = ? AND student_id = ?', [(int) $eduId, $student['id']]);
        return $this->redirect('/students/' . $student['id'] . '#education');
    }

    // ---- helpers ------------------------------------------------------------

    /** @return array<string,mixed> */
    private function find(int $id): array
    {
        [$sql, $params] = Rbac::studentFilter('s');
        $student = Database::first(
            "SELECT s.*, c.code AS center_code, c.name_en AS center_en, c.name_bn AS center_bn,
                    pu.name_en AS perm_upazila_en, pu.name_bn AS perm_upazila_bn, pd.id AS perm_district_id, pd.name_en AS perm_district_en, pd.name_bn AS perm_district_bn,
                    su.name_en AS pres_upazila_en, su.name_bn AS pres_upazila_bn, sd.id AS pres_district_id, sd.name_en AS pres_district_en, sd.name_bn AS pres_district_bn
               FROM students s JOIN training_centers c ON c.id = s.center_id
               LEFT JOIN upazilas pu ON pu.id = s.permanent_upazila_id LEFT JOIN districts pd ON pd.id = pu.district_id
               LEFT JOIN upazilas su ON su.id = s.present_upazila_id LEFT JOIN districts sd ON sd.id = su.district_id
              WHERE s.id = ? AND s.deleted_at IS NULL AND {$sql}", array_merge([$id], $params));
        if ($student === null) {
            throw new HttpException(Database::value('SELECT id FROM students WHERE id = ?', [$id]) !== null ? 403 : 404);
        }
        return $student;
    }

    private function storePhoto(Request $request, int $studentId): void
    {
        $file = $request->file('photo');
        if ($file === null) {
            return;
        }
        $path = $this->upload($file, 'students/' . $studentId, (array) Config::get('security.uploads.allowed_images', []), 2 * 1024 * 1024, 'photo');
        $old = Database::value('SELECT photo_path FROM students WHERE id = ?', [$studentId]);
        Database::query('UPDATE students SET photo_path = ? WHERE id = ?', [$path, $studentId]);
        if (is_string($old) && $old !== '') {
            try {
                @unlink(FileUpload::absolutePath($old));
            } catch (HttpException) {
            }
        }
    }

    /**
     * Store an upload; a rejected file (type/size) becomes a normal form error
     * so the user returns to the form instead of an error page.
     */
    private function upload(array $file, string $folder, array $allowed, ?int $maxBytes, string $field): string
    {
        try {
            return FileUpload::store($file, $folder, $allowed, $maxBytes);
        } catch (HttpException $e) {
            if ($e->getStatusCode() >= 500) {
                throw $e;
            }
            $msg = $e->getStatusCode() === 413 ? __('students.file_too_large') : __('students.file_type');
            throw new ValidationException([$field => [$msg]]);
        }
    }

    /** @return array<string,mixed> */
    private function formData(?array $student): array
    {
        return [
            'student'   => $student,
            'centers'   => $this->centers(),
            'districts' => Database::all('SELECT id, name_en, name_bn FROM districts ORDER BY name_en'),
        ];
    }

    /** @return list<array<string,mixed>> */
    private function centers(): array
    {
        [$sql, $params] = Rbac::centerFilter('c.id');
        return Database::all("SELECT c.id, c.code, c.name_en, c.name_bn FROM training_centers c
                               WHERE c.is_active = 1 AND c.center_type = 'training_center' AND {$sql} ORDER BY c.name_en", $params);
    }

    /** @return array<string,mixed> */
    private function validated(Request $request, ?array $student): array
    {
        $input = $request->all();
        foreach (['mobile', 'father_mobile', 'mother_mobile', 'guardian_mobile'] as $k) {
            $input[$k] = normalize_phone((string) ($input[$k] ?? ''));
        }
        foreach (['nid_no', 'birth_reg_no', 'household_income_monthly'] as $k) {
            $input[$k] = preg_replace('/[\s-]/', '', en_digits(trim((string) ($input[$k] ?? ''))));
        }
        $rules = [
            'center_id'          => 'required|integer',
            'name_en'            => 'required|max:120',
            'name_bn'            => 'nullable|max:120',
            'gender'             => 'required|in:male,female,other',
            'date_of_birth'      => 'required|date',
            'nid_no'             => ['nullable', 'regex:/^(\d{10}|\d{13}|\d{17})$/'],
            'birth_reg_no'       => ['nullable', 'regex:/^\d{17}$/'],
            'mobile'             => 'required|bd_phone',
            'email'              => 'nullable|email|max:150',
            'religion'           => 'nullable|in:' . implode(',', self::RELIGION),
            'marital_status'     => 'nullable|in:single,married,widowed,divorced',
            'father_name'        => 'nullable|max:120',
            'father_mobile'      => 'nullable|bd_phone',
            'mother_name'        => 'nullable|max:120',
            'mother_mobile'      => 'nullable|bd_phone',
            'guardian_name'      => 'nullable|max:120',
            'guardian_mobile'    => 'nullable|bd_phone',
            'guardian_relation'  => 'nullable|max:40',
            'permanent_upazila_id' => 'nullable|integer',
            'permanent_address'  => 'nullable|max:255',
            'present_upazila_id' => 'nullable|integer',
            'present_address'    => 'nullable|max:255',
            'highest_education'  => 'nullable|max:80',
            'disability'         => 'required|in:' . implode(',', self::DISABILITY),
            'household_income_monthly' => 'nullable|decimal:2|min_value:0|max_value:10000000',
            'employment_status_before' => 'nullable|in:' . implode(',', self::EMPLOYMENT),
        ];
        if ($student !== null) {
            $rules['status'] = 'required|in:active,inactive,blacklisted';
        }
        $data = $this->validate($rules, $input);

        $errors = [];
        $centerId = (int) $data['center_id'];
        if (!Rbac::canAccessCenter($centerId)
            || Database::value("SELECT id FROM training_centers WHERE id = ? AND center_type = 'training_center'", [$centerId]) === null) {
            $errors['center_id'][] = __('users.center_not_allowed');
        }
        $age = (new \DateTimeImmutable((string) $data['date_of_birth']))->diff(new \DateTimeImmutable('today'))->y;
        if ((string) $data['date_of_birth'] > date('Y-m-d') || $age < 10 || $age > 80) {
            $errors['date_of_birth'][] = __('students.dob_range');
        }
        if (($data['nid_no'] ?? '') === '' && ($data['birth_reg_no'] ?? '') === '') {
            $errors['nid_no'][] = __('students.identity_required');
        }
        foreach (['nid_no' => 'students.nid', 'birth_reg_no' => 'students.brn'] as $field => $label) {
            if (($data[$field] ?? '') !== '') {
                $dup = Database::first("SELECT id, registration_no FROM students WHERE {$field} = ? AND id <> ?", [$data[$field], $student['id'] ?? 0]);
                if ($dup !== null) {
                    $errors[$field][] = __('students.duplicate', ['field' => __($label), 'reg' => $dup['registration_no']]);
                }
            }
        }
        foreach (['permanent_upazila_id', 'present_upazila_id'] as $field) {
            if (($data[$field] ?? '') !== '' && Database::value('SELECT id FROM upazilas WHERE id = ?', [(int) $data[$field]]) === null) {
                $errors[$field][] = __('validation.in', ['field' => __('centers.upazila')]);
            }
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        $out = [];
        foreach (array_keys($rules) as $key) {
            $value = $data[$key] ?? null;
            $out[$key] = ($value === '' || $value === null) ? null : (is_string($value) ? trim($value) : $value);
        }
        $out['center_id'] = $centerId;
        $out['disability'] = $data['disability'];
        $out['email'] = $out['email'] !== null ? mb_strtolower((string) $out['email']) : null;
        foreach (['permanent_upazila_id', 'present_upazila_id'] as $k) {
            $out[$k] = $out[$k] !== null ? (int) $out[$k] : null;
        }
        $out['is_ethnic_minority'] = (int) (bool) $request->input('is_ethnic_minority', 0);
        return $out;
    }
}
