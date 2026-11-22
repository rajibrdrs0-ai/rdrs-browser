<?php
$map = ['success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'info' => 'info'];
foreach ($map as $key => $class):
    $message = flash($key);
    if ($message === null || $message === '') { continue; }
    if (in_array($message, ['session_expired', 'session_invalid'], true)) { continue; }
?>
<div class="alert alert-<?= $class ?> alert-dismissible fade show" role="alert">
  <?= e($message) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endforeach; ?>
<?php $allErrors = errors(); if ($allErrors !== []): ?>
<div class="alert alert-danger" role="alert">
  <ul class="mb-0 ps-3">
    <?php foreach ($allErrors as $fieldErrors): foreach ((array) $fieldErrors as $msg): ?>
      <li><?= e($msg) ?></li>
    <?php endforeach; endforeach; ?>
  </ul>
</div>
<?php endif; ?>
