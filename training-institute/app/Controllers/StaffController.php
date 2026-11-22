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
use App\Services\Rbac;
use App\Services\Sequence;

/**
 * Staff & trainers per center. A staff record can be linked to a login
 * account; trainer accounts then see only the batches they teach.
 */
final class StaffController extends Controller
{
    private const EMPLOYMENT = ['permanent', 'contractual', 'part_time', 'guest'];
    private const STATUSES = ['active', 'on_leave', 'resigned', 'terminated'];

    public function index(Request $request): Response
    {
        [$scopeSql, $params] = Rbac::centerFilter('s.center_id');
        $where = [$scopeSql];
        foreach (['center_id' => 's.center_id', 'designation_id' => 's.designation_id'] as $key => $col) {
            $v = $this->intOrNull($request->query($key));
            if ($v !== null) {
                $where[] = "{$col} = ?";
                $params[] = $v;
            }
        }
        $status = (string) $request->query('status', 'active');
        if (in_array($status, self::STATUSES, true)) {
            $where[] = 's.status = ?';
            $params[] = $status;
        }
        if ((string) $request->query('trainers') === '1') {
            $where[] = 'd.is_trainer = 1';
        }
        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $where[] = '(s.name LIKE ? OR s.name_bn LIKE ? OR s.mobile LIKE ? OR s.employee_code LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like, $like, $like);
        }
        $result = $this->paginate(
            's.*, d.name_en AS designation_en, d.name_bn AS designation_bn, d.is_trainer, c.code AS center_code,
             c.name_en AS center_en, c.name_bn AS center_bn, u.username AS login_username',
            'FROM staff s JOIN designations d ON d.id = s.designation_id JOIN training_centers c ON c.id = s.center_id
             LEFT JOIN users u ON u.staff_id = s.id WHERE ' . implode(' AND ', $where),
            $params, 'c.name_en, d.is_trainer, s.name'
        );
        return $this->view('staff/index', $result + [
            'centers'      => $this->centers(),
            'designations' => Database::all('SELECT * FROM designations ORDER BY name_en'),
            'filters'      => ['q' => $q, 'center_id' => $request->query('center_id', ''), 'designation_id' => $request->query('designation_id', ''),
                               'status' => $status, 'trainers' => $request->query('trainers', '')],
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->view('staff/form', $this->formData(null));
    }

    public function store(Request $request): Response
    {
        $data = $this->validated($request, null);
        $id = Database::transaction(function () use ($data): int {
            $code = (string) Database::value('SELECT code FROM training_centers WHERE id = ?', [$data['center_id']]);
            $data['employee_code'] = $code . '-' . str_pad((string) Sequence::next('staff', $code), 4, '0', STR_PAD_LEFT);
            Database::query(
                'INSERT INTO staff (employee_code, center_id, designation_id, name, name_bn, gender, date_of_birth, nid_no, mobile, email,
                                    address, qualification, trainer_certificate_no, employment_type, joining_date, leaving_date,
                                    monthly_salary, status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$data['employee_code'], $data['center_id'], $data['designation_id'], $data['name'], $data['name_bn'], $data['gender'],
                 $data['date_of_birth'], $data['nid_no'], $data['mobile'], $data['email'], $data['address'], $data['qualification'],
                 $data['trainer_certificate_no'], $data['employment_type'], $data['joining_date'], $data['leaving_date'],
                 $data['monthly_salary'], $data['status'], \App\Services\Auth::id()]
            );
            $id = Database::lastInsertId();
            $this->linkUser($id, $data['user_id']);
            Audit::log('create', 'staff', $id, null, $data, $data['center_id']);
            return $id;
        });
        Session::flash('success', __('staff.saved', ['name' => $data['name']]));
        return $this->redirect('/staff/' . $id . '/edit');
    }

    public function edit(Request $request, string $id): Response
    {
        return $this->view('staff/form', $this->formData($this->find((int) $id)));
    }

    public function update(Request $request, string $id): Response
    {
        $staff = $this->find((int) $id);
        $data = $this->validated($request, $staff);
        Database::transaction(function () use ($staff, $data): void {
            Database::query(
                'UPDATE staff SET center_id = ?, designation_id = ?, name = ?, name_bn = ?, gender = ?, date_of_birth = ?, nid_no = ?, mobile = ?,
                        email = ?, address = ?, qualification = ?, trainer_certificate_no = ?, employment_type = ?, joining_date = ?,
                        leaving_date = ?, monthly_salary = ?, status = ? WHERE id = ?',
                [$data['center_id'], $data['designation_id'], $data['name'], $data['name_bn'], $data['gender'], $data['date_of_birth'],
                 $data['nid_no'], $data['mobile'], $data['email'], $data['address'], $data['qualification'], $data['trainer_certificate_no'],
                 $data['employment_type'], $data['joining_date'], $data['leaving_date'], $data['monthly_salary'], $data['status'], $staff['id']]
            );
            $this->linkUser((int) $staff['id'], $data['user_id']);
            Audit::log('update', 'staff', (int) $staff['id'], ...[...Audit::diff($staff, $data), $data['center_id']]);
        });
        Session::flash('success', __('staff.saved', ['name' => $data['name']]));
        return $this->redirect('/staff');
    }

    // -----------------------------------------------------------------

    private function linkUser(int $staffId, ?int $userId): void
    {
        Database::query('UPDATE users SET staff_id = NULL WHERE staff_id = ?', [$staffId]);
        if ($userId !== null) {
            Database::query('UPDATE users SET staff_id = ? WHERE id = ?', [$staffId, $userId]);
        }
    }

