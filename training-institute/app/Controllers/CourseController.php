<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;
use App\Services\Audit;
use App\Services\FeeStructure;
use App\Services\Rbac;

/**
 * Central course catalogue: course details, curriculum modules and the
 * dated fee structure (all-center default + per-center overrides).
 */
final class CourseController extends Controller
{
    public const TYPES = ['short', 'long', 'nsda_cbt', 'bteb', 'customized'];

    public function index(Request $request): Response
    {
        $where = ['1=1'];
        $params = [];
        $tradeId = $this->intOrNull($request->query('trade_id'));
        if ($tradeId !== null) {
            $where[] = 'c.trade_id = ?';
            $params[] = $tradeId;
        }
        $type = (string) $request->query('course_type', '');
        if (in_array($type, self::TYPES, true)) {
            $where[] = 'c.course_type = ?';
            $params[] = $type;
        }
        $active = (string) $request->query('active', '1');
        if ($active === '1' || $active === '0') {
            $where[] = 'c.is_active = ?';
            $params[] = (int) $active;
        }
        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $where[] = '(c.name_en LIKE ? OR c.name_bn LIKE ? OR c.code LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like, $like);
        }
        [$batchScope, $batchParams] = Rbac::centerFilter('b.center_id');
        $result = $this->paginate(
            "c.*, t.name_en AS trade_en, t.name_bn AS trade_bn,
             (SELECT COUNT(*) FROM course_modules m WHERE m.course_id = c.id) AS module_count,
             (SELECT COUNT(*) FROM batches b WHERE b.course_id = c.id AND b.status IN ('admission_open','running') AND {$batchScope}) AS active_batches",
            'FROM courses c JOIN trades t ON t.id = c.trade_id WHERE ' . implode(' AND ', $where),
            array_merge($batchParams, $params),
            't.name_en, c.name_en'
        );
        return $this->view('courses/index', $result + [
            'trades'  => Database::all('SELECT id, name_en, name_bn FROM trades WHERE is_active = 1 ORDER BY name_en'),
            'filters' => ['q' => $q, 'trade_id' => $tradeId, 'course_type' => $type, 'active' => $active],
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $course = $this->find((int) $id);
        $centerId = $this->intOrNull($request->query('center_id'));
        if ($centerId !== null) {
            Rbac::assertCenter($centerId);
        }
        [$scopeSql, $scopeParams] = Rbac::centerFilter('c.id');
        $centers = Database::all("SELECT c.id, c.code, c.name_en, c.name_bn FROM training_centers c
                                   WHERE c.is_active = 1 AND c.center_type = 'training_center' AND {$scopeSql} ORDER BY c.name_en", $scopeParams);
        $overrideCenters = Database::all(
            "SELECT DISTINCT c.id, c.code, c.name_en, c.name_bn FROM course_fees cf JOIN training_centers c ON c.id = cf.center_id
              WHERE cf.course_id = ? AND {$scopeSql} ORDER BY c.name_en", array_merge([$course['id']], $scopeParams));

        return $this->view('courses/show', [
            'course'          => $course,
            'modules'         => Database::all('SELECT * FROM course_modules WHERE course_id = ? ORDER BY sequence_no', [$course['id']]),
            'feeHeads'        => Database::all('SELECT * FROM fee_heads WHERE is_active = 1 ORDER BY sort_order'),
            'defaultFees'     => FeeStructure::current((int) $course['id'], null),
            'centerFees'      => $centerId !== null ? FeeStructure::current((int) $course['id'], $centerId) : null,
            'centerId'        => $centerId,
            'centers'         => $centers,
            'overrideCenters' => $overrideCenters,
            'feeHistory'      => Database::all(
                'SELECT cf.*, fh.name_en AS head_en, fh.name_bn AS head_bn, c.code AS center_code
                   FROM course_fees cf JOIN fee_heads fh ON fh.id = cf.fee_head_id LEFT JOIN training_centers c ON c.id = cf.center_id
                  WHERE cf.course_id = ? ORDER BY cf.effective_from DESC, cf.id DESC LIMIT 50', [$course['id']]),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->view('courses/form', ['course' => null, 'trades' => $this->trades()]);
    }

    public function store(Request $request): Response
    {
        $data = $this->validated($request, null);
        Database::query(
            'INSERT INTO courses (code, trade_id, name_en, name_bn, course_type, nsqf_level, duration_months, total_hours,
                                  min_qualification, min_age, max_age, min_attendance_percent, pass_mark_percent, description, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)',
            [$data['code'], $data['trade_id'], $data['name_en'], $data['name_bn'], $data['course_type'], $data['nsqf_level'],
             $data['duration_months'], $data['total_hours'], $data['min_qualification'], $data['min_age'], $data['max_age'],
             $data['min_attendance_percent'], $data['pass_mark_percent'], $data['description']]
        );
        $id = Database::lastInsertId();
        Audit::log('create', 'course', $id, null, $data);
        Session::flash('success', __('courses.saved', ['name' => $data['name_en']]));
        return $this->redirect('/courses/' . $id);
    }

    public function edit(Request $request, string $id): Response
    {
        return $this->view('courses/form', ['course' => $this->find((int) $id), 'trades' => $this->trades()]);
    }

    public function update(Request $request, string $id): Response
    {
        $course = $this->find((int) $id);
        $data = $this->validated($request, $course);
        $data['is_active'] = (int) (bool) $request->input('is_active', 0);
        Database::query(
            'UPDATE courses SET code = ?, trade_id = ?, name_en = ?, name_bn = ?, course_type = ?, nsqf_level = ?, duration_months = ?,
                    total_hours = ?, min_qualification = ?, min_age = ?, max_age = ?, min_attendance_percent = ?, pass_mark_percent = ?,
                    description = ?, is_active = ? WHERE id = ?',
            [$data['code'], $data['trade_id'], $data['name_en'], $data['name_bn'], $data['course_type'], $data['nsqf_level'],
             $data['duration_months'], $data['total_hours'], $data['min_qualification'], $data['min_age'], $data['max_age'],
             $data['min_attendance_percent'], $data['pass_mark_percent'], $data['description'], $data['is_active'], $course['id']]
        );
        Audit::log('update', 'course', (int) $course['id'], ...Audit::diff($course, $data));
        Session::flash('success', __('courses.saved', ['name' => $data['name_en']]));
        return $this->redirect('/courses/' . $course['id']);
    }

    // ---- curriculum modules ---------------------------------------------

    public function storeModule(Request $request, string $id): Response
    {
        $course = $this->find((int) $id);
        $data = $this->validate([
            'title_en'        => 'required|max:200',
            'title_bn'        => 'nullable|max:200',
            'theory_hours'    => 'required|integer|min_value:0|max_value:2000',
            'practical_hours' => 'required|integer|min_value:0|max_value:2000',
        ]);
        $next = (int) Database::value('SELECT COALESCE(MAX(sequence_no), 0) + 1 FROM course_modules WHERE course_id = ?', [$course['id']]);
        Database::query('INSERT INTO course_modules (course_id, sequence_no, title_en, title_bn, theory_hours, practical_hours) VALUES (?, ?, ?, ?, ?, ?)',
            [$course['id'], $next, trim((string) $data['title_en']), ($data['title_bn'] ?? '') !== '' ? trim((string) $data['title_bn']) : null,
             (int) $data['theory_hours'], (int) $data['practical_hours']]);
        Session::flash('success', __('courses.module_saved'));
        return $this->redirect('/courses/' . $course['id'] . '#modules');
    }

    public function deleteModule(Request $request, string $id, string $moduleId): Response
    {
        $course = $this->find((int) $id);
        $module = Database::first('SELECT * FROM course_modules WHERE id = ? AND course_id = ?', [(int) $moduleId, $course['id']])
            ?? throw new HttpException(404);
        $used = (int) Database::value(
            'SELECT (SELECT COUNT(*) FROM class_routines WHERE module_id = ?) + (SELECT COUNT(*) FROM class_sessions WHERE module_id = ?)
                  + (SELECT COUNT(*) FROM assessments WHERE module_id = ?)', [$module['id'], $module['id'], $module['id']]);
        if ($used > 0) {
            Session::flash('error', __('courses.module_in_use'));
            return $this->redirect('/courses/' . $course['id'] . '#modules');
        }
        Database::transaction(static function () use ($module, $course): void {
            Database::query('DELETE FROM course_modules WHERE id = ?', [$module['id']]);
            // Close the gap in numbering: two-step update avoids unique-key clashes.
            Database::query('UPDATE course_modules SET sequence_no = sequence_no + 1000 WHERE course_id = ? AND sequence_no > ?', [$course['id'], $module['sequence_no']]);
            Database::query('UPDATE course_modules SET sequence_no = sequence_no - 1001 WHERE course_id = ? AND sequence_no > 1000', [$course['id']]);
        });
        Audit::log('delete', 'course_module', (int) $module['id'], $module, null);
        Session::flash('success', __('courses.module_deleted'));
        return $this->redirect('/courses/' . $course['id'] . '#modules');
    }

    // ---- fee structure ----------------------------------------------------

    public function saveFees(Request $request, string $id): Response
    {
        $course = $this->find((int) $id);
        $data = $this->validate(['effective_from' => 'required|date', 'center_id' => 'nullable|integer']);
        $centerId = ($data['center_id'] ?? '') !== '' ? (int) $data['center_id'] : null;
        if ($centerId !== null) {
            Rbac::assertCenter($centerId);
        }
        $amounts = (array) $request->input('amounts', []);
        $optional = (array) $request->input('optional', []);
        $heads = array_column(Database::all('SELECT id FROM fee_heads WHERE is_active = 1'), 'id');

        $rows = [];
        $errors = [];
        foreach ($heads as $headId) {
            $raw = trim(en_digits((string) ($amounts[$headId] ?? '')));
            if ($raw === '') {
                continue;
            }
            if (!preg_match('/^\d{1,9}(\.\d{1,2})?$/', $raw)) {
                $errors['amounts'][] = __('courses.fee_invalid');
                continue;
            }
            $rows[] = [(int) $headId, (float) $raw, !empty($optional[$headId]) ? 1 : 0];
        }
        if ($rows === [] && $errors === []) {
            $errors['amounts'][] = __('courses.fee_empty');
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        Database::transaction(static function () use ($rows, $course, $centerId, $data): void {
            foreach ($rows as [$headId, $amount, $isOptional]) {
                // UNIQUE keys ignore NULL center_id, so replace same-day default rows explicitly.
                if ($centerId === null) {
                    Database::query('DELETE FROM course_fees WHERE course_id = ? AND fee_head_id = ? AND center_id IS NULL AND effective_from = ?',
                        [$course['id'], $headId, $data['effective_from']]);
                }
                Database::query(
                    'INSERT INTO course_fees (course_id, fee_head_id, center_id, amount, is_optional, effective_from) VALUES (?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE amount = VALUES(amount), is_optional = VALUES(is_optional)',
                    [$course['id'], $headId, $centerId, $amount, $isOptional, $data['effective_from']]
                );
            }
        });
        Audit::log('update', 'course_fees', (int) $course['id'], null,
            ['center_id' => $centerId, 'effective_from' => $data['effective_from'], 'fees' => $rows], $centerId);
        Session::flash('success', __('courses.fees_saved'));
        return $this->redirect('/courses/' . $course['id'] . ($centerId !== null ? '?center_id=' . $centerId : '') . '#fees');
    }

    // ---- helpers -------------------------------------------------------

    /** @return array<string,mixed> */
    private function find(int $id): array
    {
        return Database::first(
            'SELECT c.*, t.name_en AS trade_en, t.name_bn AS trade_bn FROM courses c JOIN trades t ON t.id = c.trade_id WHERE c.id = ?', [$id])
            ?? throw new HttpException(404);
    }

    /** @return list<array<string,mixed>> */
    private function trades(): array
    {
        return Database::all('SELECT id, name_en, name_bn FROM trades WHERE is_active = 1 ORDER BY name_en');
    }

    /** @return array<string,mixed> */
    private function validated(Request $request, ?array $course): array
    {
        $input = $request->all();
        $input['code'] = strtoupper(trim((string) ($input['code'] ?? '')));
        foreach (['duration_months', 'total_hours', 'min_age', 'max_age', 'nsqf_level', 'min_attendance_percent', 'pass_mark_percent'] as $k) {
            $input[$k] = en_digits(trim((string) ($input[$k] ?? '')));
        }
        $data = $this->validate([
            'code'                   => ['required', 'regex:/^[A-Z0-9-]{2,20}$/'],
            'trade_id'               => 'required|integer',
            'name_en'                => 'required|max:150',
            'name_bn'                => 'required|max:150',
            'course_type'            => 'required|in:' . implode(',', self::TYPES),
            'nsqf_level'             => 'nullable|integer|min_value:1|max_value:6',
            'duration_months'        => 'required|decimal:1|min_value:0.5|max_value:48',
            'total_hours'            => 'required|integer|min_value:1|max_value:5000',
            'min_qualification'      => 'nullable|max:120',
            'min_age'                => 'nullable|integer|min_value:10|max_value:80',
            'max_age'                => 'nullable|integer|min_value:10|max_value:80',
            'min_attendance_percent' => 'required|decimal:2|min_value:0|max_value:100',
            'pass_mark_percent'      => 'required|decimal:2|min_value:0|max_value:100',
            'description'            => 'nullable|max:5000',
        ], $input);
        $errors = [];
        if (Database::value('SELECT id FROM courses WHERE code = ? AND id <> ?', [$data['code'], $course['id'] ?? 0]) !== null) {
            $errors['code'][] = __('validation.unique', ['field' => __('courses.code')]);
        }
        if (Database::value('SELECT id FROM trades WHERE id = ?', [(int) $data['trade_id']]) === null) {
            $errors['trade_id'][] = __('validation.in', ['field' => __('courses.trade')]);
        }
        if (($data['min_age'] ?? '') !== '' && ($data['max_age'] ?? '') !== '' && (int) $data['max_age'] < (int) $data['min_age']) {
            $errors['max_age'][] = __('courses.age_range');
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        $nullable = static fn(string $k): mixed => ($data[$k] ?? '') !== '' ? $data[$k] : null;
        return [
            'code' => $data['code'], 'trade_id' => (int) $data['trade_id'],
            'name_en' => trim((string) $data['name_en']), 'name_bn' => trim((string) $data['name_bn']),
            'course_type' => $data['course_type'], 'nsqf_level' => $nullable('nsqf_level'),
            'duration_months' => $data['duration_months'], 'total_hours' => (int) $data['total_hours'],
            'min_qualification' => $nullable('min_qualification'), 'min_age' => $nullable('min_age'), 'max_age' => $nullable('max_age'),
            'min_attendance_percent' => $data['min_attendance_percent'], 'pass_mark_percent' => $data['pass_mark_percent'],
            'description' => $nullable('description'),
        ];
    }
}
