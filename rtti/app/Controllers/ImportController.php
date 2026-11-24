<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Export;
use App\Core\Sequence;
use App\Core\XlsxReader;

/**
 * Bulk import (Excel .xlsx or CSV): template → upload → validation preview with error report → commit.
 */
final class ImportController extends Controller
{
    private const TYPES = [
        'students' => ['Students', 'students.import', ['name*', 'father_name*', 'mother_name', 'date_of_birth*', 'gender*', 'nid', 'birth_reg_no', 'mobile*', 'email', 'present_address', 'permanent_address', 'district', 'education', 'occupation', 'registration_date', 'center_code*'],
            ['Md. Rahim Uddin', 'Abdul Karim', 'Rokeya Begum', '2004-05-17', 'Male', '1234567890', '', '01712345678', '', 'Vill: Tajhat, Rangpur', 'Vill: Tajhat, Rangpur', 'Rangpur', 'SSC / Dakhil', 'Student', '2026-11-24', 'RNG']],
        'staff' => ['Staff', 'staff.import', ['name*', 'designation', 'department', 'center_code*', 'mobile*', 'email', 'nid', 'joining_date', 'is_trainer', 'trade_code', 'present_address'],
            ['Shahidul Islam', 'Trainer', 'Academic', 'RNG', '01812345678', '', '', '2025-01-01', 'yes', 'COM', 'Rangpur']],
        'courses' => ['Courses', 'courses.import', ['code*', 'name*', 'trade_code*', 'category', 'duration*', 'duration_unit', 'fee*', 'pass_mark_percent', 'min_attendance_percent', 'description'],
            ['COM-BAS', 'Basic Computer', 'COM', 'Short Course', '3', 'months', '5000', '40', '75', '']],
        'batches' => ['Batches', 'batches.import', ['name*', 'course_code*', 'center_code*', 'start_date*', 'end_date*', 'max_students', 'trainer_employee_code', 'class_days', 'class_start_time', 'class_end_time', 'room'],
            ['Basic Computer – Morning', 'COM-OAP', 'RNG', '2027-01-05', '2027-04-04', '25', 'EMP-0002', 'Sat, Mon, Wed', '10:00', '13:00', 'Lab-1']],
        'assets' => ['Assets', 'assets.import', ['name*', 'category*', 'center_code*', 'serial_no', 'brand_model', 'purchase_date', 'purchase_price', 'supplier', 'condition', 'warranty_expiry', 'quantity'],
            ['Desktop Computer', 'Computer & IT', 'RNG', 'SN123456', 'HP ProDesk 400', '2026-01-15', '45000', 'Star Tech', 'new', '2029-01-14', '1']],
    ];

    private function type(): array
    {
        $t = (string)input('type', '');
        $def = self::TYPES[$t] ?? null;
        return [$t, $def];
    }

    public function index(): void
    {
        $allowed = array_filter(self::TYPES, fn($d) => Auth::can($d[1]));
        if (!$allowed) {
            $this->deny('You have no import permission.');
        }
        [$t, $def] = $this->type();
        $this->render('import/index', ['title' => 'Data Import', 'types' => $allowed, 't' => $def && Auth::can($def[1]) ? $t : array_key_first($allowed),
            'xlsx' => XlsxReader::available(), 'breadcrumbs' => ['Administration' => null, 'Data Import' => null]]);
    }

    public function template(): void
    {
        [$t, $def] = $this->type();
        if (!$def) {
            $this->notFound('Import type');
        }
        $this->authorize($def[1]);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="rtti_import_' . $t . '_template.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, array_map(fn($c) => rtrim($c, '*'), $def[2]));
        fputcsv($out, $def[3]);
        fclose($out);
        exit;
    }

