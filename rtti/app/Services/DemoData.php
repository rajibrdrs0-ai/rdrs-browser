<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Sequence;

/**
 * Optional sample data so the system can be explored right after installation.
 * All records are created through the same services the UI uses, so balances,
 * ledgers, receipts and certificates are internally consistent.
 */
final class DemoData
{
    public static function load(int $adminId, int $hoId): array
    {
        mt_srand(20261124);
        $_SESSION['uid'] = $adminId; // services record the acting user
        $log = [];
        $today = new \DateTimeImmutable('today');
        $d = fn(string $mod) => $today->modify($mod)->format('Y-m-d');
        $pw = password_hash('Demo@1234', PASSWORD_DEFAULT);

        $dist = fn(string $n) => DB::row('SELECT id, division_id FROM districts WHERE name = :n', ['n' => $n]);
        $upz = fn(string $n) => DB::value('SELECT id FROM upazilas WHERE name = :n LIMIT 1', ['n' => $n]);
        $bs = DB::pairs('SELECT code, id FROM batch_statuses');
        $cs = DB::pairs('SELECT code, id FROM course_statuses');
        $trade = DB::pairs('SELECT code, id FROM trades');
        $desig = DB::pairs('SELECT name, id FROM designations');
        $dept = DB::pairs('SELECT name, id FROM departments');
        $gender = DB::pairs('SELECT name, id FROM genders');
        $edu = DB::column('SELECT id FROM education_levels WHERE sort_order BETWEEN 3 AND 6');
        $pm = DB::pairs('SELECT name, id FROM payment_methods');

        // ---- Centers ----
        $centers = [];
        foreach ([['RNG', 'RTTI Rangpur', 'Rangpur', 'Rangpur Sadar', 'Jail Road, Rangpur'], ['KUR', 'RTTI Kurigram', 'Kurigram', 'Kurigram Sadar', 'College Road, Kurigram'],
                     ['LAL', 'RTTI Lalmonirhat', 'Lalmonirhat', 'Lalmonirhat Sadar', 'Station Road, Lalmonirhat'], ['NIL', 'RTTI Nilphamari', 'Nilphamari', 'Nilphamari Sadar', 'Sadar Road, Nilphamari']] as [$code, $name, $dn, $un, $addr]) {
            $dd = $dist($dn);
            $id = DB::insert('centers', ['code' => $code, 'name' => $name, 'division_id' => $dd['division_id'], 'district_id' => $dd['id'], 'upazila_id' => $upz($un),
                'address' => $addr, 'contact_person' => 'Center Manager', 'mobile' => '017' . mt_rand(10000000, 99999999)]);
            DB::insert('accounts', ['name' => "$name Cash", 'account_type' => 'cash', 'center_id' => $id]);
            $centers[$code] = ['id' => $id, 'district' => $dn, 'upz' => $upz($un)];
        }
        DB::insert('accounts', ['name' => 'RDRS bKash Merchant', 'account_type' => 'mobile', 'account_no' => '01700000000']);
        $log[] = 'Demo: 4 district training centers created.';

        // ---- Staff (trainers + managers) ----
        $first = ['Abdur', 'Md.', 'Nusrat', 'Farhana', 'Rafiqul', 'Shamima', 'Kamrul', 'Tahmina', 'Mizanur', 'Sharmin', 'Habibur', 'Rokeya', 'Jahangir', 'Moushumi', 'Sohel', 'Ayesha', 'Rashed', 'Nasrin', 'Anisur', 'Sabina', 'Mahmudul', 'Lipi', 'Shahidul', 'Rumana', 'Tanvir', 'Jannatul', 'Arif', 'Sumaiya', 'Rubel', 'Khadija'];
        $last = ['Rahman', 'Islam', 'Hossain', 'Akter', 'Alam', 'Begum', 'Hasan', 'Khatun', 'Ahmed', 'Sultana', 'Uddin', 'Parvin', 'Kabir', 'Nahar', 'Mia', 'Sarkar', 'Roy', 'Barman'];
        $female = ['Nusrat', 'Farhana', 'Shamima', 'Tahmina', 'Sharmin', 'Rokeya', 'Moushumi', 'Ayesha', 'Nasrin', 'Sabina', 'Lipi', 'Rumana', 'Jannatul', 'Sumaiya', 'Khadija'];
        $person = function () use ($first, $last, $female): array {
            $f = $first[mt_rand(0, count($first) - 1)];
            $isF = in_array($f, $female, true);
            $maleLast = ['Rahman', 'Islam', 'Hossain', 'Alam', 'Hasan', 'Ahmed', 'Uddin', 'Kabir', 'Mia', 'Sarkar', 'Roy', 'Barman'];
            $l = $isF ? ['Akter', 'Begum', 'Khatun', 'Sultana', 'Parvin', 'Nahar', 'Islam', 'Rahman'][mt_rand(0, 7)] : $maleLast[mt_rand(0, count($maleLast) - 1)];
            return [$f . ' ' . $l, $isF];
        };
        $trainers = [];
        $managers = [];
        foreach ($centers as $code => $c) {
            foreach ([['Center Manager', 0, null], ['Senior Trainer', 1, 'COM'], ['Trainer', 1, 'ELE'], ['Trainer', 1, 'TAI'], ['Accounts Officer', 0, null]] as [$dg, $isT, $tr]) {
                [$nm, $isF] = $person();
                $sid = DB::insert('staff', [
                    'employee_code' => Sequence::next('employee_id_format', 'EMP-{SEQ4}'), 'name' => $nm, 'designation_id' => $desig[$dg],
                    'department_id' => $dept[$isT ? 'Academic' : ($dg === 'Accounts Officer' ? 'Accounts' : 'Administration')], 'center_id' => $c['id'],
                    'gender_id' => $gender[$isF ? 'Female' : 'Male'], 'mobile' => '01' . mt_rand(3, 9) . mt_rand(10000000, 99999999),
                    'joining_date' => $d('-' . mt_rand(200, 2500) . ' days'), 'is_trainer' => $isT, 'trainer_trade_id' => $tr ? $trade[$tr] : null,
                    'present_address' => $c['district'], 'created_by' => $adminId,
                ]);
                if ($isT) {
                    $trainers[$code][$tr] = $sid;
                }
                if ($dg === 'Center Manager') {
                    $managers[$code] = [$sid, $nm];
                }
                if ($dg === 'Accounts Officer') {
                    $managers[$code . '_acc'] = [$sid, $nm];
                }
            }
        }
        $log[] = 'Demo: 20 staff members (incl. 12 trainers).';

        // ---- Users ----
        $role = DB::pairs('SELECT slug, id FROM roles');
        $mkUser = function (string $username, string $name, string $roleSlug, ?int $centerId, ?int $staffId = null) use ($pw, $role) {
            $uid = DB::insert('users', ['name' => $name, 'username' => $username, 'password_hash' => $pw, 'staff_id' => $staffId, 'default_center_id' => $centerId]);
            DB::insert('user_roles', ['user_id' => $uid, 'role_id' => $role[$roleSlug]]);
            if ($centerId) {
                DB::insert('user_centers', ['user_id' => $uid, 'center_id' => $centerId]);
            }
            return $uid;
        };
        $mkUser('hoadmin', 'Head Office Admin', 'ho_admin', $hoId);
        $mkUser('hoaccounts', 'Head Office Accounts', 'ho_accounts', $hoId);
        $mkUser('rangpur', $managers['RNG'][1], 'center_manager', $centers['RNG']['id'], $managers['RNG'][0]);
        $mkUser('kurigram', $managers['KUR'][1], 'center_manager', $centers['KUR']['id'], $managers['KUR'][0]);
        $mkUser('rngaccounts', $managers['RNG_acc'][1], 'accounts_officer', $centers['RNG']['id'], $managers['RNG_acc'][0]);
        $mkUser('trainer', 'Demo Trainer (Rangpur)', 'trainer', $centers['RNG']['id'], $trainers['RNG']['COM']);
        $log[] = 'Demo users (password Demo@1234): hoadmin, hoaccounts, rangpur, kurigram, rngaccounts, trainer.';

        // ---- Courses ----
        $courseDefs = [
            ['COM-OAP', 'Computer Office Application', 'COM', 3, 6000, [['MS Word', 30, 20], ['MS Excel', 30, 20], ['MS PowerPoint & Internet', 20, 30]]],
            ['COM-GDS', 'Graphic Design', 'COM', 4, 8000, [['Photoshop', 30, 20], ['Illustrator', 30, 20]]],
            ['ELE-HWR', 'Electrical House Wiring', 'ELE', 3, 5000, [['Theory of Electricity', 40, 0], ['Practical Wiring', 0, 60]]],
            ['TAI-DRM', 'Tailoring & Dress Making', 'TAI', 3, 4000, []],
            ['ETR-MPS', 'Mobile Phone Servicing', 'ETR', 3, 6000, []],
            ['LIV-PLT', 'Livestock & Poultry Rearing', 'LIV', 1, 2000, []],
        ];
        $cat = DB::pairs('SELECT name, id FROM academic_categories');
        $courses = [];
        foreach ($courseDefs as [$code, $name, $tr, $months, $fee, $subs]) {
            $cid = DB::insert('courses', [
                'code' => $code, 'name' => $name, 'academic_category_id' => $months > 3 ? $cat['Long Course'] : $cat['Short Course'], 'trade_id' => $trade[$tr],
                'duration' => $months, 'duration_unit' => 'months', 'fee' => $fee, 'min_education_level_id' => $edu[0] ?? null,
                'description' => "$name – hands-on skill development course.", 'objectives' => 'Make trainees job-ready or self-employed in the ' . $name . ' trade.',
                'outline' => implode("\n", array_map(fn($s) => '• ' . $s[0], $subs)) ?: '• Theory\n• Practical', 'pass_mark_percent' => 40, 'min_attendance_percent' => 75,
                'course_status_id' => $cs['ACTIVE'], 'created_by' => $adminId,
            ]);
            foreach ($subs as $i => [$sn, $th, $pr]) {
                DB::insert('course_subjects', ['course_id' => $cid, 'name' => $sn, 'theory_full_marks' => $th, 'practical_full_marks' => $pr, 'sort_order' => $i]);
            }
            $courses[$code] = ['id' => $cid, 'trade' => $tr, 'months' => $months];
        }
        // Center-specific fee example
        DB::insert('fee_structures', ['course_id' => $courses['COM-OAP']['id'], 'center_id' => $centers['NIL']['id'], 'fee' => 5500, 'effective_from' => $d('-1 year')]);
        $log[] = 'Demo: 6 courses with subjects.';

        // ---- Batches ----
        $batches = [];
        $plan = [
            'RNG' => [['COM-OAP', 'COMPLETED', '-5 months'], ['COM-OAP', 'RUNNING', '-6 weeks'], ['ELE-HWR', 'RUNNING', '-4 weeks'], ['COM-GDS', 'UPCOMING', '+5 weeks']],
            'KUR' => [['TAI-DRM', 'COMPLETED', '-4 months'], ['COM-OAP', 'RUNNING', '-5 weeks'], ['LIV-PLT', 'UPCOMING', '+3 weeks']],
            'LAL' => [['ETR-MPS', 'COMPLETED', '-6 months'], ['TAI-DRM', 'RUNNING', '-3 weeks']],
            'NIL' => [['COM-OAP', 'COMPLETED', '-4 months'], ['ELE-HWR', 'RUNNING', '-2 weeks']],
        ];
        foreach ($plan as $ctr => $list) {
            foreach ($list as [$ccode, $status, $startMod]) {
                $c = $courses[$ccode];
                $start = $d($startMod);
                $end = (new \DateTimeImmutable($start))->modify('+' . $c['months'] . ' months')->modify('-1 day')->format('Y-m-d');
                $code = Sequence::next('batch_code_format', '{CENTER}-{COURSE}-{YY}-{SEQ3}', ['center' => $ctr, 'course' => explode('-', $ccode)[1]]);
                $trainer = $trainers[$ctr][$c['trade']] ?? $trainers[$ctr]['COM'];
                $bid = DB::insert('batches', [
                    'batch_code' => $code, 'name' => DB::value('SELECT name FROM courses WHERE id = :i', ['i' => $c['id']]) . ' – Batch ' . substr($code, -3),
                    'course_id' => $c['id'], 'center_id' => $centers[$ctr]['id'], 'trainer_id' => $trainer, 'start_date' => $start, 'end_date' => $end,
                    'class_days' => 'Sat, Mon, Wed', 'class_start_time' => '10:00:00', 'class_end_time' => '13:00:00', 'class_schedule' => 'Sat, Mon, Wed 10:00 AM – 1:00 PM',
                    'room' => 'Lab-' . mt_rand(1, 3), 'max_students' => 25, 'batch_status_id' => $bs[$status === 'COMPLETED' ? 'RUNNING' : $status], 'created_by' => $adminId,
                ]);
                foreach ([6 => 'Sat', 1 => 'Mon', 3 => 'Wed'] as $dow => $_) {
                    DB::insert('class_routines', ['batch_id' => $bid, 'day_of_week' => $dow, 'start_time' => '10:00:00', 'end_time' => '13:00:00', 'subject' => 'Theory & Practical', 'trainer_id' => $trainer, 'room' => 'Lab-1']);
                }
                $batches[] = ['id' => $bid, 'center' => $ctr, 'course' => $ccode, 'status' => $status, 'start' => $start, 'end' => $end, 'trainer' => $trainer];
            }
        }
        $log[] = 'Demo: ' . count($batches) . ' batches with class routines.';

        // ---- Students & enrollments ----
        $nStudents = 0;
        $nEnroll = 0;
        $villages = ['Mahiganj', 'Shapla Chattar', 'Lalbag', 'Modern More', 'Dhap', 'Payrabond', 'Tajhat', 'Kamal Kachna', 'Ghagot Para', 'Nisbetganj'];
        $methodIds = [$pm['Cash'], $pm['Cash'], $pm['Cash'], $pm['bKash'], $pm['Nagad']];
        $allEnrollments = [];
        foreach ($batches as $b) {
            if ($b['status'] === 'UPCOMING') {
                $n = mt_rand(4, 8);
            } else {
                $n = mt_rand(12, 18);
            }
            $ctr = $centers[$b['center']];
            for ($i = 0; $i < $n; $i++) {
                [$nm, $isF] = $person();
                [$fn] = $person();
                $fatherFirst = ['Abdul', 'Md. Abul', 'Nur', 'Golam', 'Md. Shafiqul', 'Hafizur', 'Abdus', 'Md. Delwar'][mt_rand(0, 7)];
                $regDate = (new \DateTimeImmutable($b['start']))->modify('-' . mt_rand(5, 25) . ' days')->format('Y-m-d');
                if ($regDate > $today->format('Y-m-d')) {
                    $regDate = $today->format('Y-m-d');
                }
                $sid = DB::insert('students', [
                    'student_code' => Sequence::next('student_id_format', 'RTTI-{YY}-{SEQ6}'), 'name' => $nm,
                    'father_name' => $fatherFirst . ' ' . ['Rahman', 'Islam', 'Hossain', 'Alam', 'Hasan', 'Ahmed', 'Uddin', 'Kabir', 'Mia', 'Sarkar'][mt_rand(0, 9)], 'mother_name' => ['Rahima', 'Salma', 'Jahanara', 'Fatema', 'Morium', 'Anowara'][mt_rand(0, 5)] . ' Begum',
                    'date_of_birth' => $d('-' . mt_rand(17, 32) . ' years -' . mt_rand(0, 300) . ' days'), 'gender_id' => $gender[$isF ? 'Female' : 'Male'],
                    'nid' => mt_rand(0, 4) ? (string)mt_rand(1000000000, 9999999999) : null, 'mobile' => '01' . [3, 7, 8, 9, 6, 5][mt_rand(0, 5)] . mt_rand(10000000, 99999999),
                    'present_address' => 'Vill: ' . $villages[mt_rand(0, 9)] . ', ' . $ctr['district'], 'permanent_address' => 'Vill: ' . $villages[mt_rand(0, 9)] . ', ' . $ctr['district'],
                    'district_id' => $dist($ctr['district'])['id'], 'upazila_id' => $ctr['upz'], 'education_level_id' => $edu[mt_rand(0, count($edu) - 1)],
                    'occupation' => ['Student', 'Unemployed', 'Housewife', 'Farmer', 'Small business'][mt_rand(0, 4)],
                    'emergency_contact_name' => $fatherFirst, 'emergency_contact_mobile' => '017' . mt_rand(10000000, 99999999),
                    'registration_date' => $regDate, 'center_id' => $ctr['id'], 'created_by' => $adminId,
                ]);
                $nStudents++;
                $fee = EnrollmentService::courseFee($courses[$b['course']]['id'], $ctr['id']);
                $discount = mt_rand(0, 5) === 0 ? round($fee * 0.25) : 0;
                $initial = [0, round(($fee - $discount) / 2), $fee - $discount, 1000][mt_rand(0, 3)];
                $method = $methodIds[mt_rand(0, 4)];
                $r = EnrollmentService::enroll([
                    'student_id' => $sid, 'batch_id' => $b['id'], 'enrollment_date' => $regDate, 'discount' => $discount,
                    'discount_reason' => $discount ? 'Poor & meritorious (25% waiver)' : null, 'initial_payment' => $initial,
                    'payment_method_id' => $method, 'transaction_ref' => $method !== $pm['Cash'] ? 'TXN' . mt_rand(100000, 999999) : null,
                ]);
                $nEnroll++;
                $allEnrollments[] = ['id' => $r['enrollment_id'], 'batch' => $b];
            }
        }
        $log[] = "Demo: $nStudents students and $nEnroll enrollments with admission payments.";

        // ---- Installments on completed/running batches ----
        $nPay = 0;
        foreach ($allEnrollments as $en) {
            if ($en['batch']['status'] === 'UPCOMING') {
                continue;
            }
            $e = DB::row('SELECT * FROM enrollments WHERE id = :id', ['id' => $en['id']]);
            $due = (float)$e['due_amount'];
            if ($due <= 0) {
                continue;
            }
            $payAll = $en['batch']['status'] === 'COMPLETED' ? mt_rand(0, 5) > 0 : mt_rand(0, 2) === 0;
            $amount = $payAll ? $due : round($due / 2);
            $payDate = min($today->format('Y-m-d'), (new \DateTimeImmutable($en['batch']['start']))->modify('+' . mt_rand(10, 40) . ' days')->format('Y-m-d'));
            $method = $methodIds[mt_rand(0, 4)];
            FeeService::collect(['enrollment_id' => $en['id'], 'amount' => $amount, 'payment_date' => $payDate, 'payment_method_id' => $method,
                'transaction_ref' => $method !== $pm['Cash'] ? 'TXN' . mt_rand(100000, 999999) : null, 'remarks' => 'Installment']);
            $nPay++;
        }
        $log[] = "Demo: $nPay installment receipts.";

        // ---- Attendance ----
        foreach ($batches as $b) {
            if ($b['status'] === 'UPCOMING') {
                continue;
            }
            $enrs = DB::column("SELECT id FROM enrollments WHERE batch_id = :b AND status IN ('active','completed')", ['b' => $b['id']]);
            $weak = array_slice($enrs, 0, 1); // one weak attender per batch
            $day = new \DateTimeImmutable($b['start']);
            $stop = min($b['end'], $today->modify('-1 day')->format('Y-m-d'));
            $sessions = 0;
            while ($day->format('Y-m-d') <= $stop && $sessions < 36) {
                if (in_array((int)$day->format('w'), [6, 1, 3], true)) {
                    $aid = DB::insert('attendance', ['batch_id' => $b['id'], 'attendance_date' => $day->format('Y-m-d'), 'topic' => 'Session ' . ($sessions + 1), 'trainer_id' => $b['trainer'], 'taken_by' => $adminId]);
                    foreach ($enrs as $eid) {
                        $rnd = mt_rand(1, 100);
                        $st = in_array($eid, $weak, true) ? ($rnd <= 55 ? 'present' : 'absent') : ($rnd <= 86 ? 'present' : ($rnd <= 92 ? 'late' : ($rnd <= 96 ? 'leave' : 'absent')));
                        DB::insert('attendance_details', ['attendance_id' => $aid, 'enrollment_id' => $eid, 'status' => $st]);
                    }
                    $sessions++;
                }
                $day = $day->modify('+1 day');
            }
        }
        $log[] = 'Demo: attendance sheets for running and completed batches.';

        // ---- Final exams, results, completion & certificates for completed batches ----
        $nCert = 0;
        foreach ($batches as $b) {
            if ($b['status'] !== 'COMPLETED') {
                continue;
            }
            $xid = DB::insert('examinations', ['batch_id' => $b['id'], 'name' => 'Final Assessment', 'exam_type' => 'final', 'exam_date' => $b['end'],
                'theory_full_marks' => 60, 'practical_full_marks' => 40, 'is_final' => 1, 'status' => 'marks_entry', 'created_by' => $adminId]);
            $exam = DB::find('examinations', $xid);
            $subs = ExamService::subjects($exam);
            $enrs = DB::column("SELECT id FROM enrollments WHERE batch_id = :b AND status = 'active'", ['b' => $b['id']]);
            foreach ($enrs as $k => $eid) {
                $skill = $k === 1 ? 0.3 : mt_rand(45, 95) / 100;
                foreach ($subs as $s) {
                    DB::insert('examination_results', ['examination_id' => $xid, 'enrollment_id' => $eid, 'subject_id' => $s['id'],
                        'theory_marks' => round((float)$s['theory_full_marks'] * min(1, $skill + mt_rand(-5, 5) / 100)),
                        'practical_marks' => round((float)$s['practical_full_marks'] * min(1, $skill + mt_rand(-5, 8) / 100)), 'entered_by' => $adminId]);
                }
            }
            ExamService::publish($xid);
            DB::update('batches', ['batch_status_id' => $bs['COMPLETED'], 'completed_at' => $b['end'] . ' 17:00:00'], 'id = :id', ['id' => $b['id']]);
            foreach ($enrs as $eid) {
                $e = DB::row('SELECT * FROM enrollments WHERE id = :id', ['id' => $eid]);
                if (!CertificateService::problems($e)) {
                    CertificateService::issue($eid, (new \DateTimeImmutable($b['end']))->modify('+14 days')->format('Y-m-d'));
                    $nCert++;
                }
            }
        }
        $log[] = "Demo: final results published and $nCert certificates issued.";

        // ---- Expenses ----
        $heads = DB::pairs('SELECT name, id FROM expense_heads');
        $nExp = 0;
        foreach ($centers as $code => $c) {
            for ($m = 5; $m >= 0; $m--) {
                $monthDate = $today->modify("first day of -$m month");
                foreach ([['House Rent', 15000], ['Electricity Bill', mt_rand(2500, 5000)], ['Internet Bill', 1500], ['Training Materials', mt_rand(3000, 9000)], ['Stationery & Printing', mt_rand(800, 2500)]] as [$h, $amt]) {
                    $date = $monthDate->modify('+' . mt_rand(2, 25) . ' days')->format('Y-m-d');
                    if ($date > $today->format('Y-m-d')) {
                        continue;
                    }
                    $pending = $m === 0 && mt_rand(0, 2) === 0;
                    DB::insert('expenses', [
                        'voucher_no' => Sequence::next('voucher_no_format', 'DV-{CENTER}-{YY}-{SEQ5}', ['center' => $code]), 'expense_date' => $date,
                        'center_id' => $c['id'], 'expense_head_id' => $heads[$h], 'description' => $h . ' – ' . $monthDate->format('F Y'), 'amount' => $amt,
                        'payment_method_id' => $pm['Cash'], 'paid_to' => $h === 'House Rent' ? 'Landlord' : 'Vendor', 'status' => $pending ? 'pending' : 'approved',
                        'prepared_by' => $adminId, 'approved_by' => $pending ? null : $adminId, 'approved_at' => $pending ? null : $date . ' 16:00:00',
                    ]);
                    $nExp++;
                }
            }
        }
        $log[] = "Demo: $nExp expense vouchers.";

        // ---- Assets ----
        $acat = DB::pairs('SELECT name, id FROM asset_categories');
        $nAst = 0;
        foreach ($centers as $code => $c) {
            foreach ([['Desktop Computer', 'Computer & IT', 10, 45000], ['Multimedia Projector', 'Computer & IT', 1, 55000], ['Laser Printer', 'Computer & IT', 1, 22000],
                         ['Sewing Machine (Butterfly)', 'Sewing Machine', 6, 14000], ['Trainee Chair', 'Furniture', 25, 1800], ['Wiring Practice Board', 'Training Equipment', 4, 6000]] as [$nm, $cn, $qty, $price]) {
                for ($q = 1; $q <= min($qty, 3); $q++) {
                    $aid = DB::insert('assets', [
                        'asset_code' => Sequence::next('asset_code_format', 'AST-{CENTER}-{SEQ5}', ['center' => $code]), 'name' => $nm, 'asset_category_id' => $acat[$cn],
                        'serial_no' => strtoupper(substr(md5($code . $nm . $q), 0, 10)), 'purchase_date' => $d('-' . mt_rand(100, 1500) . ' days'), 'purchase_price' => $price,
                        'supplier' => ['Ryans Computers', 'Star Tech', 'Singer Bangladesh', 'Local Supplier'][mt_rand(0, 3)], 'center_id' => $c['id'],
                        'asset_condition' => ['new', 'good', 'good', 'fair'][mt_rand(0, 3)], 'warranty_expiry' => $d('+' . mt_rand(-200, 500) . ' days'),
                        'status' => mt_rand(0, 12) === 0 ? 'under_maintenance' : 'in_use', 'created_by' => $adminId,
                    ]);
                    DB::insert('asset_assignments', ['asset_id' => $aid, 'movement_type' => 'register', 'to_center_id' => $c['id'], 'movement_date' => $d('-100 days'), 'note' => 'Opening register', 'created_by' => $adminId]);
                    $nAst++;
                }
            }
        }
        $log[] = "Demo: $nAst assets registered.";
        unset($_SESSION['uid']);
        return $log;
    }
}
