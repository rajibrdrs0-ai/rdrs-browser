<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;
use App\Services\Audit;

/** Upazila lookup (JSON for dependent selects) and upazila maintenance. */
final class GeoController extends Controller
{
    public function upazilasJson(Request $request): Response
    {
        $districtId = $this->intOrNull($request->query('district_id'));
        $rows = $districtId === null ? [] : Database::all(
            'SELECT id, name_en, name_bn FROM upazilas WHERE district_id = ? AND is_active = 1 ORDER BY name_en', [$districtId]);
        return $this->json(array_map(static fn(array $r): array => ['id' => (int) $r['id'], 'name' => loc($r, 'name')], $rows));
    }

    public function upazilas(Request $request): Response
    {
        $districtId = $this->intOrNull($request->query('district_id'));
        return $this->view('geo/upazilas', [
            'districts'  => Database::all('SELECT id, name_en, name_bn FROM districts ORDER BY name_en'),
            'districtId' => $districtId,
            'upazilas'   => $districtId === null ? [] : Database::all(
                'SELECT * FROM upazilas WHERE district_id = ? ORDER BY name_en', [$districtId]),
        ]);
    }

    public function storeUpazila(Request $request): Response
    {
        $data = $this->validate(['district_id' => 'required|integer', 'name_en' => 'required|max:80', 'name_bn' => 'required|max:80']);
        $districtId = (int) $data['district_id'];
        if (Database::value('SELECT id FROM districts WHERE id = ?', [$districtId]) === null) {
            throw new ValidationException(['district_id' => [__('validation.in', ['field' => __('centers.district')])]]);
        }
        if (Database::value('SELECT id FROM upazilas WHERE district_id = ? AND name_en = ?', [$districtId, trim((string) $data['name_en'])]) !== null) {
            throw new ValidationException(['name_en' => [__('validation.unique', ['field' => __('geo.upazila')])]]);
        }
        Database::query('INSERT INTO upazilas (district_id, name_en, name_bn) VALUES (?, ?, ?)',
            [$districtId, trim((string) $data['name_en']), trim((string) $data['name_bn'])]);
        Audit::log('create', 'upazila', Database::lastInsertId(), null, $data);
        Session::flash('success', __('geo.saved'));
        return $this->redirect('/geo/upazilas?district_id=' . $districtId);
    }
}
