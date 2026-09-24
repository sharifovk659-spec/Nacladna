<?php
$roleLabel = $role === 'owner' ? 'Владелец' : ($role === 'admin' ? 'Админ' : 'Сотрудник');
$plan = $subscription['plan'] ?? '';
$status = $subscription['status'] ?? '';
$isTrial = $plan === 'trial' || $status === 'trial';
?>

<div class="welcome-card">
  <h2><?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?></h2>
  <p><?= htmlspecialchars($companyName) ?> · <?= htmlspecialchars($roleLabel) ?></p>
  <?php if ($subscription): ?>
  <p style="margin-top:8px; font-size:13px; opacity:.95;">
    <?php if ($isTrial && $daysLeft > 0): ?>
      Пробный период: <?= (int)$daysLeft ?> <?= $daysLeft === 1 ? 'день' : ($daysLeft < 5 ? 'дня' : 'дней') ?> осталось
    <?php elseif ($status === 'active' && $daysLeft > 0): ?>
      Подписка активна · <?= (int)$daysLeft ?> дней осталось
    <?php elseif (!empty($readOnly)): ?>
      Подписка истекла · только просмотр
    <?php else: ?>
      Подписка: <?= htmlspecialchars($status ?: $plan) ?>
    <?php endif; ?>
  </p>
  <?php endif; ?>
</div>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-label">Накладные</div>
    <div class="stat-value"><?= number_format((int)$stats['invoice_count']) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Клиенты</div>
    <div class="stat-value"><?= number_format((int)$stats['client_count']) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Товары</div>
    <div class="stat-value"><?= number_format((int)$stats['product_count']) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Продажи</div>
    <div class="stat-value green"><?= number_format((float)$stats['total_sales'], 2) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Оплачено</div>
    <div class="stat-value green"><?= number_format((float)$stats['total_paid'], 2) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Долг</div>
    <div class="stat-value red"><?= number_format((float)$stats['total_debt'], 2) ?></div>
  </div>
</div>

<div class="empty-state" style="margin-top:8px;">
  <div class="empty-text">Создайте первую накладную</div>
  <div class="empty-sub">Модуль накладных будет доступен в следующем этапе</div>
  <a href="/invoices/create" class="btn btn-primary mt-16" style="min-height:48px; padding:12px 20px;">
    Создать накладную
  </a>
</div>
