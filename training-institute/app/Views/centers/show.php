<?php
/** @var array $center @var array $rooms @var array $batches @var array $staff @var bool $canManageRooms */
$this->layout('layouts/app', ['title' => loc($center, 'name')]);
$roomTypes = ['classroom', 'lab', 'workshop', 'hall'];
?>
<div class="page-head">
  <h1><span class="badge text-bg-secondary me-2"><?= e($center['code']) ?></span><?= e(loc($center, 'name')) ?></h1>
  <div class="d-flex gap-2">
    <?php if (can('batch.manage') && $center['center_type'] === 'training_center'): ?><a class="btn btn-sm btn-primary" href="<?= e(url('/batches/create', ['center_id' => $center['id']])) ?>"><i class="bi bi-plus-lg me-1"></i><?= e(__('batches.add')) ?></a><?php endif; ?>
    <?php if (can('center.manage')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/centers/' . $center['id'] . '/edit')) ?>"><i class="bi bi-pencil me-1"></i><?= e(__('common.edit')) ?></a><?php endif; ?>
  </div>
</div>
<div class="row g-3">
  <div class="col-lg-5">
    <div class="card h-100"><div class="card-header bg-body fw-semibold"><?= e(__('centers.details')) ?></div>
      <div class="card-body"><dl class="row small mb-0">
        <dt class="col-5"><?= e(__('centers.type')) ?></dt><dd class="col-7"><?= e(__('centers.' . $center['center_type'])) ?></dd>
        <dt class="col-5"><?= e(__('centers.district')) ?></dt><dd class="col-7"><?= e(current_locale() === 'bn' ? $center['district_bn'] : $center['district_en']) ?></dd>
        <dt class="col-5"><?= e(__('centers.upazila')) ?></dt><dd class="col-7"><?= e($center['upazila_en'] !== null ? (current_locale() === 'bn' ? $center['upazila_bn'] : $center['upazila_en']) : '—') ?></dd>
        <dt class="col-5"><?= e(__('centers.address')) ?></dt><dd class="col-7"><?= e($center['address']) ?></dd>
        <dt class="col-5"><?= e(__('centers.phone')) ?></dt><dd class="col-7"><?= e($center['phone'] ?? '—') ?></dd>
        <dt class="col-5"><?= e(__('centers.email')) ?></dt><dd class="col-7"><?= e($center['email'] ?? '—') ?></dd>
        <dt class="col-5"><?= e(__('centers.registration_no')) ?></dt><dd class="col-7"><?= e($center['registration_no'] ?? '—') ?></dd>
        <dt class="col-5"><?= e(__('centers.seat_capacity')) ?></dt><dd class="col-7"><?= num($center['seat_capacity']) ?></dd>
        <dt class="col-5"><?= e(__('centers.has_hostel')) ?></dt><dd class="col-7"><?= e(__((int) $center['has_hostel'] === 1 ? 'common.yes' : 'common.no')) ?></dd>
        <dt class="col-5"><?= e(__('centers.established_on')) ?></dt><dd class="col-7"><?= e(fmt_date($center['established_on'])) ?></dd>
      </dl></div></div>
  </div>
  <div class="col-lg-7">
    <div class="card h-100"><div class="card-header bg-body fw-semibold"><i class="bi bi-door-open me-1"></i><?= e(__('centers.rooms')) ?></div>
      <div class="table-responsive"><table class="table table-sm mb-0">
        <thead class="table-light"><tr><th class="ps-3"><?= e(__('centers.room_name')) ?></th><th><?= e(__('centers.room_type')) ?></th><th class="text-center"><?= e(__('centers.capacity')) ?></th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rooms as $r): ?>
          <tr class="<?= (int) $r['is_active'] === 1 ? '' : 'text-body-secondary' ?>">
            <td class="ps-3"><?= e($r['name']) ?></td><td><?= e(__('centers.room_types.' . $r['room_type'])) ?></td><td class="text-center"><?= num($r['capacity']) ?></td>
            <td class="text-end pe-3"><?php if ($canManageRooms): ?>
              <form method="post" action="<?= e(url('/centers/' . $center['id'] . '/rooms/' . $r['id'] . '/toggle')) ?>" class="d-inline"><?= csrf_field() ?>
                <button class="btn btn-sm btn-link p-0"><?= e(__((int) $r['is_active'] === 1 ? 'users.deactivate' : 'users.activate')) ?></button></form>
            <?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if ($rooms === []): ?><tr><td colspan="4" class="ps-3 text-body-secondary"><?= e(__('centers.no_rooms')) ?></td></tr><?php endif; ?>
        </tbody></table></div>
      <?php if ($canManageRooms): ?>
      <form method="post" action="<?= e(url('/centers/' . $center['id'] . '/rooms')) ?>" class="card-body border-top" novalidate><?= csrf_field() ?>
        <div class="row g-2 align-items-end">
          <div class="col-12 col-sm-5"><?= form_input('name', __('centers.room_name'), '', ['class' => 'form-control form-control-sm', 'required' => true, 'maxlength' => 80]) ?></div>
          <div class="col-6 col-sm-3"><?= form_select('room_type', __('centers.room_type'), array_combine($roomTypes, array_map(static fn($t) => __('centers.room_types.' . $t), $roomTypes)), 'classroom', ['class' => 'form-select form-select-sm', 'placeholder' => false]) ?></div>
          <div class="col-3 col-sm-2"><?= form_input('capacity', __('centers.capacity'), 25, ['type' => 'number', 'min' => 1, 'class' => 'form-control form-control-sm']) ?></div>
          <div class="col-3 col-sm-2 d-grid"><button class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg"></i></button></div>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="card"><div class="card-header bg-body fw-semibold"><i class="bi bi-collection me-1"></i><?= e(__('centers.running_batches')) ?></div>
      <div class="list-group list-group-flush">
        <?php foreach ($batches as $b): ?>
          <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-2" href="<?= e(url('/batches/' . $b['id'])) ?>">
            <span><span class="fw-semibold"><?= e($b['batch_code']) ?></span> · <?= e(loc($b, 'course')) ?><br><span class="small text-body-secondary"><?= e(fmt_date($b['start_date'])) ?> – <?= e(fmt_date($b['end_date'])) ?></span></span>
            <?= $this->insert('batches/_status', ['status' => $b['status']]) ?>
          </a>
        <?php endforeach; ?>
        <?php if ($batches === []): ?><div class="list-group-item text-body-secondary"><?= e(__('common.no_records')) ?></div><?php endif; ?>
      </div></div>
  </div>
  <div class="col-lg-5">
    <div class="card"><div class="card-header bg-body fw-semibold"><i class="bi bi-person-vcard me-1"></i><?= e(__('centers.staff_list')) ?></div>
      <ul class="list-group list-group-flush">
        <?php foreach ($staff as $s): ?>
          <li class="list-group-item d-flex justify-content-between"><span><?= e(loc($s, 'name')) ?></span><span class="small text-body-secondary"><?= e(current_locale() === 'bn' ? $s['designation_bn'] : $s['designation_en']) ?></span></li>
        <?php endforeach; ?>
        <?php if ($staff === []): ?><li class="list-group-item text-body-secondary"><?= e(__('common.no_records')) ?></li><?php endif; ?>
      </ul></div>
  </div>
</div>
