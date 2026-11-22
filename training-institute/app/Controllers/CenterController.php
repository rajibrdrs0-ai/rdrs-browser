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

/**
 * Training centers and their rooms/labs.
 * Listing is scoped; creating/editing centers needs center.manage (Head Office).
 * Rooms can be managed by center.manage or routine.manage inside the user's scope.
 */
final class CenterController extends Controller
{
    public function index(Request $request): Response
    {
        [$scopeSql, $params] = Rbac::centerFilter('c.id');
        $where = [$scopeSql];
        $districtId = $this->intOrNull($request->query('district_id'));
        if ($districtId !== null) {
            $where[] = 'c.district_id = ?';
            $params[] = $districtId;
        }
        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $where[] = '(c.name_en LIKE ? OR c.name_bn LIKE ? OR c.code LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like, $like);
        }
        $from = 'FROM training_centers c
                 JOIN districts d ON d.id = c.district_id
                 LEFT JOIN upazilas u ON u.id = c.upazila_id
                WHERE (' . implode(') AND (', $where) . ')';
        $result = $this->paginate(
            "c.*, d.name_en AS district_en, d.name_bn AS district_bn, u.name_en AS upazila_en, u.name_bn AS upazila_bn,
             (SELECT COUNT(*) FROM batches b WHERE b.center_id = c.id AND b.status IN ('admission_open','running')) AS active_batches,
             (SELECT COUNT(*) FROM staff s WHERE s.center_id = c.id AND s.status = 'active') AS staff_count,
             (SELECT COUNT(*) FROM students st WHERE st.center_id = c.id AND st.deleted_at IS NULL) AS student_count",
            $from, $params, "c.center_type = 'head_office' DESC, d.name_en, c.name_en"
        );
        return $this->view('centers/index', $result + [
            'districts' => $this->districtsInScope(),
            'filters'   => ['q' => $q, 'district_id' => $districtId],
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $center = $this->find((int) $id);
        return $this->view('centers/show', [
            'center'  => $center,
            'rooms'   => Database::all('SELECT * FROM rooms WHERE center_id = ? ORDER BY is_active DESC, name', [$center['id']]),
            'batches' => Database::all(
                "SELECT b.id, b.batch_code, b.name, b.status, b.start_date, b.end_date, c.name_en AS course_en, c.name_bn AS course_bn
                   FROM batches b JOIN courses c ON c.id = b.course_id
                  WHERE b.center_id = ? AND b.status IN ('planned','admission_open','running')
                  ORDER BY b.start_date", [$center['id']]),
            'staff'   => Database::all(
                "SELECT s.id, s.name, s.name_bn, s.mobile, d.name_en AS designation_en, d.name_bn AS designation_bn
                   FROM staff s JOIN designations d ON d.id = s.designation_id
                  WHERE s.center_id = ? AND s.status = 'active' ORDER BY d.is_trainer, s.name", [$center['id']]),
            'canManageRooms' => Rbac::canAny(['center.manage', 'routine.manage']),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->view('centers/form', ['center' => null, 'districts' => $this->allDistricts()]);
    }

    public function store(Request $request): Response
    {
        $data = $this->validated($request, null);
        $id = Database::transaction(function () use ($data): int {
            Database::query(
                'INSERT INTO training_centers (code, name_en, name_bn, center_type, district_id, upazila_id, address, phone, email,
                                               registration_no, seat_capacity, has_hostel, established_on, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$data['code'], $data['name_en'], $data['name_bn'], 'training_center', $data['district_id'], $data['upazila_id'],
                 $data['address'], $data['phone'], $data['email'], $data['registration_no'], $data['seat_capacity'],
                 $data['has_hostel'], $data['established_on'], $data['is_active']]
            );
            $id = Database::lastInsertId();
            // Every center gets a cash box so fee collection works from day one.
            Database::query("INSERT INTO finance_accounts (center_id, account_type, name) VALUES (?, 'cash', ?)",
                [$id, $data['code'] . ' Cash']);
            Audit::log('create', 'training_center', $id, null, $data, $id);
            return $id;
        });
        Session::flash('success', __('centers.saved', ['name' => $data['name_en']]));
        return $this->redirect('/centers/' . $id);
    }

    public function edit(Request $request, string $id): Response
    {
        return $this->view('centers/form', ['center' => $this->find((int) $id), 'districts' => $this->allDistricts()]);
    }

    public function update(Request $request, string $id): Response
    {
        $center = $this->find((int) $id);
        $data = $this->validated($request, $center);
        Database::query(
            'UPDATE training_centers SET code = ?, name_en = ?, name_bn = ?, district_id = ?, upazila_id = ?, address = ?, phone = ?,
                    email = ?, registration_no = ?, seat_capacity = ?, has_hostel = ?, established_on = ?, is_active = ? WHERE id = ?',
            [$data['code'], $data['name_en'], $data['name_bn'], $data['district_id'], $data['upazila_id'], $data['address'],
             $data['phone'], $data['email'], $data['registration_no'], $data['seat_capacity'], $data['has_hostel'],
             $data['established_on'], $data['is_active'], $center['id']]
        );
        [$old, $new] = Audit::diff($center, $data);
        if ($new !== []) {
            Audit::log('update', 'training_center', (int) $center['id'], $old, $new, (int) $center['id']);
        }
        Session::flash('success', __('centers.saved', ['name' => $data['name_en']]));
        return $this->redirect('/centers/' . $center['id']);
    }

    // ---- rooms ---------------------------------------------------------

    public function storeRoom(Request $request, string $id): Response
    {
        $center = $this->find((int) $id);
        $data = $this->validate([
            'name'      => 'required|max:80',
            'room_type' => 'required|in:classroom,lab,workshop,hall',
            'capacity'  => 'required|integer|min_value:1|max_value:500',
        ]);
        if (Database::value('SELECT id FROM rooms WHERE center_id = ? AND name = ?', [$center['id'], trim((string) $data['name'])]) !== null) {
            throw new ValidationException(['name' => [__('validation.unique', ['field' => __('centers.room_name')])]]);
        }
        Database::query('INSERT INTO rooms (center_id, name, room_type, capacity) VALUES (?, ?, ?, ?)',
            [$center['id'], trim((string) $data['name']), $data['room_type'], (int) $data['capacity']]);
        Session::flash('success', __('centers.room_saved'));
        return $this->redirect('/centers/' . $center['id']);
    }

    public function toggleRoom(Request $request, string $id, string $roomId): Response
    {
        $center = $this->find((int) $id);
        Database::query('UPDATE rooms SET is_active = 1 - is_active WHERE id = ? AND center_id = ?', [(int) $roomId, $center['id']]);
        Session::flash('success', __('centers.room_saved'));
        return $this->redirect('/centers/' . $center['id']);
    }

    // ---- helpers -------------------------------------------------------

    /** @return array<string,mixed> */
    private function find(int $id): array
    {
        $center = Database::first(
            'SELECT c.*, d.name_en AS district_en, d.name_bn AS district_bn, u.name_en AS upazila_en, u.name_bn AS upazila_bn
               FROM training_centers c JOIN districts d ON d.id = c.district_id LEFT JOIN upazilas u ON u.id = c.upazila_id
              WHERE c.id = ?', [$id]);
        if ($center === null) {
            throw new HttpException(404);
        }
        Rbac::assertCenter((int) $center['id']);
        return $center;
    }

    /** @return array<string,mixed> */
    private function validated(Request $request, ?array $center): array
    {
        $input = $request->all();
        $input['code'] = strtoupper(trim((string) ($input['code'] ?? '')));
        $input['phone'] = normalize_phone((string) ($input['phone'] ?? ''));
        $data = $this->validate([
            'code'            => ['required', 'regex:/^[A-Z0-9]{2,12}$/'],
            'name_en'         => 'required|max:150',
            'name_bn'         => 'required|max:150',
            'district_id'     => 'required|integer',
            'upazila_id'      => 'nullable|integer',
            'address'         => 'required|max:255',
            'phone'           => 'nullable|bd_phone',
            'email'           => 'nullable|email|max:150',
            'registration_no' => 'nullable|max:60',
            'seat_capacity'   => 'required|integer|min_value:0|max_value:10000',
            'established_on'  => 'nullable|date',
        ], $input);

        $errors = [];
        if (Database::value('SELECT id FROM training_centers WHERE code = ? AND id <> ?', [$data['code'], $center['id'] ?? 0]) !== null) {
            $errors['code'][] = __('validation.unique', ['field' => __('centers.code')]);
        }
        if (Database::value('SELECT id FROM districts WHERE id = ?', [(int) $data['district_id']]) === null) {
            $errors['district_id'][] = __('validation.in', ['field' => __('centers.district')]);
        }
        $upazilaId = ($data['upazila_id'] ?? '') !== '' ? (int) $data['upazila_id'] : null;
        if ($upazilaId !== null && Database::value('SELECT id FROM upazilas WHERE id = ? AND district_id = ?', [$upazilaId, (int) $data['district_id']]) === null) {
            $errors['upazila_id'][] = __('centers.upazila_mismatch');
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        return [
            'code'            => $data['code'],
            'name_en'         => trim((string) $data['name_en']),
            'name_bn'         => trim((string) $data['name_bn']),
            'district_id'     => (int) $data['district_id'],
            'upazila_id'      => $upazilaId,
            'address'         => trim((string) $data['address']),
            'phone'           => $data['phone'] !== '' ? $data['phone'] : null,
            'email'           => ($data['email'] ?? '') !== '' ? mb_strtolower((string) $data['email']) : null,
            'registration_no' => ($data['registration_no'] ?? '') !== '' ? trim((string) $data['registration_no']) : null,
            'seat_capacity'   => (int) $data['seat_capacity'],
            'has_hostel'      => (int) (bool) $request->input('has_hostel', 0),
            'established_on'  => ($data['established_on'] ?? '') !== '' ? $data['established_on'] : null,
            'is_active'       => $center === null ? 1 : (int) (bool) $request->input('is_active', 0),
        ];
    }

    /** @return list<array<string,mixed>> */
    private function allDistricts(): array
    {
        return Database::all('SELECT id, name_en, name_bn FROM districts ORDER BY name_en');
    }

    /** @return list<array<string,mixed>> */
    private function districtsInScope(): array
    {
        [$sql, $params] = Rbac::centerFilter('c.id');
        return Database::all(
            "SELECT DISTINCT d.id, d.name_en, d.name_bn FROM districts d JOIN training_centers c ON c.district_id = d.id
              WHERE {$sql} ORDER BY d.name_en", $params);
    }
}
