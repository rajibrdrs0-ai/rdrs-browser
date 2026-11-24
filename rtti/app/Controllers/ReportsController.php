<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Export;

/**
 * Report engine. Every report supports date range, district, center, course, batch,
 * search, pagination, Excel/CSV export and print/PDF – limited to the user's centers.
 * Head-office users automatically get consolidated (all-center) figures.
 *
 * Definition keys:
 *   title, group, perm, sql (with {where}), center (column), district (column), date (column),
 *   course, batch (columns), q (searchable columns), columns [key => label], totals [keys], order, groupby
 */
final class ReportsController extends Controller
{
    private function defs(): array
    {
        $enr = "FROM enrollments e JOIN students s ON s.id = e.student_id JOIN courses c ON c.id = e.course_id JOIN batches b ON b.id = e.batch_id
                JOIN centers ctr ON ctr.id = e.center_id JOIN districts d ON d.id = ctr.district_id";
        $att = "(SELECT ROUND(SUM(ad.status IN ('present','late'))*100/COUNT(*),1) FROM attendance_details ad WHERE ad.enrollment_id = e.id)";
        return [
            // ---------------- Student reports ----------------
            'student_list' => ['title' => 'Student List', 'group' => 'Student', 'perm' => 'students.view',
                'sql' => "SELECT s.student_code, s.name, s.father_name, s.mobile, g.name gender, s.date_of_birth, el.name education, ctr.name center, d.name district, s.registration_date, s.status
                    FROM students s JOIN centers ctr ON ctr.id = s.center_id JOIN districts d ON d.id = ctr.district_id LEFT JOIN genders g ON g.id = s.gender_id
                    LEFT JOIN education_levels el ON el.id = s.education_level_id WHERE s.deleted_at IS NULL {where}",
                'center' => 's.center_id', 'district' => 'ctr.district_id', 'date' => 's.registration_date', 'q' => ['s.student_code', 's.name', 's.mobile', 's.father_name'],
                'course' => 'EXISTS:SELECT 1 FROM enrollments ex WHERE ex.student_id = s.id AND ex.course_id = :course', 'batch' => 'EXISTS:SELECT 1 FROM enrollments ex WHERE ex.student_id = s.id AND ex.batch_id = :batch',
                'columns' => ['student_code' => 'Student ID', 'name' => 'Name', 'father_name' => "Father's name", 'mobile' => 'Mobile', 'gender' => 'Gender', 'education' => 'Education',
                    'center' => 'Center', 'district' => 'District', 'registration_date' => 'Registered', 'status' => 'Status'], 'order' => 's.student_code'],
            'new_registrations' => ['title' => 'New Student Registration', 'group' => 'Student', 'perm' => 'students.view',
                'sql' => "SELECT s.registration_date, s.student_code, s.name, s.mobile, g.name gender, ctr.name center, u.name registered_by FROM students s JOIN centers ctr ON ctr.id = s.center_id
                    JOIN districts d ON d.id = ctr.district_id LEFT JOIN genders g ON g.id = s.gender_id LEFT JOIN users u ON u.id = s.created_by WHERE s.deleted_at IS NULL {where}",
                'center' => 's.center_id', 'district' => 'ctr.district_id', 'date' => 's.registration_date', 'default_days' => 30, 'q' => ['s.name', 's.student_code'],
                'columns' => ['registration_date' => 'Date', 'student_code' => 'Student ID', 'name' => 'Name', 'mobile' => 'Mobile', 'gender' => 'Gender', 'center' => 'Center', 'registered_by' => 'Registered by'],
                'order' => 's.registration_date DESC'],
            'course_students' => ['title' => 'Course-wise Students', 'group' => 'Student', 'perm' => 'enrollments.view',
                'sql' => "SELECT c.name course, b.batch_code, s.student_code, s.name, s.mobile, ctr.name center, e.enrollment_date, e.status, e.result_grade $enr WHERE e.status <> 'transferred' {where}",
                'center' => 'e.center_id', 'district' => 'ctr.district_id', 'date' => 'e.enrollment_date', 'course' => 'e.course_id', 'batch' => 'e.batch_id', 'q' => ['s.name', 's.student_code'],
                'columns' => ['course' => 'Course', 'batch_code' => 'Batch', 'student_code' => 'Student ID', 'name' => 'Name', 'mobile' => 'Mobile', 'center' => 'Center', 'enrollment_date' => 'Enrolled', 'status' => 'Status', 'result_grade' => 'Grade'],
                'order' => 'c.name, b.batch_code, s.name'],
            'batch_students' => ['title' => 'Batch-wise Students', 'group' => 'Student', 'perm' => 'enrollments.view',
                'sql' => "SELECT b.batch_code, s.student_code, s.name, s.father_name, s.mobile, e.enrollment_code, e.status, $att attendance, e.due_amount $enr WHERE e.status NOT IN ('transferred','cancelled') {where}",
                'center' => 'e.center_id', 'district' => 'ctr.district_id', 'course' => 'e.course_id', 'batch' => 'e.batch_id', 'q' => ['s.name', 's.student_code'],
                'columns' => ['batch_code' => 'Batch', 'student_code' => 'Student ID', 'name' => 'Name', 'father_name' => "Father's name", 'mobile' => 'Mobile', 'enrollment_code' => 'Enrollment', 'status' => 'Status', 'attendance' => 'Attendance %', 'due_amount' => 'Due'],
                'totals' => ['due_amount'], 'order' => 'b.batch_code, s.name'],
            'center_students' => ['title' => 'Center-wise Students (summary)', 'group' => 'Student', 'perm' => 'students.view',
                'sql' => "SELECT ctr.name center, d.name district, COUNT(DISTINCT s.id) registered,
                    SUM(g.name = 'Male') male, SUM(g.name = 'Female') female,
                    (SELECT COUNT(DISTINCT e.student_id) FROM enrollments e WHERE e.center_id = ctr.id AND e.status = 'active') active
                    FROM students s JOIN centers ctr ON ctr.id = s.center_id JOIN districts d ON d.id = ctr.district_id LEFT JOIN genders g ON g.id = s.gender_id
                    WHERE s.deleted_at IS NULL {where} GROUP BY ctr.id",
                'center' => 's.center_id', 'district' => 'ctr.district_id', 'date' => 's.registration_date',
                'columns' => ['center' => 'Center', 'district' => 'District', 'registered' => 'Registered', 'male' => 'Male', 'female' => 'Female', 'active' => 'Currently active'],
                'totals' => ['registered', 'male', 'female', 'active'], 'order' => 'ctr.name'],
            'district_students' => ['title' => 'District-wise Students (consolidated)', 'group' => 'Student', 'perm' => 'students.view',
                'sql' => "SELECT dv.name division, d.name district, COUNT(DISTINCT ctr.id) centers, COUNT(s.id) students, SUM(g.name = 'Female') female,
                    (SELECT COUNT(*) FROM enrollments e JOIN centers c2 ON c2.id = e.center_id WHERE c2.district_id = d.id AND e.status NOT IN ('cancelled','transferred')) enrollments
                    FROM students s JOIN centers ctr ON ctr.id = s.center_id JOIN districts d ON d.id = ctr.district_id JOIN divisions dv ON dv.id = d.division_id LEFT JOIN genders g ON g.id = s.gender_id
                    WHERE s.deleted_at IS NULL {where} GROUP BY d.id",
                'center' => 's.center_id', 'district' => 'ctr.district_id', 'date' => 's.registration_date',
                'columns' => ['division' => 'Division', 'district' => 'District', 'centers' => 'Centers', 'students' => 'Students', 'female' => 'Female', 'enrollments' => 'Enrollments'],
                'totals' => ['centers', 'students', 'female', 'enrollments'], 'order' => 'dv.name, d.name'],
            // ---------------- Academic reports ----------------
            'course_list' => ['title' => 'Course List', 'group' => 'Academic', 'perm' => 'courses.view',
                'sql' => "SELECT c.code, c.name, t.name trade, CONCAT(c.duration, ' ', c.duration_unit) duration, c.fee, cs.name status,
                    (SELECT COUNT(*) FROM batches b WHERE b.course_id = c.id AND b.deleted_at IS NULL) batches,
                    (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.status NOT IN ('cancelled','transferred')) enrollments
                    FROM courses c JOIN trades t ON t.id = c.trade_id JOIN course_statuses cs ON cs.id = c.course_status_id WHERE c.deleted_at IS NULL {where}",
                'q' => ['c.code', 'c.name'], 'course' => 'c.id',
                'columns' => ['code' => 'Code', 'name' => 'Course', 'trade' => 'Trade', 'duration' => 'Duration', 'fee' => 'Fee', 'status' => 'Status', 'batches' => 'Batches', 'enrollments' => 'Enrollments'], 'order' => 'c.name'],
            'batch_list' => ['title' => 'Batch List', 'group' => 'Academic', 'perm' => 'batches.view', 'sql' => $this->batchSql(''), 'center' => 'b.center_id', 'district' => 'ctr.district_id', 'date' => 'b.start_date',
                'course' => 'b.course_id', 'batch' => 'b.id', 'q' => ['b.batch_code', 'b.name'], 'columns' => $this->batchCols(), 'totals' => ['enrolled'], 'order' => 'b.start_date DESC'],
            'running_batches' => ['title' => 'Running Batches', 'group' => 'Academic', 'perm' => 'batches.view', 'sql' => $this->batchSql("AND bs.code = 'RUNNING'"), 'center' => 'b.center_id', 'district' => 'ctr.district_id',
                'course' => 'b.course_id', 'q' => ['b.batch_code', 'b.name'], 'columns' => $this->batchCols(), 'totals' => ['enrolled'], 'order' => 'b.end_date'],
            'completed_batches' => ['title' => 'Completed Batches', 'group' => 'Academic', 'perm' => 'batches.view', 'sql' => $this->batchSql("AND bs.code = 'COMPLETED'"), 'center' => 'b.center_id', 'district' => 'ctr.district_id',
                'date' => 'b.end_date', 'course' => 'b.course_id', 'q' => ['b.batch_code', 'b.name'], 'columns' => $this->batchCols() + ['passed' => 'Passed', 'certificates' => 'Certificates'], 'totals' => ['enrolled', 'passed', 'certificates'], 'order' => 'b.end_date DESC'],
            'attendance' => ['title' => 'Attendance Summary', 'group' => 'Academic', 'perm' => 'attendance.view',
                'sql' => "SELECT b.batch_code, s.student_code, s.name, COUNT(ad.id) classes, SUM(ad.status='present') present, SUM(ad.status='late') late, SUM(ad.status='leave') leave_, SUM(ad.status='absent') absent,
                    ROUND(SUM(ad.status IN ('present','late'))*100/NULLIF(COUNT(ad.id),0),1) pct
                    $enr JOIN attendance_details ad ON ad.enrollment_id = e.id JOIN attendance a ON a.id = ad.attendance_id WHERE 1=1 {where} GROUP BY e.id",
                'center' => 'e.center_id', 'district' => 'ctr.district_id', 'date' => 'a.attendance_date', 'course' => 'e.course_id', 'batch' => 'e.batch_id', 'q' => ['s.name', 's.student_code'],
                'columns' => ['batch_code' => 'Batch', 'student_code' => 'Student ID', 'name' => 'Name', 'classes' => 'Classes', 'present' => 'Present', 'late' => 'Late', 'leave_' => 'Leave', 'absent' => 'Absent', 'pct' => 'Attendance %'],
                'order' => 'b.batch_code, pct'],
            'exam_results' => ['title' => 'Examination Results (final)', 'group' => 'Academic', 'perm' => 'exams.view',
                'sql' => "SELECT b.batch_code, c.name course, s.student_code, s.name, e.result_percent, e.result_grade, e.result_status, ctr.name center $enr WHERE e.result_status IS NOT NULL {where}",
                'center' => 'e.center_id', 'district' => 'ctr.district_id', 'date' => 'b.end_date', 'course' => 'e.course_id', 'batch' => 'e.batch_id', 'q' => ['s.name', 's.student_code'],
                'columns' => ['batch_code' => 'Batch', 'course' => 'Course', 'student_code' => 'Student ID', 'name' => 'Name', 'result_percent' => '%', 'result_grade' => 'Grade', 'result_status' => 'Result', 'center' => 'Center'],
                'order' => 'b.batch_code, e.result_percent DESC'],
            'course_completion' => ['title' => 'Course Completion', 'group' => 'Academic', 'perm' => 'enrollments.view',
                'sql' => "SELECT c.name course, ctr.name center, COUNT(*) enrolled, SUM(e.status='active') active, SUM(e.status='completed') completed, SUM(e.status='dropped') dropped,
                    SUM(e.status='cancelled') cancelled, SUM(e.result_status='pass') passed, SUM(e.result_status='fail') failed,
                    (SELECT COUNT(*) FROM certificates ce WHERE ce.course_id = c.id AND ce.center_id = ctr.id AND ce.status = 'issued') certificates,
                    ROUND(SUM(e.status='completed')*100/COUNT(*),1) completion_rate
                    $enr WHERE e.status <> 'transferred' {where} GROUP BY c.id, ctr.id",
                'center' => 'e.center_id', 'district' => 'ctr.district_id', 'date' => 'e.enrollment_date', 'course' => 'e.course_id', 'batch' => 'e.batch_id',
                'columns' => ['course' => 'Course', 'center' => 'Center', 'enrolled' => 'Enrolled', 'active' => 'Active', 'completed' => 'Completed', 'dropped' => 'Dropped', 'cancelled' => 'Cancelled',
                    'passed' => 'Passed', 'failed' => 'Failed', 'certificates' => 'Certificates', 'completion_rate' => 'Completion %'],
                'totals' => ['enrolled', 'active', 'completed', 'dropped', 'cancelled', 'passed', 'failed', 'certificates'], 'order' => 'c.name, ctr.name'],
            // ---------------- Financial reports ----------------
            'fee_collection' => ['title' => 'Fee Collection', 'group' => 'Financial', 'perm' => 'reports.financial',
                'sql' => "SELECT f.payment_date, f.receipt_no, s.student_code, s.name, c.name course, b.batch_code, ctr.name center, pm.name method, f.amount
                    FROM fee_collections f JOIN enrollments e ON e.id = f.enrollment_id JOIN students s ON s.id = f.student_id JOIN courses c ON c.id = e.course_id JOIN batches b ON b.id = e.batch_id
                    JOIN centers ctr ON ctr.id = f.center_id JOIN districts d ON d.id = ctr.district_id JOIN payment_methods pm ON pm.id = f.payment_method_id WHERE f.status = 'posted' {where}",
                'center' => 'f.center_id', 'district' => 'ctr.district_id', 'date' => 'f.payment_date', 'default_days' => 31, 'course' => 'e.course_id', 'batch' => 'e.batch_id', 'q' => ['f.receipt_no', 's.name', 's.student_code'],
                'columns' => ['payment_date' => 'Date', 'receipt_no' => 'Receipt', 'student_code' => 'Student ID', 'name' => 'Student', 'course' => 'Course', 'batch_code' => 'Batch', 'center' => 'Center', 'method' => 'Method', 'amount' => 'Amount'],
                'totals' => ['amount'], 'order' => 'f.payment_date, f.id'],
            'due_list' => ['title' => 'Due List', 'group' => 'Financial', 'perm' => 'reports.financial',
                'sql' => "SELECT s.student_code, s.name, s.mobile, c.name course, b.batch_code, ctr.name center, e.payable_amount, e.paid_amount, e.due_amount $enr WHERE e.status IN ('pending','active','completed') AND e.due_amount > 0 {where}",
                'center' => 'e.center_id', 'district' => 'ctr.district_id', 'date' => 'e.enrollment_date', 'course' => 'e.course_id', 'batch' => 'e.batch_id', 'q' => ['s.name', 's.student_code', 's.mobile'],
                'columns' => ['student_code' => 'Student ID', 'name' => 'Name', 'mobile' => 'Mobile', 'course' => 'Course', 'batch_code' => 'Batch', 'center' => 'Center', 'payable_amount' => 'Payable', 'paid_amount' => 'Paid', 'due_amount' => 'Due'],
                'totals' => ['payable_amount', 'paid_amount', 'due_amount'], 'order' => 'e.due_amount DESC'],
            'daily_collection' => ['title' => 'Daily Collection', 'group' => 'Financial', 'perm' => 'reports.financial',
                'sql' => "SELECT f.payment_date, ctr.name center, COUNT(*) receipts, SUM(IF(pm.name = 'Cash', f.amount, 0)) cash, SUM(IF(pm.name <> 'Cash', f.amount, 0)) non_cash, SUM(f.amount) total
                    FROM fee_collections f JOIN centers ctr ON ctr.id = f.center_id JOIN districts d ON d.id = ctr.district_id JOIN payment_methods pm ON pm.id = f.payment_method_id
                    JOIN enrollments e ON e.id = f.enrollment_id WHERE f.status = 'posted' {where} GROUP BY f.payment_date, ctr.id",
                'center' => 'f.center_id', 'district' => 'ctr.district_id', 'date' => 'f.payment_date', 'default_days' => 31, 'course' => 'e.course_id', 'batch' => 'e.batch_id',
                'columns' => ['payment_date' => 'Date', 'center' => 'Center', 'receipts' => 'Receipts', 'cash' => 'Cash', 'non_cash' => 'Bank / mobile', 'total' => 'Total'],
                'totals' => ['receipts', 'cash', 'non_cash', 'total'], 'order' => 'f.payment_date DESC, ctr.name'],
            'monthly_collection' => ['title' => 'Monthly Collection', 'group' => 'Financial', 'perm' => 'reports.financial',
                'sql' => "SELECT DATE_FORMAT(f.payment_date, '%Y-%m') month, ctr.name center, COUNT(*) receipts, SUM(f.amount) total
                    FROM fee_collections f JOIN centers ctr ON ctr.id = f.center_id JOIN districts d ON d.id = ctr.district_id JOIN enrollments e ON e.id = f.enrollment_id
                    WHERE f.status = 'posted' {where} GROUP BY month, ctr.id",
                'center' => 'f.center_id', 'district' => 'ctr.district_id', 'date' => 'f.payment_date', 'default_days' => 365, 'course' => 'e.course_id',
                'columns' => ['month' => 'Month', 'center' => 'Center', 'receipts' => 'Receipts', 'total' => 'Amount'], 'totals' => ['receipts', 'total'], 'order' => 'month DESC, ctr.name'],
            'center_income' => ['title' => 'Center-wise Income & Expense (consolidated)', 'group' => 'Financial', 'perm' => 'reports.financial',
                'sql' => "SELECT ctr.name center, d.name district,
                    (SELECT COALESCE(SUM(f.amount),0) FROM fee_collections f WHERE f.center_id = ctr.id AND f.status = 'posted' AND f.payment_date BETWEEN :from AND :to) income,
                    (SELECT COALESCE(SUM(x.amount),0) FROM expenses x WHERE x.center_id = ctr.id AND x.status = 'approved' AND x.expense_date BETWEEN :from2 AND :to2) expense,
                    (SELECT COALESCE(SUM(e.due_amount),0) FROM enrollments e WHERE e.center_id = ctr.id AND e.status IN ('pending','active','completed')) outstanding
                    FROM centers ctr JOIN districts d ON d.id = ctr.district_id WHERE ctr.is_active = 1 {where}",
                'center' => 'ctr.id', 'district' => 'ctr.district_id', 'date_params' => true, 'default_days' => 365,
                'columns' => ['center' => 'Center', 'district' => 'District', 'income' => 'Fee income', 'expense' => 'Expenses', 'net' => ['Net', fn($r) => number_format($r['income'] - $r['expense'], 2)], 'outstanding' => 'Outstanding due'],
                'totals' => ['income', 'expense', 'outstanding'], 'order' => 'ctr.name'],
            'expense_report' => ['title' => 'Expense Report', 'group' => 'Financial', 'perm' => 'reports.financial',
                'sql' => "SELECT x.expense_date, x.voucher_no, ctr.name center, ec.name category, h.name head, x.description, pm.name method, x.amount
                    FROM expenses x JOIN centers ctr ON ctr.id = x.center_id JOIN districts d ON d.id = ctr.district_id JOIN expense_heads h ON h.id = x.expense_head_id
                    JOIN expense_categories ec ON ec.id = h.expense_category_id JOIN payment_methods pm ON pm.id = x.payment_method_id WHERE x.status = 'approved' {where}",
                'center' => 'x.center_id', 'district' => 'ctr.district_id', 'date' => 'x.expense_date', 'default_days' => 31, 'q' => ['x.voucher_no', 'x.description', 'h.name'],
                'columns' => ['expense_date' => 'Date', 'voucher_no' => 'Voucher', 'center' => 'Center', 'category' => 'Category', 'head' => 'Head', 'description' => 'Description', 'method' => 'Method', 'amount' => 'Amount'],
                'totals' => ['amount'], 'order' => 'x.expense_date, x.id'],
            'expense_by_head' => ['title' => 'Monthly Expense by Head', 'group' => 'Financial', 'perm' => 'reports.financial',
                'sql' => "SELECT DATE_FORMAT(x.expense_date, '%Y-%m') month, ec.name category, h.name head, COUNT(*) vouchers, SUM(x.amount) amount
                    FROM expenses x JOIN centers ctr ON ctr.id = x.center_id JOIN expense_heads h ON h.id = x.expense_head_id JOIN expense_categories ec ON ec.id = h.expense_category_id
                    WHERE x.status = 'approved' {where} GROUP BY month, h.id",
                'center' => 'x.center_id', 'district' => 'ctr.district_id', 'date' => 'x.expense_date', 'default_days' => 180,
                'columns' => ['month' => 'Month', 'category' => 'Category', 'head' => 'Expense head', 'vouchers' => 'Vouchers', 'amount' => 'Amount'], 'totals' => ['vouchers', 'amount'], 'order' => 'month DESC, amount DESC'],
            'income_vs_expense' => ['title' => 'Income vs Expense (monthly)', 'group' => 'Financial', 'perm' => 'reports.financial',
                'sql' => "SELECT m.month, COALESCE(i.income,0) income, COALESCE(x.expense,0) expense FROM
                    (SELECT DATE_FORMAT(f.payment_date,'%Y-%m') month FROM fee_collections f JOIN centers ctr ON ctr.id = f.center_id WHERE f.payment_date BETWEEN :from AND :to {cwhere:f.center_id}
                     UNION SELECT DATE_FORMAT(x.expense_date,'%Y-%m') FROM expenses x JOIN centers ctr ON ctr.id = x.center_id WHERE x.expense_date BETWEEN :from2 AND :to2 {cwhere:x.center_id}) m
                    LEFT JOIN (SELECT DATE_FORMAT(f.payment_date,'%Y-%m') month, SUM(f.amount) income FROM fee_collections f JOIN centers ctr ON ctr.id = f.center_id WHERE f.status = 'posted' AND f.payment_date BETWEEN :from3 AND :to3 {cwhere:f.center_id} GROUP BY 1) i ON i.month = m.month
                    LEFT JOIN (SELECT DATE_FORMAT(x.expense_date,'%Y-%m') month, SUM(x.amount) expense FROM expenses x JOIN centers ctr ON ctr.id = x.center_id WHERE x.status = 'approved' AND x.expense_date BETWEEN :from4 AND :to4 {cwhere:x.center_id} GROUP BY 1) x ON x.month = m.month
                    WHERE 1=1 {where}",
                'date_params' => 4, 'default_days' => 365, 'cwhere' => true,
                'columns' => ['month' => 'Month', 'income' => 'Income', 'expense' => 'Expense', 'net' => ['Surplus / (deficit)', fn($r) => number_format($r['income'] - $r['expense'], 2)]],
                'totals' => ['income', 'expense'], 'order' => 'm.month DESC'],
            'cash_summary' => ['title' => 'Cash Summary (by payment method)', 'group' => 'Financial', 'perm' => 'reports.financial',
                'sql' => "SELECT pm.name method,
                    (SELECT COALESCE(SUM(f.amount),0) FROM fee_collections f JOIN centers ctr ON ctr.id = f.center_id WHERE f.payment_method_id = pm.id AND f.status = 'posted' AND f.payment_date BETWEEN :from AND :to {cwhere:f.center_id}) received,
                    (SELECT COALESCE(SUM(x.amount),0) FROM expenses x JOIN centers ctr ON ctr.id = x.center_id WHERE x.payment_method_id = pm.id AND x.status = 'approved' AND x.expense_date BETWEEN :from2 AND :to2 {cwhere:x.center_id}) paid
                    FROM payment_methods pm WHERE 1=1 {where}",
                'date_params' => 2, 'default_days' => 31, 'cwhere' => true,
                'columns' => ['method' => 'Method', 'received' => 'Received (fees)', 'paid' => 'Paid (expenses)', 'balance' => ['Net movement', fn($r) => number_format($r['received'] - $r['paid'], 2)]],
                'totals' => ['received', 'paid'], 'order' => 'pm.id'],
            // ---------------- Administrative ----------------
            'staff_list' => ['title' => 'Staff List', 'group' => 'Administrative', 'perm' => 'staff.view',
                'sql' => "SELECT st.employee_code, st.name, dg.name designation, ctr.name center, d.name district, st.mobile, st.joining_date, IF(st.is_trainer, 'Yes', '') trainer, st.employment_status
                    FROM staff st JOIN centers ctr ON ctr.id = st.center_id JOIN districts d ON d.id = ctr.district_id LEFT JOIN designations dg ON dg.id = st.designation_id WHERE st.deleted_at IS NULL {where}",
                'center' => 'st.center_id', 'district' => 'ctr.district_id', 'date' => 'st.joining_date', 'q' => ['st.name', 'st.employee_code'],
                'columns' => ['employee_code' => 'Employee ID', 'name' => 'Name', 'designation' => 'Designation', 'center' => 'Center', 'district' => 'District', 'mobile' => 'Mobile', 'joining_date' => 'Joined', 'trainer' => 'Trainer', 'employment_status' => 'Status'],
                'order' => 'ctr.name, st.name'],
            'asset_register' => ['title' => 'Asset Register', 'group' => 'Administrative', 'perm' => 'assets.view',
                'sql' => "SELECT a.asset_code, a.name, ac.name category, a.serial_no, ctr.name center, a.purchase_date, a.purchase_price, a.asset_condition, a.status
                    FROM assets a JOIN asset_categories ac ON ac.id = a.asset_category_id JOIN centers ctr ON ctr.id = a.center_id WHERE a.deleted_at IS NULL {where}",
                'center' => 'a.center_id', 'district' => 'ctr.district_id', 'date' => 'a.purchase_date', 'q' => ['a.asset_code', 'a.name', 'a.serial_no'],
                'columns' => ['asset_code' => 'Asset ID', 'name' => 'Asset', 'category' => 'Category', 'serial_no' => 'Serial', 'center' => 'Center', 'purchase_date' => 'Purchased', 'purchase_price' => 'Price', 'asset_condition' => 'Condition', 'status' => 'Status'],
                'totals' => ['purchase_price'], 'order' => 'ctr.name, a.asset_code'],
            'center_assets' => ['title' => 'Center-wise Assets (summary)', 'group' => 'Administrative', 'perm' => 'assets.view',
                'sql' => "SELECT ctr.name center, ac.name category, COUNT(*) quantity, SUM(a.status = 'in_use') in_use, SUM(a.status = 'under_maintenance') maintenance, SUM(a.status IN ('disposed','lost')) disposed, SUM(a.purchase_price) value
                    FROM assets a JOIN asset_categories ac ON ac.id = a.asset_category_id JOIN centers ctr ON ctr.id = a.center_id WHERE a.deleted_at IS NULL {where} GROUP BY ctr.id, ac.id",
                'center' => 'a.center_id', 'district' => 'ctr.district_id',
                'columns' => ['center' => 'Center', 'category' => 'Category', 'quantity' => 'Quantity', 'in_use' => 'In use', 'maintenance' => 'Maintenance', 'disposed' => 'Disposed/lost', 'value' => 'Purchase value'],
                'totals' => ['quantity', 'in_use', 'maintenance', 'disposed', 'value'], 'order' => 'ctr.name, ac.name'],
        ];
    }

