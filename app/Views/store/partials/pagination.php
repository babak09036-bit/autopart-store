<?php
/** @var array $pagination  @var string $basePath  (crawlable ?page=N links) */
if (($pagination['pages'] ?? 0) <= 1) {
    return;
}
$sep = str_contains($basePath, '?') ? '&' : '?';
$link = static fn (int $n) => $n === 1 ? $basePath : $basePath . $sep . 'page=' . $n;
?>
<nav class="pager" aria-label="صفحه‌بندی">
  <?php if ($pagination['page'] > 1): ?><a class="pager__btn" href="<?= e($link($pagination['page'] - 1)) ?>" rel="prev"><?= icon('chevron-right', 'icon--sm') ?>قبلی</a><?php endif; ?>
  <?php for ($i = 1; $i <= $pagination['pages']; $i++): ?>
    <?php if ($i === $pagination['page']): ?><span class="pager__num" aria-current="page"><?= e(fa_digits($i)) ?></span>
    <?php else: ?><a class="pager__num" href="<?= e($link($i)) ?>"><?= e(fa_digits($i)) ?></a><?php endif; ?>
  <?php endfor; ?>
  <?php if ($pagination['page'] < $pagination['pages']): ?><a class="pager__btn" href="<?= e($link($pagination['page'] + 1)) ?>" rel="next">بعدی<?= icon('chevron-left', 'icon--sm') ?></a><?php endif; ?>
</nav>
