<div class="doc-header">
  <img src="<?= logo_url() ?>" alt="">
  <div>
    <h1><?= e(setting('institute_name')) ?></h1>
    <div class="small"><?= e(setting('organization_name')) ?> · <?= e(setting('institute_address')) ?></div>
    <?php if (setting('institute_phone') || setting('institute_email')): ?><div class="small"><?= e(trim(setting('institute_phone') . '  ' . setting('institute_email'))) ?></div><?php endif; ?>
  </div>
</div>
