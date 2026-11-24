<div class="page-head"><h1>Notifications</h1><div class="actions"><form method="post" action="<?= url('notifications/read') ?>"><?= csrf_field() ?><button class="btn btn-outline-secondary btn-sm">Mark all as read</button></form></div></div>
<div class="card"><ul class="list-group list-group-flush">
<?php foreach ($rows as $n): ?><li class="list-group-item d-flex gap-2 <?= $n['is_read'] ? '' : 'bg-light' ?>"><i class="bi bi-<?= $n['is_read'] ? 'bell' : 'bell-fill text-primary' ?>"></i>
  <div class="flex-grow-1"><b><?= e($n['title']) ?></b><div class="small"><?= e($n['message']) ?></div><small class="text-muted"><?= e(fmt_datetime($n['created_at'])) ?></small></div>
  <?php if ($n['link']): ?><form method="post" action="<?= url('notifications/read') ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$n['id'] ?>"><button class="btn btn-sm btn-outline-primary">Open</button></form><?php endif; ?></li><?php endforeach; ?>
<?php if (!$rows): ?><li class="list-group-item text-muted">No notifications.</li><?php endif; ?></ul></div>
