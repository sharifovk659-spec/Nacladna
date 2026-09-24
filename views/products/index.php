<?php
$flash = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);
$status = $status ?? '';
$search = $search ?? '';
?>
<?php if ($flash): ?><div class="alert alert-success" data-autohide><?= htmlspecialchars($flash) ?></div><?php endif; ?>

<div class="module-toolbar">
  <form method="GET" action="/products" class="search-bar" style="flex:1; margin-bottom:0;">
    <span class="search-icon">🔍</span>
    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
           placeholder="Поиск по названию, SKU или штрихкоду..." autocomplete="off">
    <?php if ($status !== ''): ?>
    <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
    <?php endif; ?>
  </form>
  <a href="/products/create" class="btn btn-primary btn-sm">+ Добавить</a>
</div>

<div class="filter-chips">
  <a href="/products?q=<?= urlencode($search) ?>" class="chip <?= $status === '' ? 'active' : '' ?>">Все</a>
  <a href="/products?q=<?= urlencode($search) ?>&status=active" class="chip <?= $status === 'active' ? 'active' : '' ?>">Активные</a>
  <a href="/products?q=<?= urlencode($search) ?>&status=inactive" class="chip <?= $status === 'inactive' ? 'active' : '' ?>">Неактивные</a>
</div>

<?php if (empty($items)): ?>
<div class="empty-state">
  <div class="empty-icon">📦</div>
  <div class="empty-text">Товаров пока нет</div>
  <div class="empty-sub">Добавьте первый товар</div>
  <a href="/products/create" class="btn btn-primary mt-16">Добавить товар</a>
</div>
<?php else: ?>

<div class="mobile-list">
<?php foreach ($items as $p): ?>
<a href="/products/<?= (int)$p['id'] ?>/edit" class="list-item">
  <div class="list-item-icon" style="background:<?= $p['status'] === 'active' ? 'var(--green-light)' : 'var(--gray-100)' ?>;">📦</div>
  <div class="list-item-body">
    <div class="list-item-title"><?= htmlspecialchars($p['name']) ?></div>
    <div class="list-item-sub">
      <?= htmlspecialchars($p['unit']) ?> ·
      Остаток: <?= number_format((float)$p['stock_quantity'], 3, '.', ' ') ?>
      <?php if (\App\Models\Product::isOutOfStock($p['stock_quantity'])): ?>
      <span class="badge badge-debt">Нет</span>
      <?php elseif (\App\Models\Product::isLowStock($p['stock_quantity'])): ?>
      <span class="badge badge-partial">Мало</span>
      <?php endif; ?>
    </div>
  </div>
  <div class="list-item-right">
    <div class="fw-700"><?= number_format((float)$p['sale_price'], 2, '.', ' ') ?> с.</div>
    <span class="badge badge-<?= $p['status'] === 'active' ? 'active' : 'inactive' ?>">
      <?= $p['status'] === 'active' ? 'Актив.' : 'Неакт.' ?>
    </span>
  </div>
</a>
<?php endforeach; ?>
</div>

<div class="table-wrap desktop-only">
  <table class="data-table">
    <thead>
      <tr>
        <th>Товар</th><th>SKU</th><th>Штрихкод</th><th>Ед.</th>
        <th>Остаток</th><th>Закуп</th><th>Продажа</th><th>Статус</th><th></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($items as $p): ?>
    <tr>
      <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
      <td><?= htmlspecialchars($p['sku'] ?? '—') ?></td>
      <td><?= htmlspecialchars($p['barcode'] ?? '—') ?></td>
      <td><?= htmlspecialchars($p['unit']) ?></td>
      <td class="<?= \App\Models\Product::isOutOfStock($p['stock_quantity']) ? 'text-red fw-700' : '' ?>">
        <?= number_format((float)$p['stock_quantity'], 3, '.', ' ') ?>
        <?php if (\App\Models\Product::isLowStock($p['stock_quantity'])): ?>
        <span class="badge badge-partial">Мало</span>
        <?php elseif (\App\Models\Product::isOutOfStock($p['stock_quantity'])): ?>
        <span class="badge badge-debt">Нет</span>
        <?php endif; ?>
      </td>
      <td><?= $p['purchase_price'] !== null && $p['purchase_price'] !== '' ? number_format((float)$p['purchase_price'], 2, '.', ' ') . ' с.' : '—' ?></td>
      <td class="fw-700"><?= number_format((float)$p['sale_price'], 2, '.', ' ') ?> с.</td>
      <td>
        <span class="badge badge-<?= $p['status'] === 'active' ? 'active' : 'inactive' ?>">
          <?= $p['status'] === 'active' ? 'Активен' : 'Неактивен' ?>
        </span>
      </td>
      <td><a href="/products/<?= (int)$p['id'] ?>/edit" class="btn btn-sm btn-secondary">Изменить</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php if ($last_page > 1): ?>
<div class="pagination">
  <?php for ($i = 1; $i <= $last_page; $i++): ?>
  <a href="?q=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&page=<?= $i ?>"
     class="<?= $i === $page ? 'current' : '' ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<style>
.module-toolbar { display:flex; gap:10px; align-items:center; margin-bottom:12px; }
.filter-chips { display:flex; gap:8px; margin-bottom:14px; flex-wrap:wrap; }
.chip {
  display:inline-flex; align-items:center; padding:8px 12px; border-radius:999px;
  background:#f3f4f6; color:#4b5563; text-decoration:none; font-size:13px; font-weight:600;
  min-height:36px;
}
.chip.active { background:#dcfce7; color:#166534; }
.desktop-only { display:none; }
@media(min-width:768px) {
  .desktop-only { display:block; }
  .mobile-list { display:none; }
}
</style>
