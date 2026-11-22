<div class="btn-group btn-group-sm" role="group" aria-label="<?= e(__('common.language')) ?>">
  <a href="<?= e(url('/lang/bn')) ?>" class="btn <?= current_locale() === 'bn' ? 'btn-primary' : 'btn-outline-primary' ?>">বাংলা</a>
  <a href="<?= e(url('/lang/en')) ?>" class="btn <?= current_locale() === 'en' ? 'btn-primary' : 'btn-outline-primary' ?>">English</a>
</div>
