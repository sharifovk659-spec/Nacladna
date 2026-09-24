<?php
$flash = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);
$status = $status ?? '';
$search = $search ?? '';
?>
<?php if ($flash): ?><div class="alert alert-success" data-autohide><?= htmlspecialchars($flash) ?></div><?php endif; ?>

<div class="module-toolbar">
  <form method="GET" action="/clients" class="search-bar" style="flex:1; margin-bottom:0;">
    <span class="search-icon">🔍</span>
    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
           placeholder="Поиск по имени или телефону..." autocomplete="off">
    <?php if ($status !== ''): ?>
    <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
    <?php endif; ?>
  </form>
  <a href="/clients/create" class="btn btn-primary btn-sm">+ Добавить</a>
</div>

<div class="filter-chips">
  <a href="/clients?q=<?= urlencode($search) ?>" class="chip <?= $status === '' ? 'active' : '' ?>">Все</a>
  <a href="/clients?q=<?= urlencode($search) ?>&status=active" class="chip <?= $status === 'active' ? 'active' : '' ?>">Активные</a>
  <a href="/clients?q=<?= urlencode($search) ?>&status=inactive" class="chip <?= $status === 'inactive' ? 'active' : '' ?>">Неактивные</a>
</div>

<?php if (empty($items)): ?>
<div class="empty-state">
  <div class="empty-icon">👥</div>
  <div class="empty-text">Клиентов пока нет</div>
  <div class="empty-sub">Добавьте первого клиента</div>
  <a href="/clients/create" class="btn btn-primary mt-16">Добавить клиента</a>
</div>
<?php else: ?>

<div class="mobile-list">
<?php foreach ($items as $c): ?>
<a href="/clients/<?= (int)$c['id'] ?>" class="list-item">
  <div class="list-item-icon">👤</div>
  <div class="list-item-body">
    <div class="list-item-title"><?= htmlspecialchars($c['name']) ?></div>
    <div class="list-item-sub">
      <?= htmlspecialchars($c['phone'] ?? '—') ?>
      <?php if ($c['status'] !== 'active'): ?>
      · <span class="badge badge-inactive">Неактивен</span>
      <?php endif; ?>
    </div>
  </div>
  <div class="list-item-right">
    <?php if ((float)$c['current_debt'] > 0): ?>
    <div class="fw-700 text-red"><?= number_format((float)$c['current_debt'], 2, '.', ' ') ?> с.</div>
    <span class="badge badge-debt">Долг</span>
    <?php else: ?>
    <span class="badge badge-active">Без долга</span>
    <?php endif; ?>
  </div>
</a>
<?php endforeach; ?>
</div>

<div class="table-wrap desktop-only">
  <table class="data-table">
    <thead>
      <tr>
        <th>Клиент</th><th>Телефон</th><th>Адрес</th><th>Долг</th><th>Статус</th><th></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($items as $c): ?>
    <tr>
      <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
      <td><?= htmlspecialchars($c['phone'] ?? '—') ?></td>
      <td><?= htmlspecialchars($c['address'] ?? '—') ?></td>
      <td class="<?= (float)$c['current_debt'] > 0 ? 'text-red fw-700' : '' ?>">
        <?= number_format((float)$c['current_debt'], 2, '.', ' ') ?> с.
      </td>
      <td>
        <span class="badge badge-<?= $c['status'] === 'active' ? 'active' : 'inactive' ?>">
          <?= $c['status'] === 'active' ? 'Активен' : 'Неактивен' ?>
        </span>
      </td>
      <td>
        <a href="/clients/<?= (int)$c['id'] ?>" class="btn btn-sm btn-secondary">Открыть</a>
        <a href="/clients/<?= (int)$c['id'] ?>/edit" class="btn btn-sm btn-outline">Изменить</a>
      </td>
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
