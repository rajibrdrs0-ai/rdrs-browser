<?php
/** @var ?array $target @var array $roles @var array $centers @var array $districts @var array $extraCenters
 *  @var array $extraDistricts @var array $overrides @var array $permissions */
$isEdit = $target !== null;
$this->layout('layouts/app', ['title' => __($isEdit ? 'users.edit' : 'users.add')]);
$v = static fn(string $key, mixed $default = '') => old($key, $isEdit ? ($target[$key] ?? $default) : $default);
$selectedRole = (string) $v('role_id');
?>
<div class="page-head">
  <h1><?= e(__($isEdit ? 'users.edit' : 'users.add')) ?></h1>
  <a href="<?= e(url('/users')) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i><?= e(__('common.back')) ?></a>
</div>

<form method="post" action="<?= e(url($isEdit ? '/users/' . $target['id'] : '/users')) ?>" novalidate>
  <?= csrf_field() ?>
  <?php if ($isEdit): ?><?= method_field('PUT') ?><?php endif; ?>
  <div class="row g-3">
    <div class="col-lg-7">
      <div class="card"><div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="name"><?= e(__('users.name')) ?> *</label>
            <input class="form-control" id="name" name="name" value="<?= e($v('name')) ?>" required maxlength="120">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="name_bn"><?= e(__('users.name_bn')) ?></label>
            <input class="form-control" id="name_bn" name="name_bn" value="<?= e($v('name_bn')) ?>" maxlength="120">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="username"><?= e(__('users.username')) ?> *</label>
            <input class="form-control" id="username" name="username" value="<?= e($v('username')) ?>" required maxlength="50" autocomplete="off">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="mobile"><?= e(__('users.mobile')) ?> *</label>
            <input class="form-control" id="mobile" name="mobile" value="<?= e($v('mobile')) ?>" required inputmode="tel" placeholder="01XXXXXXXXX">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="email"><?= e(__('users.email')) ?></label>
            <input type="email" class="form-control" id="email" name="email" value="<?= e($v('email')) ?>" maxlength="150">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="locale"><?= e(__('users.locale')) ?></label>
            <select class="form-select" id="locale" name="locale">
              <?php foreach (['bn' => 'বাংলা', 'en' => 'English'] as $code => $label): ?>
                <option value="<?= $code ?>" <?= (string) $v('locale', 'bn') === $code ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php if (!$isEdit): ?>
          <div class="col-md-6">
            <label class="form-label" for="password"><?= e(__('users.password')) ?> *</label>
            <input type="password" class="form-control" id="password" name="password" required autocomplete="new-password">
            <div class="form-text"><?= e(__('users.password_help')) ?></div>
          </div>
          <?php else: ?>
          <div class="col-md-6">
            <label class="form-label" for="status"><?= e(__('common.status')) ?></label>
            <select class="form-select" id="status" name="status">
              <?php foreach (['active', 'inactive', 'suspended'] as $s): ?>
                <option value="<?= $s ?>" <?= (string) $v('status') === $s ? 'selected' : '' ?>><?= e(__('common.' . $s)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>
        </div>
      </div></div>
    </div>

    <div class="col-lg-5">
      <div class="card"><div class="card-header bg-body fw-semibold"><i class="bi bi-shield-lock me-1"></i><?= e(__('users.role')) ?> &amp; <?= e(__('users.scope_note')) ?></div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label" for="role_id"><?= e(__('users.role')) ?> *</label>
            <select class="form-select" id="role_id" name="role_id" required>
              <option value="" data-scope=""><?= e(__('common.select')) ?></option>
              <?php foreach ($roles as $r): ?>
                <option value="<?= (int) $r['id'] ?>" data-scope="<?= e($r['scope']) ?>" <?= $selectedRole === (string) $r['id'] ? 'selected' : '' ?>>
                  <?= e(loc($r, 'name')) ?> — <?= e(__('scope.' . $r['scope'])) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3" data-scope-show="center,self">
            <label class="form-label" for="center_id"><?= e(__('users.center')) ?> *</label>
            <select class="form-select" id="center_id" name="center_id">
              <option value=""><?= e(__('common.select')) ?></option>
              <?php foreach ($centers as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= (string) $v('center_id') === (string) $c['id'] ? 'selected' : '' ?>>
                  <?= e($c['code']) ?> — <?= e(loc($c, 'name')) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3" data-scope-show="center">
            <label class="form-label" for="extra_centers"><?= e(__('users.extra_centers')) ?> <span class="text-body-secondary small">(<?= e(__('common.optional')) ?>)</span></label>
            <select class="form-select" id="extra_centers" name="extra_centers[]" multiple size="4">
              <?php foreach ($centers as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= in_array((int) $c['id'], $extraCenters, true) ? 'selected' : '' ?>><?= e($c['code']) ?> — <?= e(loc($c, 'name')) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <?php if ($districts !== []): ?>
          <div class="mb-3" data-scope-show="district">
            <label class="form-label" for="district_id"><?= e(__('users.district')) ?> *</label>
            <select class="form-select" id="district_id" name="district_id">
              <option value=""><?= e(__('common.select')) ?></option>
              <?php foreach ($districts as $d): ?>
                <option value="<?= (int) $d['id'] ?>" <?= (string) $v('district_id') === (string) $d['id'] ? 'selected' : '' ?>><?= e(loc($d, 'name')) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3" data-scope-show="district">
            <label class="form-label" for="extra_districts"><?= e(__('users.extra_districts')) ?> <span class="text-body-secondary small">(<?= e(__('common.optional')) ?>)</span></label>
            <select class="form-select" id="extra_districts" name="extra_districts[]" multiple size="4">
              <?php foreach ($districts as $d): ?>
                <option value="<?= (int) $d['id'] ?>" <?= in_array((int) $d['id'], $extraDistricts, true) ? 'selected' : '' ?>><?= e(loc($d, 'name')) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>
          <p class="small text-body-secondary mb-0"><i class="bi bi-info-circle me-1"></i><?= e(__('roles.level_help')) ?></p>
        </div>
      </div>
    </div>

    <?php if ($permissions !== []): ?>
    <div class="col-12">
      <details class="card">
        <summary class="card-header bg-body fw-semibold"><i class="bi bi-sliders me-1"></i><?= e(__('users.overrides')) ?>
          <span class="fw-normal small text-body-secondary ms-2"><?= e(__('users.overrides_help')) ?></span></summary>
        <div class="card-body perm-columns">
          <?php $current = null; foreach ($permissions as $p): ?>
            <?php if ($current !== $p['module']): $current = $p['module']; ?><div class="fw-semibold small mt-2 mb-1"><?= e(__('modules.' . $p['module'])) ?></div><?php endif; ?>
            <div class="d-flex align-items-center gap-2 small mb-1 perm-group">
              <select class="form-select form-select-sm w-auto" name="overrides[<?= (int) $p['id'] ?>]" aria-label="<?= e(loc($p, 'name')) ?>">
                <?php $mode = $overrides[(int) $p['id']] ?? 'inherit'; ?>
                <option value="inherit"><?= e(__('users.inherit')) ?></option>
                <option value="grant" <?= $mode === 'grant' ? 'selected' : '' ?>>+ <?= e(__('users.grant')) ?></option>
                <option value="revoke" <?= $mode === 'revoke' ? 'selected' : '' ?>>− <?= e(__('users.revoke')) ?></option>
              </select>
              <span><?= e(loc($p, 'name')) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </details>
    </div>
    <?php endif; ?>

    <div class="col-12">
      <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i><?= e(__('common.save')) ?></button>
      <a href="<?= e(url('/users')) ?>" class="btn btn-link"><?= e(__('common.cancel')) ?></a>
    </div>
  </div>
</form>
