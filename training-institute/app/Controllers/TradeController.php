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

/** Trades / sectors (one page: list + add + inline edit). */
final class TradeController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('trades/index', [
            'trades' => Database::all(
                'SELECT t.*, (SELECT COUNT(*) FROM courses c WHERE c.trade_id = t.id) AS course_count FROM trades t ORDER BY t.is_active DESC, t.name_en'),
        ]);
    }

    public function store(Request $request): Response
    {
        $data = $this->validated($request, null);
        Database::query('INSERT INTO trades (code, name_en, name_bn, is_active) VALUES (?, ?, ?, 1)', [$data['code'], $data['name_en'], $data['name_bn']]);
        Audit::log('create', 'trade', Database::lastInsertId(), null, $data);
        Session::flash('success', __('trades.saved'));
        return $this->redirect('/trades');
    }

    public function update(Request $request, string $id): Response
    {
        $trade = Database::first('SELECT * FROM trades WHERE id = ?', [(int) $id]) ?? throw new HttpException(404);
        $data = $this->validated($request, $trade);
        $data['is_active'] = (int) (bool) $request->input('is_active', 0);
        Database::query('UPDATE trades SET code = ?, name_en = ?, name_bn = ?, is_active = ? WHERE id = ?',
            [$data['code'], $data['name_en'], $data['name_bn'], $data['is_active'], $trade['id']]);
        Audit::log('update', 'trade', (int) $trade['id'], ...Audit::diff($trade, $data));
        Session::flash('success', __('trades.saved'));
        return $this->redirect('/trades');
    }

    /** @return array<string,mixed> */
    private function validated(Request $request, ?array $trade): array
    {
        $input = $request->all();
        $input['code'] = strtoupper(trim((string) ($input['code'] ?? '')));
        $data = $this->validate([
            'code'    => ['required', 'regex:/^[A-Z0-9]{2,12}$/'],
            'name_en' => 'required|max:120',
            'name_bn' => 'required|max:120',
        ], $input);
        if (Database::value('SELECT id FROM trades WHERE code = ? AND id <> ?', [$data['code'], $trade['id'] ?? 0]) !== null) {
            throw new ValidationException(['code' => [__('validation.unique', ['field' => __('trades.code')])]]);
        }
        return ['code' => $data['code'], 'name_en' => trim((string) $data['name_en']), 'name_bn' => trim((string) $data['name_bn'])];
    }
}
