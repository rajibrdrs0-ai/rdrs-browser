<?php
declare(strict_types=1);

namespace App\Security;

use App\Core\Config;
use App\Core\Request;
use App\Core\Session;

/**
 * Synchronizer-token CSRF protection.
 * One random token per session, compared in constant time.
 * Rotated on login/logout via Csrf::rotate().
 */
final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public static function token(): string
    {
        $token = Session::get(self::SESSION_KEY);
        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            Session::set(self::SESSION_KEY, $token);
        }
        return $token;
    }

    public static function rotate(): string
    {
        Session::forget(self::SESSION_KEY);
        return self::token();
    }

    public static function field(): string
    {
        $name = (string) Config::get('security.csrf.field', '_token');
        return '<input type="hidden" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
            . '" value="' . self::token() . '">';
    }

    public static function verify(Request $request): bool
    {
        $expected = Session::get(self::SESSION_KEY);
        if (!is_string($expected) || $expected === '') {
            return false;
        }
        $field = (string) Config::get('security.csrf.field', '_token');
        $header = (string) Config::get('security.csrf.header', 'X-CSRF-TOKEN');
        $provided = $request->input($field) ?? $request->header($header);
        return is_string($provided) && hash_equals($expected, $provided);
    }
}
