<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;

final class AuthController extends Controller
{
    public function login(): void
    {
        if (Auth::check()) {
            redirect(url('dashboard'));
        }
        $this->render('auth/login', ['title' => 'Sign in'], 'guest');
    }

    public function dologin(): void
    {
        $this->requirePost();
        $username = (string)input('username', '');
        $password = (string)($_POST['password'] ?? '');
        if ($username === '' || $password === '') {
            flash('danger', 'Please enter username and password.');
            redirect(url('auth/login'));
        }
        [$ok, $msg] = Auth::attempt($username, $password);
        if (!$ok) {
            flash('danger', $msg);
            redirect(url('auth/login'));
        }
        $to = $_SESSION['intended'] ?? null;
        unset($_SESSION['intended']);
        $to = (is_string($to) && str_starts_with($to, base_url()) && !str_contains($to, 'auth/')) ? $to : url('dashboard');
        redirect($to);
    }

    public function logout(): void
    {
        $this->requirePost();
        Auth::logout();
        session_start();
        flash('success', 'You have been signed out.');
        redirect(url('auth/login'));
    }

    public function lang(): void
    {
        $l = in_array(input('l'), ['en', 'bn'], true) ? input('l') : 'en';
        $_SESSION['lang'] = $l;
        DB::update('users', ['language' => $l], 'id = :id', ['id' => Auth::id()]);
        back();
    }

    public function profile(): void
    {
        $u = Auth::user();
        if (is_post()) {
            csrf_check();
            $current = (string)($_POST['current_password'] ?? '');
            $new = (string)($_POST['new_password'] ?? '');
            $confirm = (string)($_POST['confirm_password'] ?? '');
            if (!password_verify($current, $u['password_hash'])) {
                $this->fail('Current password is incorrect.');
            }
            if ($msg = self::passwordProblem($new)) {
                $this->fail($msg);
            }
            if ($new !== $confirm) {
                $this->fail('New password and confirmation do not match.');
            }
            if (password_verify($new, $u['password_hash'])) {
                $this->fail('New password must be different from the current password.');
            }
            DB::update('users', ['password_hash' => password_hash($new, PASSWORD_DEFAULT), 'must_change_password' => 0], 'id = :id', ['id' => $u['id']]);
            Audit::log('users', 'password_change', $u['id'], 'User changed own password');
            flash('success', 'Password changed successfully.');
            redirect(url('auth/profile'));
        }
        $roles = Auth::roles();
        $centers = Auth::isHeadOffice() ? [['name' => 'All centers (Head Office)']] : DB::all('SELECT c.name FROM centers c JOIN user_centers uc ON uc.center_id = c.id WHERE uc.user_id = :u', ['u' => $u['id']]);
        $logins = DB::all('SELECT * FROM login_logs WHERE user_id = :u ORDER BY id DESC LIMIT 10', ['u' => $u['id']]);
        $this->render('auth/profile', ['title' => 'My Profile', 'u' => $u, 'roles' => $roles, 'centers' => $centers, 'logins' => $logins]);
    }

    public static function passwordProblem(string $p): ?string
    {
        if (strlen($p) < 8) {
            return 'Password must be at least 8 characters.';
        }
        if (!preg_match('/[A-Za-z]/', $p) || !preg_match('/\d/', $p)) {
            return 'Password must contain both letters and numbers.';
        }
        return null;
    }
}
