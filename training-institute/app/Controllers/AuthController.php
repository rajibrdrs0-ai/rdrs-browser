<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;
use App\Security\Password;
use App\Services\Auth;

final class AuthController extends Controller
{
    public function showLogin(Request $request): Response
    {
        return $this->view('auth/login');
    }

    public function login(Request $request): Response
    {
        $data = $this->validate(['identifier' => 'required|max:150', 'password' => 'required|max:200']);
        $result = Auth::attempt((string) $data['identifier'], (string) $data['password'], $request);

        if (!$result['ok']) {
            Session::flash('error', __((string) $result['error'], ['minutes' => $result['minutes'] ?? 15]));
            Session::flash('old', ['identifier' => $data['identifier']]);
            return $this->redirect('/login');
        }
        $user = Auth::user();
        if ($user !== null) {
            Session::set('locale', $user['locale']);
        }
        Session::flash('success', __('auth.welcome', ['name' => $user !== null ? loc($user, 'name') : '']));
        return $this->redirect('/');
    }

    public function logout(Request $request): Response
    {
        $locale = Session::get('locale');
        Auth::logout($request);
        Session::start($request);
        Session::set('locale', $locale);
        Session::flash('success', __('auth.logged_out'));
        return $this->redirect('/login');
    }

    public function showPassword(Request $request): Response
    {
        return $this->view('auth/password', ['forced' => (int) (Auth::user()['must_change_password'] ?? 0) === 1]);
    }

    public function updatePassword(Request $request): Response
    {
        $data = $this->validate([
            'current_password' => 'required',
            'new_password'     => 'required|max:200|confirmed',
        ]);
        $user = Auth::user();
        $errors = [];
        if ($user === null || !Password::verify((string) $data['current_password'], (string) $user['password_hash'])) {
            $errors['current_password'][] = __('auth.current_wrong');
        }
        if ($data['current_password'] === $data['new_password']) {
            $errors['new_password'][] = __('auth.password_same');
        }
        foreach (Password::policyViolations((string) $data['new_password']) as $rule) {
            $errors['new_password'][] = __($rule);
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        Auth::changePassword((int) $user['id'], (string) $data['new_password']);
        Session::flash('success', __('auth.password_changed'));
        return $this->redirect('/');
    }
}
