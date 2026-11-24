<div class="page-head"><h1>Voucher <?= e($x['voucher_no']) ?></h1><?= status_badge($x['status']) ?>
  <div class="actions"><a class="btn btn-outline-secondary btn-sm" target="_blank" href="<?= url('expenses/voucher', ['id' => $x['id']]) ?>"><i class="bi bi-printer"></i> Print voucher</a>
  <?php if ($x['status'] === 'pending' && can('expenses.approve')): ?>
    <form method="post" action="<?= url('expenses/decide') ?>" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$x['id'] ?>"><input type="hidden" name="decision" value="approve"><button class="btn btn-success btn-sm"><i class="bi bi-check2"></i> Approve</button></form>
    <form method="post" action="<?= url('expenses/decide') ?>" class="d-inline" data-confirm="Reject this voucher?" data-reason><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$x['id'] ?>"><input type="hidden" name="decision" value="reject"><button class="btn btn-outline-danger btn-sm">Reject</button></form>
  <?php endif; ?>
  <?php if (in_array($x['status'], ['pending', 'approved']) && can('expenses.cancel')): ?><form method="post" action="<?= url('expenses/cancel') ?>" class="d-inline" data-confirm="Cancel this voucher?" data-reason><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$x['id'] ?>"><button class="btn btn-outline-secondary btn-sm">Cancel voucher</button></form><?php endif; ?></div></div>
<div class="row g-3"><div class="col-lg-6"><div class="card"><div class="card-body"><dl class="kv small">
  <dt>Date</dt><dd><?= e(fmt_date($x['expense_date'])) ?></dd><dt>Center</dt><dd><?= e($x['center']) ?></dd>
  <dt>Expense head</dt><dd><?= e($x['category']) ?> › <?= e($x['head']) ?><?= $x['ah_code'] ? ' (' . e($x['ah_code'] . ' ' . $x['ah_name']) . ')' : '' ?></dd>
  <dt>Description</dt><dd><?= e($x['description']) ?></dd><dt>Paid to</dt><dd><?= e($x['paid_to']) ?></dd>
  <dt>Amount</dt><dd class="fw-bold"><?= money($x['amount']) ?><br><small><?= e(amount_in_words((float)$x['amount'])) ?></small></dd>
  <dt>Method / account</dt><dd><?= e($x['method']) ?> <?= e($x['account']) ?></dd>
  <dt>Prepared by</dt><dd><?= e($x['prepared_by_name']) ?> · <?= e(fmt_datetime($x['created_at'])) ?></dd>
  <dt>Approved by</dt><dd><?= e($x['approved_by_name']) ?> <?= e(fmt_datetime($x['approved_at'])) ?></dd>
  <?php if ($x['rejection_reason']): ?><dt>Note</dt><dd class="text-danger"><?= e($x['rejection_reason']) ?></dd><?php endif; ?>
  <?php if ($x['supporting_document']): ?><dt>Document</dt><dd><a target="_blank" href="<?= e(file_url($x['supporting_document'])) ?>"><i class="bi bi-paperclip"></i> View supporting document</a></dd><?php endif; ?>
  <?php if ($x['remarks']): ?><dt>Remarks</dt><dd><?= e($x['remarks']) ?></dd><?php endif; ?>
</dl></div></div></div>
<div class="col-lg-6"><div class="card"><div class="card-header">History</div><ul class="list-group list-group-flush small"><?php foreach ($history as $h): ?><li class="list-group-item"><b><?= e($h['uname']) ?></b> · <?= e($h['action']) ?> · <?= e(fmt_datetime($h['created_at'])) ?><br><?= e($h['description']) ?></li><?php endforeach; ?></ul></div></div></div>
