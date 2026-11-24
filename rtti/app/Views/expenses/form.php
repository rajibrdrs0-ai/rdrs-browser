<div class="page-head"><h1>New expense voucher</h1></div>
<form method="post" action="<?= url('expenses/store') ?>" enctype="multipart/form-data"><?= csrf_field() ?>
<div class="card"><div class="card-body row g-3">
  <div class="col-md-3"><label class="form-label req">Expense date</label><input type="date" name="expense_date" class="form-control" value="<?= e(old('expense_date', date('Y-m-d'))) ?>" max="<?= date('Y-m-d') ?>" required></div>
  <div class="col-md-5"><label class="form-label req">Training center</label><select name="center_id" class="form-select" required><?= options($centers, old('center_id', \App\Core\Auth::defaultCenterId())) ?></select></div>
  <div class="col-md-4"><label class="form-label req">Amount (BDT)</label><input type="number" step="0.01" min="0.01" name="amount" class="form-control" value="<?= e(old('amount')) ?>" required></div>
  <div class="col-md-6"><label class="form-label req">Expense head</label><select name="expense_head_id" class="form-select s2" required><?= options($heads, old('expense_head_id')) ?></select></div>
  <div class="col-md-6"><label class="form-label">Paid to</label><input name="paid_to" class="form-control" value="<?= e(old('paid_to')) ?>"></div>
  <div class="col-12"><label class="form-label req">Description</label><input name="description" class="form-control" value="<?= e(old('description')) ?>" required maxlength="255"></div>
  <div class="col-md-4"><label class="form-label req">Payment method</label><select name="payment_method_id" class="form-select" required><?= options($methods, old('payment_method_id', 1), 'id', 'name', '') ?></select></div>
  <div class="col-md-4"><label class="form-label">Paid from account</label><select name="account_id" class="form-select"><?= options($accounts, old('account_id'), 'id', 'name', '-- Optional --') ?></select></div>
  <div class="col-md-4"><label class="form-label">Supporting document (bill / memo)</label><input type="file" name="document" class="form-control" accept=".pdf,.jpg,.jpeg,.png"></div>
  <div class="col-12"><label class="form-label">Remarks</label><input name="remarks" class="form-control" value="<?= e(old('remarks')) ?>"></div>
  <?php if (can('expenses.approve')): ?><div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="approve_now" value="1" id="apNow"><label class="form-check-label" for="apNow">Approve immediately (I am the approving authority)</label></div></div><?php endif; ?>
</div><div class="card-footer bg-white text-end"><a href="<?= url('expenses/index') ?>" class="btn btn-light">Cancel</a> <button class="btn btn-primary">Save voucher</button></div></div></form>
