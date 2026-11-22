<?php
/** @var string $title */
use App\Services\Rbac;
$user = auth();
$menu = (array) require BASE_PATH . '/config/menu.php';
$path = $request->path();
// Only keep items the user may see; drop section headers that end up empty.
$visible = [];
$pendingSection = null;
foreach ($menu as $item) {
    if (isset($item['section'])) { $pendingSection = $item; continue; }
    if (!Rbac::canAny($item['perm'])) { continue; }
    if ($pendingSection !== null) { $visible[] = $pendingSection; $pendingSection = null; }
    $visible[] = $item;
}
// Highlight only the most specific matching item (/fees/dues should not also light up /fees).
$activeUrl = null;
foreach ($visible as $item) {
    if (!isset($item['url']) || empty($item['ready'])) { continue; }
    $u = $item['url'];
    $hit = $u === '/' ? $path === '/' : ($path === $u || str_starts_with($path, rtrim($u, '/') . '/'));
    if ($hit && ($activeUrl === null || strlen($u) > strlen($activeUrl))) { $activeUrl = $u; }
}
?><!doctype html>
<html lang="<?= e(current_locale()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? __('app.name')) ?> · <?= e(__('app.short')) ?></title>
<link rel="stylesheet" href="<?= e(asset('assets/vendor/bootstrap/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/print.css')) ?>">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
</head>
<body>
<nav class="navbar navbar-expand bg-body border-bottom sticky-top px-2 px-lg-3 app-topbar">
  <button class="btn btn-link text-body d-lg-none me-1" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar" aria-label="Menu">
    <i class="bi bi-list fs-4"></i>
  </button>
  <a class="navbar-brand fw-semibold d-flex align-items-center gap-2" href="<?= e(url('/')) ?>">
    <span class="brand-mark">R</span><span class="d-none d-sm-inline"><?= e(__('app.short')) ?></span>
  </a>
  <div class="ms-auto d-flex align-items-center gap-2">
    <?= $this->insert('partials/lang_switch') ?>
    <div class="dropdown">
      <button class="btn btn-sm btn-light border dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-person-circle"></i>
        <span class="d-none d-md-inline text-truncate user-name"><?= e(loc($user, 'name')) ?></span>
      </button>
      <ul class="dropdown-menu dropdown-menu-end shadow-sm">
        <li class="px-3 py-2 small">
          <div class="fw-semibold"><?= e(loc($user, 'name')) ?></div>
          <div class="text-body-secondary"><?= e(loc($user, 'role_name')) ?></div>
          <?php if ($user['center_name_en'] !== null): ?><div class="text-body-secondary"><?= e(loc($user, 'center_name')) ?></div><?php endif; ?>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item" href="<?= e(url('/password')) ?>"><i class="bi bi-key me-2"></i><?= e(__('auth.change_password')) ?></a></li>
        <li>
          <form method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?>
            <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i><?= e(__('common.logout')) ?></button>
          </form>
        </li>
      </ul>
    </div>
  </div>
</nav>

<div class="app-shell">
  <aside class="offcanvas-lg offcanvas-start app-sidebar border-end" tabindex="-1" id="sidebar" aria-labelledby="sidebarLabel">
    <div class="offcanvas-header d-lg-none">
      <h5 class="offcanvas-title" id="sidebarLabel"><?= e(__('app.short')) ?></h5>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0">
      <nav class="nav flex-column py-2 w-100">
        <?php foreach ($visible as $item): ?>
          <?php if (isset($item['section'])): ?>
            <div class="nav-section"><?= e(__($item['section'])) ?></div>
          <?php elseif ($item['ready']): ?>
            <?php $active = $item['url'] === $activeUrl; ?>
            <a class="nav-link<?= $active ? ' active' : '' ?>" href="<?= e(url($item['url'])) ?>">
              <i class="bi bi-<?= e($item['icon']) ?>"></i><span><?= e(__($item['label'])) ?></span>
            </a>
          <?php else: ?>
            <span class="nav-link disabled" title="<?= e(__('menu.coming_soon')) ?>">
              <i class="bi bi-<?= e($item['icon']) ?>"></i><span><?= e(__($item['label'])) ?></span>
              <span class="soon ms-auto"><?= e(__('menu.soon')) ?></span>
            </span>
          <?php endif; ?>
        <?php endforeach; ?>
      </nav>
    </div>
  </aside>

  <main class="app-content">
    <?= $this->insert('partials/flash') ?>
    <?= $this->section('content') ?>
  </main>
</div>
<script src="<?= e(asset('assets/vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('assets/js/app.js')) ?>"></script>
</body>
</html>
