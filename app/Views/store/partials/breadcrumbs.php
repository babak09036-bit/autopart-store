<?php /** @var array $crumbs [['name' => ..., 'url' => relative|null], ...] */ ?>
<nav aria-label="مسیر صفحه">
  <ol class="breadcrumb">
    <li><a href="">خانه</a></li>
<?php foreach ($crumbs as $c): ?>
    <?php if (!empty($c['url'])): ?>
    <li><a href="<?= e($c['url']) ?>"><?= e($c['name']) ?></a></li>
    <?php else: ?>
    <li aria-current="page"><?= e($c['name']) ?></li>
    <?php endif; ?>
<?php endforeach; ?>
  </ol>
</nav>
