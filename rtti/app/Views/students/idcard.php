<div class="idcards">
<?php foreach ($students as $s): ?>
  <div class="idcard">
    <div class="top"><img src="<?= logo_url() ?>" alt=""><b><?= e(setting('institute_name')) ?></b><span>STUDENT ID CARD</span></div>
    <?php if ($s['photo']): ?><img class="ph" src="<?= e(file_url($s['photo'])) ?>" alt=""><?php else: ?><div class="ph d-inline-flex align-items-center justify-content-center" style="font-size:30px;color:#999;margin-top:-22px;width:22mm;height:26mm;background:#eee;border-radius:4px">👤</div><?php endif; ?>
    <div class="nm"><?= e($s['name']) ?></div>
    <div style="color:#c8102e;font-weight:700;font-size:12px"><?= e($s['student_code']) ?></div>
    <div class="px-2 mt-1" style="text-align:left;line-height:1.45">
      <div><b>Course:</b> <?= e($s['course'] ?? '—') ?></div>
      <div><b>Batch:</b> <?= e($s['batch_code'] ?? '—') ?></div>
      <div><b>Center:</b> <?= e($s['center']) ?></div>
      <div><b>Mobile:</b> <?= e($s['mobile']) ?></div>
      <?php if (!empty($s['end_date'])): ?><div><b>Valid till:</b> <?= e(fmt_date($s['end_date'])) ?></div><?php endif; ?>
    </div>
    <div class="bot"><?= e(setting('organization_name')) ?> · <?= e(setting('institute_phone')) ?></div>
  </div>
<?php endforeach; ?>
</div>
