<?php
$flash = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);
$currentDebt = (float)($client['current_debt'] ?? $client['opening_debt']);
?>
<?php if ($flash): ?><div class="alert alert-success" data-autohide><?= htmlspecialchars($flash) ?></div><?php endif; ?>

<div class="card">
  <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; margin-bottom:12px;">
    <div>
      <h2 style="font-size:20px; font-weight:700;"><?= htmlspecialchars($client['name']) ?></h2>
      <p style="color:#6b7280; margin-top:4px;"><?= htmlspecialchars($client['phone'] ?? '—') ?></p>
    </div>
    <a href="/clients/<?= (int)$client['id'] ?>/edit" class="btn btn-sm btn-secondary">Изменить</a>
  </div>

  <?php if (!empty($client['address'])): ?>
  <p style="font-size:14px; color:#374151;"><strong>Адрес:</strong> <?= htmlspecialchars($client['address']) ?></p>
  <?php endif; ?>

  <hr class="divider">

  <div class="stats-grid" style="grid-template-columns:1fr 1fr 1fr; margin-bottom:0;">
    <div class="stat-card">
      <div class="stat-label">Начальный долг</div>
      <div class="stat-value"><?= number_format((float)$client['opening_debt'], 2, '.', ' ') ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Текущий долг</div>
      <div class="stat-value <?= $currentDebt > 0 ? 'red' : 'green' ?>"><?= number_format($currentDebt, 2, '.', ' ') ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Статус</div>
      <div class="stat-value" style="font-size:14px;">
        <span class="badge badge-<?= $client['status'] === 'active' ? 'active' : 'inactive' ?>">
          <?= $client['status'] === 'active' ? 'Активен' : 'Неактивен' ?>
        </span>
      </div>
    </div>
  </div>
</div>

<div class="section-heading mt-16">
  <h3>Накладные</h3>
</div>

<?php if (empty($invoices)): ?>
<div class="empty-state" style="padding:24px;">
  <div class="empty-text">Накладных пока нет</div>
  <div class="empty-sub">Модуль накладных будет доступен позже</div>
</div>
<?php else: ?>
<?php foreach ($invoices as $inv): ?>
<a href="/invoices/<?= (int)$inv['id'] ?>" class="list-item">
  <div class="list-item-icon">📋</div>
  <div class="list-item-body">
    <div class="list-item-title"><?= htmlspecialchars($inv['invoice_number']) ?></div>
    <div class="list-item-sub"><?= date('d.m.Y', strtotime($inv['invoice_date'])) ?></div>
  </div>
  <div class="list-item-right">
    <div class="fw-700"><?= number_format((float)$inv['total'], 2, '.', ' ') ?> с.</div>
  </div>
</a>
<?php endforeach; ?>
<?php endif; ?>
