<?php
/** @var array $course @var array $modules @var array $feeHeads @var array $defaultFees @var ?array $centerFees @var ?int $centerId
 *  @var array $centers @var array $overrideCenters @var array $feeHistory */
use App\Services\FeeStructure;
$this->layout('layouts/app', ['title' => loc($course, 'name')]);
$moduleHours = array_sum(array_map(static fn($m) => (int) $m['theory_hours'] + (int) $m['practical_hours'], $modules));
$shownFees = $centerFees ?? $defaultFees;
$feeByHead = [];
foreach ($shownFees as $f) { $feeByHead[$f['fee_head_id']] = $f; }
?>
<div class="page-head">
  <h1><span class="badge text-bg-secondary me-2"><?= e($course['code']) ?></span><?= e(loc($course, 'name')) ?></h1>
  <?php if (can('course.manage')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/courses/' . $course['id'] . '/edit')) ?>"><i class="bi bi-pencil me-1"></i><?= e(__('common.edit')) ?></a><?php endif; ?>
</div>
<div class="row g-3">
  <div class="col-lg-4">
    <div class="card"><div class="card-body"><dl class="row small mb-0">
      <dt class="col-6"><?= e(__('courses.trade')) ?></dt><dd class="col-6"><?= e(loc($course, 'trade')) ?></dd>
      <dt class="col-6"><?= e(__('courses.type')) ?></dt><dd class="col-6"><?= e(__('courses.types.' . $course['course_type'])) ?></dd>
      <dt class="col-6"><?= e(__('courses.nsqf_level')) ?></dt><dd class="col-6"><?= $course['nsqf_level'] !== null ? num($course['nsqf_level']) : '—' ?></dd>
      <dt class="col-6"><?= e(__('courses.duration_months')) ?></dt><dd class="col-6"><?= num($course['duration_months'], 1) ?></dd>
      <dt class="col-6"><?= e(__('courses.total_hours')) ?></dt><dd class="col-6"><?= num($course['total_hours']) ?></dd>
      <dt class="col-6"><?= e(__('courses.min_qualification')) ?></dt><dd class="col-6"><?= e($course['min_qualification'] ?? '—') ?></dd>
      <dt class="col-6"><?= e(__('courses.min_age')) ?> – <?= e(__('courses.max_age')) ?></dt><dd class="col-6"><?= $course['min_age'] !== null ? num($course['min_age']) : '—' ?> – <?= $course['max_age'] !== null ? num($course['max_age']) : '—' ?></dd>
      <dt class="col-6"><?= e(__('courses.min_attendance')) ?></dt><dd class="col-6"><?= num($course['min_attendance_percent'], 0) ?>%</dd>
      <dt class="col-6"><?= e(__('courses.pass_mark')) ?></dt><dd class="col-6"><?= num($course['pass_mark_percent'], 0) ?>%</dd>
    </dl>
    <?php if ($course['description'] !== null): ?><hr><p class="small mb-0"><?= nl2br(e($course['description'])) ?></p><?php endif; ?>
    </div></div>
  </div>

  <div class="col-lg-8" id="modules">
    <div class="card"><div class="card-header bg-body d-flex justify-content-between"><span class="fw-semibold"><i class="bi bi-list-ol me-1"></i><?= e(__('courses.modules')) ?></span>
      <span class="small <?= $moduleHours > (int) $course['total_hours'] ? 'text-danger' : 'text-body-secondary' ?>"><?= e(__('courses.hours_total', ['hours' => num($moduleHours), 'total' => num($course['total_hours'])])) ?></span></div>
      <div class="table-responsive"><table class="table table-sm mb-0">
        <thead class="table-light"><tr><th class="ps-3">#</th><th><?= e(__('courses.module_title_en')) ?></th><th class="text-center"><?= e(__('courses.theory_hours')) ?></th><th class="text-center"><?= e(__('courses.practical_hours')) ?></th><th></th></tr></thead>
        <tbody>
        <?php foreach ($modules as $m): ?>
          <tr><td class="ps-3"><?= num($m['sequence_no']) ?></td><td><?= e(loc(['title_en' => $m['title_en'], 'title_bn' => $m['title_bn']], 'title')) ?></td>
            <td class="text-center"><?= num($m['theory_hours']) ?></td><td class="text-center"><?= num($m['practical_hours']) ?></td>
            <td class="text-end pe-3"><?php if (can('course.manage')): ?>
              <form method="post" action="<?= e(url('/courses/' . $course['id'] . '/modules/' . $m['id'])) ?>" class="d-inline" data-confirm="<?= e(__('common.confirm')) ?>"><?= csrf_field() ?><?= method_field('DELETE') ?>
                <button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-trash"></i></button></form>
            <?php endif; ?></td></tr>
        <?php endforeach; ?>
        <?php if ($modules === []): ?><tr><td colspan="5" class="ps-3 text-body-secondary"><?= e(__('courses.no_modules')) ?></td></tr><?php endif; ?>
        </tbody></table></div>
      <?php if (can('course.manage')): ?>
      <form method="post" action="<?= e(url('/courses/' . $course['id'] . '/modules')) ?>" class="card-body border-top" novalidate><?= csrf_field() ?>
        <div class="row g-2 align-items-end">
          <div class="col-md-4"><?= form_input('title_en', __('courses.module_title_en'), '', ['class' => 'form-control form-control-sm', 'required' => true]) ?></div>
          <div class="col-md-4"><?= form_input('title_bn', __('courses.module_title_bn'), '', ['class' => 'form-control form-control-sm']) ?></div>
          <div class="col-4 col-md-1"><?= form_input('theory_hours', __('courses.theory_hours'), 0, ['class' => 'form-control form-control-sm', 'type' => 'number', 'min' => 0]) ?></div>
          <div class="col-4 col-md-1"><?= form_input('practical_hours', __('courses.practical_hours'), 0, ['class' => 'form-control form-control-sm', 'type' => 'number', 'min' => 0]) ?></div>
          <div class="col-4 col-md-2 d-grid"><button class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg me-1"></i><?= e(__('courses.add_module')) ?></button></div>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-12" id="fees">
    <div class="card">
      <div class="card-header bg-body d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <span class="fw-semibold"><i class="bi bi-cash-stack me-1"></i><?= e(__('courses.fees')) ?></span>
        <form method="get" class="d-flex gap-2 align-items-center">
          <label class="small text-body-secondary" for="fee_center"><?= e(__('courses.view_center')) ?></label>
          <select class="form-select form-select-sm w-auto" id="fee_center" name="center_id" data-autosubmit>
            <option value=""><?= e(__('courses.all_centers')) ?></option>
            <?php foreach ($centers as $ct): ?><option value="<?= (int) $ct['id'] ?>" <?= $centerId === (int) $ct['id'] ? 'selected' : '' ?>><?= e($ct['code']) ?> — <?= e(loc($ct, 'name')) ?></option><?php endforeach; ?>
          </select>
        </form>
      </div>
      <div class="row g-0">
        <div class="col-lg-6 border-end">
          <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th class="ps-3"><?= e(__('courses.fee_head')) ?></th><th class="text-end"><?= e(__('courses.amount')) ?></th><th><?= e(__('courses.effective_from')) ?></th></tr></thead>
            <tbody>
            <?php foreach ($shownFees as $f): ?>
              <tr><td class="ps-3"><?= e(loc($f, 'name')) ?><?php if ($f['is_optional']): ?> <span class="badge text-bg-light border"><?= e(__('courses.optional')) ?></span><?php endif; ?>
                <?php if ($centerFees !== null): ?><span class="badge text-bg-<?= $f['source'] === 'center' ? 'warning' : 'light border' ?>"><?= e(__('courses.source_' . $f['source'])) ?></span><?php endif; ?></td>
                <td class="text-end"><?= money($f['amount']) ?></td><td class="small"><?= e(fmt_date($f['effective_from'])) ?></td></tr>
            <?php endforeach; ?>
            <?php if ($shownFees === []): ?><tr><td colspan="3" class="ps-3 text-body-secondary"><?= e(__('courses.no_fees')) ?></td></tr>
            <?php else: ?><tr class="fw-semibold"><td class="ps-3"><?= e(__('courses.mandatory_total')) ?></td><td class="text-end"><?= money(FeeStructure::total($shownFees)) ?></td><td></td></tr><?php endif; ?>
            </tbody>
          </table>
          <?php if ($overrideCenters !== []): ?><p class="small text-body-secondary px-3 py-2 mb-0"><?= e(__('courses.center_fees')) ?>:
            <?php foreach ($overrideCenters as $oc): ?><a class="badge text-bg-light border text-decoration-none" href="<?= e(url('/courses/' . $course['id'], ['center_id' => $oc['id']])) ?>#fees"><?= e($oc['code']) ?></a> <?php endforeach; ?></p><?php endif; ?>
        </div>
        <?php if (can('fee_structure.manage')): ?>
        <div class="col-lg-6">
          <form method="post" action="<?= e(url('/courses/' . $course['id'] . '/fees')) ?>" class="p-3" novalidate><?= csrf_field() ?>
            <p class="small text-body-secondary"><?= e(__('courses.fee_help')) ?></p>
            <div class="row g-2 mb-2">
              <div class="col-sm-6"><?= form_select('center_id', __('courses.apply_to'), options($centers, 'name', 'id', 'code'), $centerId ?? '', ['class' => 'form-select form-select-sm', 'placeholder' => __('courses.all_centers')]) ?></div>
              <div class="col-sm-6"><?= form_input('effective_from', __('courses.effective_from'), date('Y-m-d'), ['type' => 'date', 'class' => 'form-control form-control-sm', 'required' => true]) ?></div>
            </div>
            <?= field_error('amounts') ?>
            <table class="table table-sm mb-2">
              <thead><tr><th><?= e(__('courses.fee_head')) ?></th><th style="width:40%"><?= e(__('courses.amount')) ?></th><th class="text-center"><?= e(__('courses.optional')) ?></th></tr></thead>
              <tbody>
              <?php foreach ($feeHeads as $h): $cur = $feeByHead[$h['id']] ?? null; ?>
                <tr><td><label for="amt-<?= (int) $h['id'] ?>"><?= e(loc($h, 'name')) ?></label></td>
                  <td><input class="form-control form-control-sm text-end" id="amt-<?= (int) $h['id'] ?>" name="amounts[<?= (int) $h['id'] ?>]" inputmode="decimal" value="<?= e($cur !== null ? number_format((float) $cur['amount'], 2, '.', '') : '') ?>"></td>
                  <td class="text-center"><input class="form-check-input" type="checkbox" name="optional[<?= (int) $h['id'] ?>]" value="1" aria-label="<?= e(__('courses.optional')) ?>" <?= ($cur['is_optional'] ?? ($h['code'] === 'RESIDENTIAL' ? 1 : 0)) ? 'checked' : '' ?>></td></tr>
              <?php endforeach; ?>
              </tbody>
            </table>
            <button class="btn btn-sm btn-primary"><i class="bi bi-check2 me-1"></i><?= e(__('courses.save_fees')) ?></button>
          </form>
        </div>
        <?php endif; ?>
      </div>
      <?php if ($feeHistory !== []): ?>
      <details class="border-top"><summary class="px-3 py-2 small"><?= e(__('courses.fee_history')) ?></summary>
        <div class="table-responsive"><table class="table table-sm mb-0 small">
          <?php foreach ($feeHistory as $fh): ?><tr><td class="ps-3"><?= e(fmt_date($fh['effective_from'])) ?></td><td><?= e($fh['center_code'] ?? __('courses.all_centers')) ?></td><td><?= e(loc(['head_en' => $fh['head_en'], 'head_bn' => $fh['head_bn']], 'head')) ?></td><td class="text-end pe-3"><?= money($fh['amount']) ?></td></tr><?php endforeach; ?>
        </table></div>
      </details>
      <?php endif; ?>
    </div>
  </div>
</div>
