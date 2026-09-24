<?php
$flash = $_SESSION['flash_success'] ?? null;
$error = $_SESSION['invoice_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['invoice_error']);
function invoiceBadge(string $status): array {
    return match ($status) {
        'paid' => ['badge-paid', 'Оплачено'],
        'partial' => ['badge-partial', 'Частично'],
        'cancelled' => ['badge-secondary', 'Отменено'],
        'draft' => ['badge-secondary', 'Черновик'],
        default => ['badge-debt', 'Не оплачено'],
    };
}
?>
<?php if ($flash): ?><div class="alert alert-success" data-autohide><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error" data-autohide><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card">
  <form method="GET" action="/invoices">
    <div class="search-bar" style="margin-bottom:12px;">
      <span class="search-icon">🔍</span>
      <input type="text" name="q" value="<?= htmlspecialchars($search ?? '') ?>" placeholder="Номер, клиент или телефон...">
    </div>
    <div class="filter-grid">
      <input type="number" name="client_id" value="<?= htmlspecialchars((string)($client_id ?? 0)) ?>" class="form-control" placeholder="ID клиента">
      <input type="date" name="date_from" value="<?= htmlspecialchars($date_from ?? '') ?>" class="form-control">
      <input type="date" name="date_to" value="<?= htmlspecialchars($date_to ?? '') ?>" class="form-control">
    </div>
    <div class="status-links">
      <?php foreach (['' => 'Все', 'draft' => 'Черновик', 'unpaid' => 'Не оплачено', 'partial' => 'Частично', 'paid' => 'Оплачено', 'cancelled' => 'Отменено'] as $value => $label): ?>
        <button type="submit" name="status" value="<?= htmlspecialchars($value) ?>" class="btn btn-sm <?= ($status ?? '') === $value ? 'btn-primary' : 'btn-secondary' ?>"><?= htmlspecialchars($label) ?></button>
      <?php endforeach; ?>
    </div>
  </form>
</div>

<?php if (empty($items)): ?>
  <div class="empty-state">
    <div class="empty-icon">📋</div>
    <div class="empty-text">Накладные пока не найдены</div>
    <a href="/invoices/create" class="btn btn-primary mt-16">Создать накладную</a>
  </div>
<?php else: ?>
  <div class="mobile-list">
    <?php foreach ($items as $inv): [$badge, $label] = invoiceBadge((string)$inv['status']); ?>
      <a href="/invoices/<?= (int)$inv['id'] ?>" class="list-item">
        <div class="list-item-icon">📄</div>
        <div class="list-item-body">
          <div class="list-item-title"><?= htmlspecialchars($inv['invoice_number']) ?></div>
          <div class="list-item-sub"><?= htmlspecialchars($inv['client_name'] ?? 'Без клиента') ?> · <?= htmlspecialchars(date('d.m.Y', strtotime($inv['invoice_date']))) ?></div>
        </div>
        <div class="list-item-right">
          <div class="fw-700"><?= number_format((float)$inv['total'], 2) ?> с.</div>
          <span class="badge <?= $badge ?>"><?= htmlspecialchars($label) ?></span>
        </div>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="table-wrap desktop-only">
    <table class="data-table">
      <thead>
        <tr>
          <th>№</th>
          <th>Клиент</th>
          <th>Дата</th>
          <th>Сумма</th>
          <th>Оплачено</th>
          <th>Долг</th>
          <th>Статус</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($items as $inv): [$badge, $label] = invoiceBadge((string)$inv['status']); ?>
        <tr>
          <td><?= htmlspecialchars($inv['invoice_number']) ?></td>
          <td><?= htmlspecialchars($inv['client_name'] ?? '—') ?></td>
          <td><?= htmlspecialchars(date('d.m.Y', strtotime($inv['invoice_date']))) ?></td>
          <td class="fw-700"><?= number_format((float)$inv['total'], 2) ?> с.</td>
          <td class="text-green"><?= number_format((float)$inv['paid_amount'], 2) ?> с.</td>
          <td class="text-red"><?= number_format((float)$inv['debt_amount'], 2) ?> с.</td>
          <td><span class="badge <?= $badge ?>"><?= htmlspecialchars($label) ?></span></td>
          <td><a href="/invoices/<?= (int)$inv['id'] ?>" class="btn btn-sm btn-secondary">Открыть</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if (($last_page ?? 1) > 1): ?>
    <div class="pagination">
      <?php for ($i = 1; $i <= $last_page; $i++): ?>
        <a href="?<?= http_build_query(['q' => $search ?? '', 'status' => $status ?? '', 'client_id' => $client_id ?? 0, 'date_from' => $date_from ?? '', 'date_to' => $date_to ?? '', 'page' => $i]) ?>" class="<?= $i === ($page ?? 1) ? 'current' : '' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>

<style>
.filter-grid { display:grid; grid-template-columns:1fr; gap:10px; margin-bottom:12px; }
.status-links { display:flex; gap:8px; flex-wrap:wrap; }
.desktop-only { display:none; }
@media(min-width:768px) {
  .desktop-only { display:block; }
  .mobile-list { display:none; }
  .filter-grid { grid-template-columns:repeat(3, minmax(0, 1fr)); }
}
</style>
