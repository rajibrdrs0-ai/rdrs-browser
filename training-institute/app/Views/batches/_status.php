<?php
/** @var string $status */
$cls = ['planned' => 'secondary', 'admission_open' => 'info', 'running' => 'success', 'completed' => 'dark', 'cancelled' => 'danger'][$status] ?? 'secondary';
?><span class="badge text-bg-<?= $cls ?>"><?= e(__('batches.status.' . $status)) ?></span>
