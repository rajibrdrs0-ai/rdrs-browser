<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Auth;

/** GET /lang/{bn|en} — switch interface language and remember it for the user. */
final class LocaleController extends Controller
{
    public function switch(Request $request, string $code): Response
    {
        $code = in_array($code, ['bn', 'en'], true) ? $code : 'bn';
        Session::set('locale', $code);
        if (($id = Auth::id()) !== null) {
            Database::query('UPDATE users SET locale = ? WHERE id = ?', [$code, $id]);
        }
        return $this->back();
    }
}