    private function parseDate(string $v): ?string
    {
        $v = trim($v);
        if ($v === '') {
            return null;
        }
        if (is_numeric($v) && (float)$v > 20000 && (float)$v < 80000) { // Excel serial date
            return date('Y-m-d', (int)(((float)$v - 25569) * 86400));
        }
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'm/d/Y'] as $f) {
            $d = \DateTime::createFromFormat('!' . $f, $v);
            if ($d && $d->format($f) === $v) {
                return $d->format('Y-m-d');
            }
        }
        return null;
    }

    private function lookup(string $table, string $col, string $val): ?int
    {
        if ($val === '') {
            return null;
        }
        $id = DB::value("SELECT id FROM `$table` WHERE LOWER(`$col`) = LOWER(:v) LIMIT 1", ['v' => $val]);
        return $id === null ? null : (int)$id;
    }

    /** Validate rows; returns list of [data|null, errors[]] */
    private function validateRows(string $t, array $rows): array
    {
        $centers = [];
        foreach (Auth::centerOptions() as $c) {
            $centers[strtoupper($c['code'])] = (int)$c['id'];
        }
        $seen = [];
        $out = [];
        foreach ($rows as $i => $r) {
            $err = [];
            $g = fn($k) => trim((string)($r[$k] ?? ''));
            foreach (self::TYPES[$t][2] as $col) {
                if (str_ends_with($col, '*') && $g(rtrim($col, '*')) === '') {
                    $err[] = rtrim($col, '*') . ' is required';
                }
            }
            $center = null;
            if (array_key_exists('center_code', $r) || in_array('center_code*', self::TYPES[$t][2], true)) {
                $center = $centers[strtoupper($g('center_code'))] ?? null;
                if ($g('center_code') !== '' && !$center) {
                    $err[] = "center_code '{$g('center_code')}' unknown or not assigned to you";
                }
            }
            $data = null;
            switch ($t) {
                case 'students':
                    $mobile = preg_replace('/\D/', '', $g('mobile'));
                    $mobile = str_starts_with($mobile, '880') ? substr($mobile, 2) : $mobile;
                    $nid = preg_replace('/\D/', '', $g('nid'));
                    $brn = preg_replace('/\D/', '', $g('birth_reg_no'));
                    $dob = $this->parseDate($g('date_of_birth'));
                    $gender = $this->lookup('genders', 'name', $g('gender'));
                    if ($g('date_of_birth') !== '' && !$dob) $err[] = 'date_of_birth invalid';
                    if ($g('gender') !== '' && !$gender) $err[] = 'gender must be Male/Female/Other';
                    if ($mobile !== '' && !preg_match('/^01[3-9]\d{8}$/', $mobile)) $err[] = 'mobile invalid';
                    if ($nid === '' && $brn === '') $err[] = 'nid or birth_reg_no required';
                    if ($nid !== '' && !preg_match('/^(\d{10}|\d{13}|\d{17})$/', $nid)) $err[] = 'nid must be 10/13/17 digits';
                    if ($brn !== '' && !preg_match('/^\d{17}$/', $brn)) $err[] = 'birth_reg_no must be 17 digits';
                    $key = $nid ?: $brn;
                    if ($key !== '' && isset($seen[$key])) $err[] = 'duplicate NID/BRN within file (row ' . $seen[$key] . ')';
                    if ($key !== '' && DB::exists('SELECT 1 FROM students WHERE deleted_at IS NULL AND (nid = :a OR birth_reg_no = :b)', ['a' => $key, 'b' => $key])) $err[] = 'student with this NID/BRN already exists';
                    $seen[$key] = $i + 2;
                    $data = ['name' => $g('name'), 'father_name' => $g('father_name'), 'mother_name' => nullify($g('mother_name')), 'date_of_birth' => $dob, 'gender_id' => $gender,
                        'nid' => nullify($nid), 'birth_reg_no' => nullify($brn), 'mobile' => $mobile, 'email' => nullify($g('email')), 'present_address' => nullify($g('present_address')),
                        'permanent_address' => nullify($g('permanent_address')), 'district_id' => $this->lookup('districts', 'name', $g('district')),
                        'education_level_id' => $this->lookup('education_levels', 'name', $g('education')), 'occupation' => nullify($g('occupation')),
                        'registration_date' => $this->parseDate($g('registration_date')) ?? date('Y-m-d'), 'center_id' => $center];
                    break;
                case 'staff':
                    $mobile = preg_replace('/\D/', '', $g('mobile'));
                    if ($mobile !== '' && !preg_match('/^01[3-9]\d{8}$/', $mobile)) $err[] = 'mobile invalid';
                    if ($mobile !== '' && (isset($seen[$mobile]) || DB::exists('SELECT 1 FROM staff WHERE mobile = :m AND deleted_at IS NULL', ['m' => $mobile]))) $err[] = 'mobile already used';
                    $seen[$mobile] = $i + 2;
                    $trainer = in_array(strtolower($g('is_trainer')), ['yes', 'y', '1', 'true'], true) ? 1 : 0;
                    $data = ['name' => $g('name'), 'designation_id' => $this->lookup('designations', 'name', $g('designation')), 'department_id' => $this->lookup('departments', 'name', $g('department')),
                        'center_id' => $center, 'mobile' => $mobile, 'email' => nullify($g('email')), 'nid' => nullify($g('nid')), 'joining_date' => $this->parseDate($g('joining_date')),
                        'is_trainer' => $trainer, 'trainer_trade_id' => $trainer ? $this->lookup('trades', 'code', $g('trade_code')) : null, 'present_address' => nullify($g('present_address'))];
                    break;
                case 'courses':
                    $trade = $this->lookup('trades', 'code', $g('trade_code'));
                    if ($g('trade_code') !== '' && !$trade) $err[] = 'trade_code unknown';
                    $code = strtoupper($g('code'));
                    if ($code !== '' && (isset($seen[$code]) || DB::exists('SELECT 1 FROM courses WHERE code = :c', ['c' => $code]))) $err[] = 'course code already exists';
                    $seen[$code] = $i + 2;
                    $unit = strtolower($g('duration_unit')) ?: 'months';
                    if (!in_array($unit, ['hours', 'days', 'weeks', 'months'], true)) $err[] = 'duration_unit must be hours/days/weeks/months';
                    if (!is_numeric($g('fee'))) $err[] = 'fee must be a number';
                    $data = ['code' => $code, 'name' => $g('name'), 'trade_id' => $trade, 'academic_category_id' => $this->lookup('academic_categories', 'name', $g('category')),
                        'duration' => max(1, (int)$g('duration')), 'duration_unit' => $unit, 'fee' => (float)$g('fee'), 'pass_mark_percent' => $g('pass_mark_percent') !== '' ? (float)$g('pass_mark_percent') : 40,
                        'min_attendance_percent' => $g('min_attendance_percent') !== '' ? (float)$g('min_attendance_percent') : 75, 'description' => nullify($g('description')),
                        'course_status_id' => (int)DB::value("SELECT id FROM course_statuses WHERE code = 'ACTIVE'")];
                    break;
                case 'batches':
                    $course = $this->lookup('courses', 'code', $g('course_code'));
                    if ($g('course_code') !== '' && !$course) $err[] = 'course_code unknown';
                    $s = $this->parseDate($g('start_date'));
                    $e = $this->parseDate($g('end_date'));
                    if (!$s || !$e) $err[] = 'start_date/end_date invalid';
                    elseif ($e < $s) $err[] = 'end_date before start_date';
                    $trainer = $g('trainer_employee_code') !== '' ? $this->lookup('staff', 'employee_code', $g('trainer_employee_code')) : null;
                    if ($g('trainer_employee_code') !== '' && !$trainer) $err[] = 'trainer_employee_code unknown';
                    $data = ['name' => $g('name'), 'course_id' => $course, 'center_id' => $center, 'start_date' => $s, 'end_date' => $e, 'max_students' => max(1, (int)($g('max_students') ?: 25)),
                        'trainer_id' => $trainer, 'class_days' => nullify($g('class_days')), 'class_start_time' => nullify($g('class_start_time')), 'class_end_time' => nullify($g('class_end_time')),
                        'room' => nullify($g('room')), 'batch_status_id' => (int)DB::value("SELECT id FROM batch_statuses WHERE code = 'UPCOMING'")];
                    $data['class_schedule'] = trim(($data['class_days'] ?? '') . ' ' . ($data['class_start_time'] ? $data['class_start_time'] . '–' . $data['class_end_time'] : '')) ?: null;
                    break;
                case 'assets':
                    $cat = $this->lookup('asset_categories', 'name', $g('category'));
                    if ($g('category') !== '' && !$cat) $err[] = 'category unknown';
                    $cond = strtolower($g('condition')) ?: 'good';
                    if (!in_array($cond, ['new', 'good', 'fair', 'poor', 'damaged'], true)) $err[] = 'condition invalid';
                    if ($g('serial_no') !== '' && DB::exists('SELECT 1 FROM assets WHERE serial_no = :s AND deleted_at IS NULL', ['s' => $g('serial_no')])) $err[] = 'serial_no already registered';
                    $data = ['name' => $g('name'), 'asset_category_id' => $cat, 'center_id' => $center, 'serial_no' => nullify($g('serial_no')), 'brand_model' => nullify($g('brand_model')),
                        'purchase_date' => $this->parseDate($g('purchase_date')), 'purchase_price' => $g('purchase_price') !== '' ? (float)$g('purchase_price') : null, 'supplier' => nullify($g('supplier')),
                        'asset_condition' => $cond, 'warranty_expiry' => $this->parseDate($g('warranty_expiry')), 'status' => 'in_use', '_qty' => max(1, min(100, (int)($g('quantity') ?: 1)))];
                    break;
            }
            $out[] = ['row' => $i + 2, 'raw' => $r, 'data' => $err ? null : $data, 'errors' => $err];
        }
        return $out;
    }

    public function preview(): void
    {
        $this->requirePost();
        [$t, $def] = $this->type();
        if (!$def) {
            $this->notFound('Import type');
        }
        $this->authorize($def[1]);
        $f = $_FILES['file'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
            $this->fail('Choose a file to import.');
        }
        $ext = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));
        try {
            if ($ext === 'xlsx') {
                $lines = XlsxReader::read($f['tmp_name']);
                $header = array_map(fn($h) => strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '_', rtrim((string)$h, '*')), '_')), array_shift($lines) ?? []);
                $rows = [];
                foreach ($lines as $l) {
                    if (count(array_filter($l, fn($x) => $x !== '')) === 0) {
                        continue;
                    }
                    $rows[] = array_combine($header, array_pad(array_slice($l, 0, count($header)), count($header), ''));
                }
            } elseif (in_array($ext, ['csv', 'txt'], true)) {
                $rows = Export::readCsv($f['tmp_name']);
            } else {
                $this->fail('Upload an Excel (.xlsx) or CSV file.');
            }
        } catch (\Throwable $e) {
            $this->fail('Could not read the file: ' . $e->getMessage());
        }
        if (!$rows) {
            $this->fail('The file has no data rows.');
        }
        if (count($rows) > 2000) {
            $this->fail('Maximum 2000 rows per import. Split the file.');
        }
        $missing = array_diff(array_map(fn($c) => rtrim($c, '*'), array_filter($def[2], fn($c) => str_ends_with($c, '*'))), array_keys($rows[0]));
        if ($missing) {
            $this->fail('Missing required column(s): ' . implode(', ', $missing) . '. Use the template.');
        }
        $result = $this->validateRows($t, $rows);
        $token = bin2hex(random_bytes(8));
        $_SESSION['import'][$token] = ['type' => $t, 'rows' => array_values(array_filter(array_map(fn($r) => $r['data'], $result)))];
        $this->render('import/preview', ['title' => 'Import preview', 't' => $t, 'def' => $def, 'result' => $result, 'token' => $token,
            'breadcrumbs' => ['Data Import' => url('import/index', ['type' => $t]), 'Preview' => null]]);
    }

    public function commit(): void
    {
        $this->requirePost();
        $token = (string)input('token');
        $job = $_SESSION['import'][$token] ?? null;
        if (!$job) {
            $this->fail('Import session expired. Upload the file again.');
        }
        $t = $job['type'];
        $this->authorize(self::TYPES[$t][1]);
        $n = 0;
        try {
            DB::transaction(function () use ($t, $job, &$n) {
                foreach ($job['rows'] as $d) {
                    if (isset($d['center_id']) && !Auth::canAccessCenter((int)$d['center_id'])) {
                        throw new \DomainException('Center access changed – aborting.');
                    }
                    $ccode = isset($d['center_id']) ? (string)DB::value('SELECT code FROM centers WHERE id = :c', ['c' => $d['center_id']]) : '';
                    switch ($t) {
                        case 'students':
                            $d['student_code'] = Sequence::next('student_id_format', 'RTTI-{YY}-{SEQ6}', ['center' => $ccode]);
                            $d['created_by'] = Auth::id();
                            DB::insert('students', $d);
                            break;
                        case 'staff':
                            $d['employee_code'] = Sequence::next('employee_id_format', 'EMP-{SEQ4}');
                            $d['created_by'] = Auth::id();
                            DB::insert('staff', $d);
                            break;
                        case 'courses':
                            $d['created_by'] = Auth::id();
                            DB::insert('courses', $d);
                            break;
                        case 'batches':
                            $d['batch_code'] = Sequence::next('batch_code_format', '{CENTER}-{COURSE}-{YY}-{SEQ3}', ['center' => $ccode,
                                'course' => preg_replace('/^[A-Z]+-/', '', (string)DB::value('SELECT code FROM courses WHERE id = :c', ['c' => $d['course_id']]))]);
                            $d['created_by'] = Auth::id();
                            DB::insert('batches', $d);
                            break;
                        case 'assets':
                            $qty = $d['_qty'];
                            unset($d['_qty']);
                            for ($i = 0; $i < $qty; $i++) {
                                $row = $d;
                                if ($i > 0) {
                                    $row['serial_no'] = null;
                                }
                                $row['asset_code'] = Sequence::next('asset_code_format', 'AST-{CENTER}-{SEQ5}', ['center' => $ccode]);
                                $row['created_by'] = Auth::id();
                                $aid = DB::insert('assets', $row);
                                DB::insert('asset_assignments', ['asset_id' => $aid, 'movement_type' => 'register', 'to_center_id' => $row['center_id'], 'movement_date' => $row['purchase_date'] ?? date('Y-m-d'), 'note' => 'Imported', 'created_by' => Auth::id()]);
                            }
                            break;
                    }
                    $n++;
                }
            });
        } catch (\Throwable $e) {
            $this->fail('Import failed and was rolled back: ' . $e->getMessage());
        }
        unset($_SESSION['import'][$token]);
        Audit::log('import', $t, null, "Imported $n $t row(s)");
        flash('success', "Import complete: $n " . self::TYPES[$t][0] . ' record(s) created.');
        redirect(url('import/index', ['type' => $t]));
    }
}
