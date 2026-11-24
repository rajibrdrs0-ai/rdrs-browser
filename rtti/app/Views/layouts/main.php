<?php
use App\Core\Auth;
use App\Core\Lang;
use App\Core\DB;

$u = Auth::user();
$menu = require ROOT_PATH . '/config/menu.php';
$currentRoute = $_GET['r'] ?? 'dashboard/index';
$currentG = $_GET['g'] ?? null;
$visible = function (array $item): bool {
    if (isset($item['perm_any'])) return Auth::canAny($item['perm_any']);
    return !isset($item['perm']) || Auth::can($item['perm']);
};
$menuRoutes = [];
foreach ($menu as $mi) { foreach (($mi['children'] ?? [$mi]) as $k) { $menuRoutes[] = $k['route']; } }
$isActive = function (array $item) use ($currentRoute, $currentG, $menuRoutes): bool {
    if (isset($item['params']['g'])) return $currentRoute === $item['route'] && $currentG === $item['params']['g'];
    if ($item['route'] === $currentRoute) return true;
    [$mod, $act] = explode('/', $item['route']);
    return $mod === explode('/', $currentRoute)[0] && $act === 'index' && !in_array($currentRoute, $menuRoutes, true);
};
$unread = $u ? (int)DB::value('SELECT COUNT(*) FROM notifications WHERE is_read = 0 AND ' . \App\Controllers\NotificationsController::condition()) : 0;
$roles = array_column(Auth::roles(), 'name');
?><!doctype html>
<html lang="<?= e(Lang::current()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<title><?= e(($title ?? 'Dashboard') . ' · ' . setting('institute_short', 'RTTI')) ?></title>
<link rel="icon" href="<?= logo_url() ?>">
<link rel="stylesheet" href="<?= asset('vendor/bootstrap.min.css') ?>">
<link rel="stylesheet" href="<?= asset('vendor/bootstrap-icons.min.css') ?>">
<link rel="stylesheet" href="<?= asset('vendor/select2.min.css') ?>">
<link rel="stylesheet" href="<?= asset('vendor/dataTables.bootstrap5.min.css') ?>">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>?v=1">
</head>
<body>
<div class="app">
  <aside class="sidebar" id="sidebar">
    <a class="brand" href="<?= url('dashboard') ?>">
      <img src="<?= logo_url() ?>" alt="">
      <span><b><?= e(setting('institute_short', 'RTTI')) ?></b><small><?= e(setting('organization_name', 'RDRS Bangladesh')) ?></small></span>
    </a>
    <nav class="menu">
      <?php foreach ($menu as $i => $item):
        if (isset($item['children'])):
          $kids = array_filter($item['children'], $visible);
          if (!$kids) continue;
          $open = false;
          foreach ($kids as $k) { if ($isActive($k)) { $open = true; } }
      ?>
        <div class="menu-group">
          <button class="menu-link<?= $open ? '' : ' collapsed' ?>" data-bs-toggle="collapse" data-bs-target="#m<?= $i ?>" type="button">
            <i class="bi bi-<?= e($item['icon']) ?>"></i><span><?= e(t($item['label'])) ?></span><i class="bi bi-chevron-down caret"></i>
          </button>
          <div class="collapse<?= $open ? ' show' : '' ?>" id="m<?= $i ?>">
            <?php foreach ($kids as $k): ?>
              <a class="menu-sub<?= $isActive($k) ? ' active' : '' ?>" href="<?= url($k['route'], $k['params'] ?? []) ?>"><?= e(t($k['label'])) ?></a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php elseif ($visible($item)): ?>
        <a class="menu-link<?= explode('/', $currentRoute)[0] === explode('/', $item['route'])[0] ? ' active' : '' ?>" href="<?= url($item['route']) ?>">
          <i class="bi bi-<?= e($item['icon']) ?>"></i><span><?= e(t($item['label'])) ?></span>
        </a>
      <?php endif; endforeach; ?>
      <a class="menu-link" href="<?= url('verify/index') ?>" target="_blank"><i class="bi bi-qr-code-scan"></i><span><?= e(t('Verify Certificate')) ?></span></a>
    </nav>
  </aside>
  <div class="sidebar-backdrop" onclick="document.body.classList.remove('sb-open')"></div>

  <div class="main">
    <header class="topbar">
      <button class="btn btn-icon d-lg-none" onclick="document.body.classList.toggle('sb-open')" aria-label="Menu"><i class="bi bi-list"></i></button>
      <form class="gsearch" action="<?= url('search/index') ?>" method="get" autocomplete="off">
        <input type="hidden" name="r" value="search/index">
        <i class="bi bi-search"></i>
        <input type="search" name="q" id="globalSearch" class="form-control" placeholder="<?= e(t('Search students, receipts, certificates…')) ?>" value="<?= e($_GET['q'] ?? '') ?>">
        <div class="gsearch-results shadow" id="gsearchResults"></div>
      </form>
      <div class="ms-auto d-flex align-items-center gap-2">
        <div class="dropdown">
          <button class="btn btn-icon" data-bs-toggle="dropdown" title="Language"><i class="bi bi-translate"></i></button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="<?= url('auth/lang', ['l' => 'en']) ?>">English</a></li>
            <li><a class="dropdown-item" href="<?= url('auth/lang', ['l' => 'bn']) ?>">বাংলা</a></li>
          </ul>
        </div>
        <a class="btn btn-icon position-relative" href="<?= url('notifications/index') ?>" title="Notifications"><i class="bi bi-bell"></i>
          <?php if ($unread): ?><span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= $unread ?></span><?php endif; ?></a>
        <div class="dropdown">
          <button class="btn user-btn" data-bs-toggle="dropdown">
            <span class="avatar"><?= e(mb_strtoupper(mb_substr($u['name'] ?? '?', 0, 1))) ?></span>
            <span class="d-none d-md-inline text-start lh-sm"><b><?= e($u['name'] ?? '') ?></b><br><small class="text-muted"><?= e(implode(', ', $roles)) ?></small></span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li class="dropdown-header"><?= Auth::isHeadOffice() ? 'Head Office – all centers' : e(count(Auth::centerIds() ?? []) . ' center(s) assigned') ?></li>
            <li><a class="dropdown-item" href="<?= url('auth/profile') ?>"><i class="bi bi-person me-2"></i><?= e(t('My Profile')) ?></a></li>
            <li><hr class="dropdown-divider"></li>
            <li><form method="post" action="<?= url('auth/logout') ?>"><?= csrf_field() ?><button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i><?= e(t('Sign out')) ?></button></form></li>
          </ul>
        </div>
      </div>
    </header>

    <main class="content">
      <?php if (!empty($breadcrumbs)): ?>
        <nav aria-label="breadcrumb"><ol class="breadcrumb small mb-2">
          <li class="breadcrumb-item"><a href="<?= url('dashboard') ?>"><i class="bi bi-house"></i></a></li>
          <?php foreach ($breadcrumbs as $label => $link): ?>
            <?php if ($link): ?><li class="breadcrumb-item"><a href="<?= e($link) ?>"><?= e($label) ?></a></li>
            <?php else: ?><li class="breadcrumb-item active"><?= e($label) ?></li><?php endif; ?>
          <?php endforeach; ?>
        </ol></nav>
      <?php endif; ?>
      <?php foreach (get_flashes() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" role="alert"><?= nl2br(e($f['message'])) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
      <?php endforeach; ?>
      <?= $content ?>
    </main>
    <footer class="footer">© <?= date('Y') ?> <?= e(setting('organization_name', 'RDRS Bangladesh')) ?> · <?= e(setting('institute_name')) ?></footer>
  </div>
</div>
<div class="loading-overlay" id="loading"><div class="spinner-border text-primary"></div></div>
<script>window.APP = {base: <?= json_encode(base_url()) ?>, csrf: <?= json_encode(csrf_token()) ?>};</script>
<script src="<?= asset('vendor/jquery.min.js') ?>"></script>
<script src="<?= asset('vendor/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= asset('vendor/select2.min.js') ?>"></script>
<script src="<?= asset('vendor/sweetalert2.all.min.js') ?>"></script>
<script src="<?= asset('vendor/jquery.dataTables.min.js') ?>"></script>
<script src="<?= asset('vendor/dataTables.bootstrap5.min.js') ?>"></script>
<?php if (!empty($charts)): ?><script src="<?= asset('vendor/chart.umd.js') ?>"></script><?php endif; ?>
<script src="<?= asset('js/app.js') ?>?v=1"></script>
<?= $scripts ?? '' ?>
</body>
</html>
