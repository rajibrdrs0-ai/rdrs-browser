<?php $cancelled = $c['status'] !== 'issued'; ?>
<div class="no-print container my-2 small">
  <?php if ($cancelled): ?><div class="alert alert-danger">Cancelled on <?= e(fmt_date($c['cancelled_at'])) ?> by <?= e($c['cancelled_by_name']) ?>: <?= e($c['cancel_reason']) ?></div><?php endif; ?>
  Issued by <?= e($c['issued_by_name']) ?> · Printed <?= (int)$c['print_count'] ?> time(s)
  <?php if ($prints): ?><details><summary>Print history</summary><ul><?php foreach ($prints as $p): ?><li><?= e(fmt_datetime($p['printed_at'])) ?> – <?= e($p['uname']) ?> – <?= e($p['reason']) ?></li><?php endforeach; ?></ul></details><?php endif; ?>
  <?php if (!$cancelled && can('certificates.print')): ?><button class="btn btn-success btn-sm mt-1" id="btnPrint"><i class="bi bi-printer"></i> Print certificate (logged)</button><?php endif; ?>
</div>
<div class="cert<?= $cancelled ? ' cancelled' : '' ?>">
  <div class="cert-inner">
    <img src="<?= logo_url() ?>" style="width:84px">
    <div class="inst"><?= e(setting('institute_name')) ?></div>
    <div class="small"><?= e(setting('organization_name')) ?> · <?= e(setting('institute_address')) ?></div>
    <h1><?= e($c['heading'] ?: 'Certificate of Completion') ?></h1>
    <div class="small text-muted">Certificate No: <b><?= e($c['certificate_no']) ?></b></div>
    <div class="name"><?= e($c['student']) ?></div>
    <div class="body"><?= e($body) ?></div>
    <div class="meta">Issue date: <?= e(fmt_date($c['issue_date'])) ?><br>Student ID: <?= e($c['student_code']) ?></div>
    <div class="sigs"><div><?= e($c['signatory1_name']) ?><br><?= e($c['signatory1_title'] ?: 'Course Coordinator') ?></div><div><?= e($c['signatory2_name']) ?><br><?= e($c['signatory2_title'] ?: 'Principal') ?></div></div>
    <div class="qr"><div id="qr"></div>Scan to verify</div>
  </div>
</div>
<script src="<?= asset('vendor/qrcode.js') ?>"></script>
<script>
(function(){ const q = qrcode(0, 'M'); q.addData(<?= json_encode($verifyUrl) ?>); q.make(); document.getElementById('qr').innerHTML = q.createSvgTag({cellSize: 3, margin: 0}); })();
const btn = document.getElementById('btnPrint');
if (btn) btn.addEventListener('click', async function () {
  let reason = '';
  if (<?= (int)$c['print_count'] ?> > 0) { reason = prompt('This certificate was printed before. Reason for reprint:'); if (!reason) return; }
  const fd = new FormData(); fd.append('_csrf', <?= json_encode(csrf_token()) ?>); fd.append('id', <?= (int)$c['id'] ?>); fd.append('reason', reason);
  const r = await fetch(<?= json_encode(url('certificates/logprint')) ?>, {method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'}});
  const j = await r.json(); if (!j.ok) { alert(j.message); return; }
  window.print(); setTimeout(() => location.reload(), 800);
});
</script>
<style>@media print{@page{size:A4 landscape;margin:0}.cert{width:297mm;height:210mm;padding:12mm;margin:0}}</style>