    private function batchSql(string $extra): string
    {
        return "SELECT b.batch_code, b.name, c.name course, ctr.name center, st.name trainer, b.start_date, b.end_date, bs.name status, b.max_students,
            (SELECT COUNT(*) FROM enrollments e WHERE e.batch_id = b.id AND e.status IN ('pending','active','completed')) enrolled,
            (SELECT COUNT(*) FROM enrollments e WHERE e.batch_id = b.id AND e.result_status = 'pass') passed,
            (SELECT COUNT(*) FROM certificates ce WHERE ce.batch_id = b.id AND ce.status = 'issued') certificates
            FROM batches b JOIN courses c ON c.id = b.course_id JOIN centers ctr ON ctr.id = b.center_id JOIN batch_statuses bs ON bs.id = b.batch_status_id
            LEFT JOIN staff st ON st.id = b.trainer_id WHERE b.deleted_at IS NULL $extra {where}";
    }

    private function batchCols(): array
    {
        return ['batch_code' => 'Batch', 'course' => 'Course', 'center' => 'Center', 'trainer' => 'Trainer', 'start_date' => 'Start', 'end_date' => 'End', 'status' => 'Status', 'max_students' => 'Capacity', 'enrolled' => 'Enrolled'];
    }

    public function index(): void
    {
        $this->authorize('reports.view');
        $groups = [];
        foreach ($this->defs() as $k => $d) {
            if (Auth::can($d['perm'])) {
                $groups[$d['group']][$k] = $d['title'];
            }
        }
        $extra = [];
        if (Auth::can('students.view')) {
            $extra['Student'][] = ['Student Profile (print)', url('students/index'), 'Open a student → Print profile'];
        }
        if (Auth::can('fees.view')) {
            $extra['Financial'][] = ['Student Ledger', url('fees/ledger'), 'Per-student debit/credit ledger'];
        }
        if (Auth::can('attendance.view')) {
            $extra['Academic'][] = ['Monthly Attendance Register', url('attendance/report'), 'Students × dates matrix'];
        }
        if (Auth::can('exams.view')) {
            $extra['Academic'][] = ['Result Sheet & Merit List', url('exams/index'), 'Per examination'];
        }
        if (Auth::can('audit.view')) {
            $extra['Administrative'][] = ['User Activity', url('audit/index'), 'Audit trail of all actions'];
        }
        if (Auth::can('users.logs')) {
            $extra['Administrative'][] = ['Login History', url('users/logins'), 'Successful & failed sign-ins'];
        }
        $this->render('reports/index', ['title' => 'Reports', 'groups' => $groups, 'extra' => $extra, 'breadcrumbs' => ['Reports' => null]]);
    }

