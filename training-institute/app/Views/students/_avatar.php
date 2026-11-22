<?php /** @var array $s @var int $size */ $size ??= 40; ?>
<?php if (!empty($s['photo_path'])): ?>
  <img src="<?= e(url('/students/' . $s['id'] . '/photo')) ?>" alt="" class="rounded-circle object-fit-cover border" width="<?= $size ?>" height="<?= $size ?>" loading="lazy">
<?php else: ?>
  <span class="rounded-circle bg-body-secondary d-inline-flex align-items-center justify-content-center text-body-secondary border" style="width:<?= $size ?>px;height:<?= $size ?>px"><i class="bi bi-person"></i></span>
<?php endif; ?>
