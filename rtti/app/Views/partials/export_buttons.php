<?php /** $perm optional – permission required for export */ if (!empty($perm) && !can($perm)) return; ?>
<div class="btn-group">
  <a class="btn btn-outline-secondary btn-sm" href="<?= e(current_url_with(['export' => 'xls'])) ?>"><i class="bi bi-file-earmark-excel"></i> Excel</a>
  <a class="btn btn-outline-secondary btn-sm" href="<?= e(current_url_with(['export' => 'csv'])) ?>">CSV</a>
  <a class="btn btn-outline-secondary btn-sm" target="_blank" href="<?= e(current_url_with(['export' => 'print'])) ?>"><i class="bi bi-printer"></i> Print / PDF</a>
</div>