    public function run(): void
    {
        $this->authorize('reports.view');
        $type = (string)input('type');
        $def = $this->defs()[$type] ?? $this->notFound('Report');
        $this->authorize($def['perm']);
        $where = '';
        $params = [];
        // center scope + filters
        $cwhere = Auth::scope('ctr.id');
        if ($c = input_id('center_id')) {
            $this->authorizeCenter($c);
            $cwhere .= ' AND ctr.id = ' . (int)$c;
        }
        if ($d = input_id('district_id')) {
            $cwhere .= ' AND ctr.district_id = ' . (int)$d;
        }
        if (!empty($def['center'])) {
            $where .= $cwhere;
        }
        $from = input_date('from');
        $to = input_date('to');
        if (!empty($def['default_days'])) {
            $from ??= date('Y-m-d', strtotime('-' . $def['default_days'] . ' days'));
            $to ??= date('Y-m-d');
        }
        if (!empty($def['date']) && ($from || $to)) {
            if ($from) {
                $where .= " AND {$def['date']} >= :from";
                $params['from'] = $from;
            }
            if ($to) {
                $where .= " AND {$def['date']} <= :to";
                $params['to'] = $to;
            }
        }
        if (!empty($def['date_params'])) {
            $from ??= '2000-01-01';
            $to ??= date('Y-m-d');
            $n = $def['date_params'] === true ? 2 : (int)$def['date_params'];
            foreach (range(1, $n) as $i) {
                $sfx = $i === 1 ? '' : (string)$i;
                $params["from$sfx"] = $from;
                $params["to$sfx"] = $to;
            }
        }
        foreach (['course' => 'course_id', 'batch' => 'batch_id'] as $k => $in) {
            if (!empty($def[$k]) && ($v = input_id($in))) {
                if (str_starts_with($def[$k], 'EXISTS:')) {
                    $where .= ' AND EXISTS (' . substr($def[$k], 7) . ')';
                } else {
                    $where .= " AND {$def[$k]} = :$k";
                }
                $params[$k] = $v;
            }
        }
        if (!empty($def['q']) && ($q = input('q'))) {
            $parts = [];
            foreach ($def['q'] as $i => $col) {
                $parts[] = "$col LIKE :q$i";
                $params["q$i"] = "%$q%";
            }
            $where .= ' AND (' . implode(' OR ', $parts) . ')';
        }
        $sql = str_replace('{where}', $where, $def['sql']);
        $sql = preg_replace_callback('/\{cwhere:([a-z_.]+)\}/', fn($m) => $cwhere, $sql);
        $sql .= ' ORDER BY ' . $def['order'];

        $totals = [];
        if (!empty($def['totals'])) {
            $all = DB::all($sql, $params);
            foreach ($def['totals'] as $tcol) {
                $totals[$tcol] = array_sum(array_map(fn($r) => (float)($r[$tcol] ?? 0), $all));
            }
        }
        $subtitle = trim(($from || $to ? fmt_date($from) . ' – ' . fmt_date($to) : 'All dates') . ' · ' . ($c ?? null ? DB::value('SELECT name FROM centers WHERE id = :c', ['c' => $c]) : (Auth::isHeadOffice() ? 'All centers (consolidated)' : 'My centers')));
        if ($fmt = Export::requested()) {
            $tot = [];
            if ($totals) {
                $tot[array_key_first($def['columns'])] = 'TOTAL';
                foreach ($totals as $k => $v) {
                    $tot[$k] = number_format($v, fmod($v, 1.0) == 0.0 && !str_contains($k, 'amount') && !in_array($k, ['income', 'expense', 'total', 'cash', 'non_cash', 'value', 'purchase_price', 'received', 'paid', 'outstanding', 'payable_amount', 'paid_amount', 'due_amount'], true) ? 0 : 2);
                }
            }
            Export::send($fmt, $def['title'], $def['columns'], $all ?? DB::all($sql, $params), ['subtitle' => $subtitle, 'totals' => $tot]);
        }
        $this->render('reports/run', ['title' => $def['title'], 'type' => $type, 'def' => $def, 'p' => DB::paginate($sql, $params, 50), 'totals' => $totals, 'subtitle' => $subtitle,
            'from' => $from, 'to' => $to,
            'courses' => !empty($def['course']) ? DB::all('SELECT id, name FROM courses WHERE deleted_at IS NULL ORDER BY name') : [],
            'batches' => !empty($def['batch']) ? DB::all("SELECT b.id, b.batch_code name FROM batches b WHERE b.deleted_at IS NULL" . Auth::scope('b.center_id') . ' ORDER BY b.start_date DESC') : [],
            'breadcrumbs' => ['Reports' => url('reports/index'), $def['title'] => null]]);
    }
}
