<?php
/**
 * RTTI front controller. Every request goes through here: index.php?r=module/action
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/Core/bootstrap.php';

use App\Core\Auth;

if (!config('db')) {
    redirect(base_url() . 'install.php');
}

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

$route = (string)($_GET['r'] ?? 'dashboard/index');
if (!preg_match('#^([a-z]+)(?:/([a-z_]+))?$#', $route, $m)) {
    http_response_code(404);
    exit('Not found');
}
$module = $m[1];
$action = $m[2] ?? 'index';

// Routes reachable without login
$public = ['auth/login', 'auth/dologin', 'verify/index', 'verify/qr'];

if (!in_array("$module/$action", $public, true) && !Auth::check()) {
    if (is_ajax()) {
        json_response(['ok' => false, 'message' => 'Session expired. Please sign in again.'], 401);
    }
    $_SESSION['intended'] = $_SERVER['REQUEST_URI'] ?? null;
    redirect(url('auth/login'));
}

// Force password change on first login
if (Auth::check() && (int)(Auth::user()['must_change_password'] ?? 0) === 1 && !in_array($module, ['auth'], true)) {
    flash('warning', 'Please change your password before continuing.');
    redirect(url('auth/profile'));
}

$class = 'App\\Controllers\\' . ucfirst($module) . 'Controller';
if (!class_exists($class)) {
    http_response_code(404);
    \App\Core\View::render('errors/404', ['message' => 'Page not found.']);
    exit;
}
$ref = new ReflectionClass($class);
if (!$ref->hasMethod($action)) {
    http_response_code(404);
    \App\Core\View::render('errors/404', ['message' => 'Page not found.']);
    exit;
}
$method = $ref->getMethod($action);
if (!$method->isPublic() || $method->isStatic() || $method->getDeclaringClass()->getName() !== $class || str_starts_with($action, '__')) {
    http_response_code(404);
    exit('Not found');
}
$controller = new $class();
$controller->$action();