    /** @return array<string,mixed> */
    private function find(int $id): array
    {
        $staff = Database::first('SELECT s.*, u.id AS user_id FROM staff s LEFT JOIN users u ON u.staff_id = s.id WHERE s.id = ?', [$id])
            ?? throw new HttpException(404);
        Rbac::assertCenter((int) $staff['center_id']);
        return $staff;
    }

    /** @return array<string,mixed> */
    private function formData(?array $staff): array
    {
        [$scopeSql, $params] = Rbac::centerFilter('u.center_id');
        // Login accounts of in-scope centers that are not yet linked to another staff record.
        $users = Database::all(
            "SELECT u.id, u.name, u.username, u.center_id, r.name_en AS role_en, r.name_bn AS role_bn
               FROM users u JOIN roles r ON r.id = u.role_id
              WHERE u.deleted_at IS NULL AND r.slug <> 'student' AND (u.staff_id IS NULL OR u.staff_id = ?) AND {$scopeSql}
              ORDER BY u.name", array_merge([$staff['id'] ?? 0], $params));
        return [
            'staff'        => $staff,
            'centers'      => $this->centers(),
            'designations' => Database::all('SELECT * FROM designations ORDER BY is_trainer DESC, name_en'),
            'users'        => $users,
        ];
    }

    /** @return list<array<string,mixed>> */
    private function centers(): array
    {
        [$sql, $params] = Rbac::centerFilter('c.id');
        return Database::all("SELECT c.id, c.code, c.name_en, c.name_bn FROM training_centers c WHERE c.is_active = 1 AND {$sql} ORDER BY c.name_en", $params);
    }

    /** @return array<string,mixed> */
    private function validated(Request $request, ?array $staff): array
    {
        $input = $request->all();
        $input['mobile'] = normalize_phone((string) ($input['mobile'] ?? ''));
        $input['monthly_salary'] = en_digits(trim((string) ($input['monthly_salary'] ?? '')));
        $data = $this->validate([
            'center_id'       => 'required|integer',
            'designation_id'  => 'required|integer',
            'name'            => 'required|max:120',
            'name_bn'         => 'nullable|max:120',
            'gender'          => 'required|in:male,female,other',
            'date_of_birth'   => 'nullable|date',
            'nid_no'          => ['nullable', 'regex:/^(\d{10}|\d{13}|\d{17})$/'],
            'mobile'          => 'required|bd_phone',
            'email'           => 'nullable|email|max:150',
            'address'         => 'nullable|max:255',
            'qualification'   => 'nullable|max:150',
            'trainer_certificate_no' => 'nullable|max:60',
            'employment_type' => 'required|in:' . implode(',', self::EMPLOYMENT),
            'joining_date'    => 'required|date',
            'leaving_date'    => 'nullable|date|after_or_equal:joining_date',
            'monthly_salary'  => 'nullable|decimal:2|min_value:0|max_value:10000000',
            'status'          => 'required|in:' . implode(',', self::STATUSES),
            'user_id'         => 'nullable|integer',
        ], $input);

        $errors = [];
        $centerId = (int) $data['center_id'];
        if (!Rbac::canAccessCenter($centerId) || Database::value('SELECT id FROM training_centers WHERE id = ?', [$centerId]) === null) {
            $errors['center_id'][] = __('users.center_not_allowed');
        }
        if (Database::value('SELECT id FROM designations WHERE id = ?', [(int) $data['designation_id']]) === null) {
            $errors['designation_id'][] = __('validation.in', ['field' => __('staff.designation')]);
        }
        if (Database::value('SELECT id FROM staff WHERE mobile = ? AND id <> ?', [$data['mobile'], $staff['id'] ?? 0]) !== null) {
            $errors['mobile'][] = __('validation.unique', ['field' => __('fields.mobile')]);
        }
        $userId = ($data['user_id'] ?? '') !== '' ? (int) $data['user_id'] : null;
        if ($userId !== null) {
            $linked = Database::first('SELECT center_id, staff_id FROM users WHERE id = ? AND deleted_at IS NULL', [$userId]);
            if ($linked === null || !Rbac::canAccessCenter($linked['center_id'] !== null ? (int) $linked['center_id'] : null)
                || ($linked['staff_id'] !== null && (int) $linked['staff_id'] !== (int) ($staff['id'] ?? 0))) {
                $errors['user_id'][] = __('staff.user_link_invalid');
            }
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        $n = static fn(string $k): mixed => ($data[$k] ?? '') !== '' ? trim((string) $data[$k]) : null;
        return [
            'center_id' => $centerId, 'designation_id' => (int) $data['designation_id'], 'name' => trim((string) $data['name']),
            'name_bn' => $n('name_bn'), 'gender' => $data['gender'], 'date_of_birth' => $n('date_of_birth'), 'nid_no' => $n('nid_no'),
            'mobile' => $data['mobile'], 'email' => $n('email') !== null ? mb_strtolower((string) $n('email')) : null,
            'address' => $n('address'), 'qualification' => $n('qualification'), 'trainer_certificate_no' => $n('trainer_certificate_no'),
            'employment_type' => $data['employment_type'], 'joining_date' => $data['joining_date'], 'leaving_date' => $n('leaving_date'),
            'monthly_salary' => $n('monthly_salary'), 'status' => $data['status'], 'user_id' => $userId,
        ];
    }
}
