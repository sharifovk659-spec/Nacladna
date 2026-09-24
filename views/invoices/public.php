<?php
[$badge, $label] = match ($invoice['status'] ?? '') {
    'paid' => ['badge-paid', 'Оплачено'],
    'partial' => ['badge-partial', 'Частично'],
    'cancelled' => ['badge-secondary', 'Отменено'],
    'draft' => ['badge-secondary', 'Черновик'],
    default => ['badge-debt', 'Не оплачено'],
};
?>
<div class="public-invoice">
  <div class="card public-hero">
    <div class="card-row">
      <div>
        <div class="public-brand"><?= htmlspecialchars((string)($company['name'] ?? 'Nakladna Cloud')) ?></div>
        <div class="muted"><?= htmlspecialchars((string)($company['phone'] ?? '')) ?></div>
      </div>
      <span class="badge <?= $badge ?>"><?= htmlspecialchars($label) ?></span>
    </div>
    <h1 class="public-title"><?= htmlspecialchars((string)$invoice['invoice_number']) ?></h1>
    <p class="muted">Дата: <?= htmlspecialchars(date('d.m.Y', strtotime((string)$invoice['invoice_date']))) ?></p>
  </div>

  <div class="card">
    <h3 style="margin-bottom:10px;">Клиент</h3>
    <div class="card-row"><span>Имя</span><strong><?= htmlspecialchars((string)($invoice['client_name'] ?: 'Без клиента')) ?></strong></div>
    <div class="card-row"><span>Телефон</span><strong><?= htmlspecialchars((string)($invoice['client_phone'] ?: '—')) ?></strong></div>
  </div>

  <div class="card">
    <h3 style="margin-bottom:10px;">Товары</h3>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Товар</th>
            <th>Кол-во</th>
            <th>Цена</th>
            <th>Скидка</th>
            <th>Сумма</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $item): ?>
          <tr>
            <td><?= htmlspecialchars((string)$item['product_name']) ?></td>
            <td><?= number_format((float)$item['quantity'], 3) ?> <?= htmlspecialchars((string)$item['unit']) ?></td>
            <td><?= number_format((float)$item['unit_price'], 2) ?></td>
            <td><?= number_format((float)($item['discount'] ?? 0), 2) ?></td>
            <td class="fw-700"><?= number_format((float)$item['line_total'], 2) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-row"><span>Подытог</span><strong><?= number_format((float)$invoice['subtotal'], 2) ?> с.</strong></div>
    <div class="card-row"><span>Скидка</span><strong><?= number_format((float)$invoice['discount'], 2) ?> с.</strong></div>
    <div class="card-row"><span>Итого</span><strong><?= number_format((float)$invoice['total'], 2) ?> с.</strong></div>
    <div class="card-row"><span>Оплачено</span><strong class="text-green"><?= number_format((float)$invoice['paid_amount'], 2) ?> с.</strong></div>
    <div class="card-row"><span>Долг</span><strong class="text-red"><?= number_format((float)$invoice['debt_amount'], 2) ?> с.</strong></div>
  </div>

  <?php if (!empty($invoice['notes'])): ?>
  <div class="card">
    <strong>Примечание</strong>
    <p style="margin-top:6px;"><?= htmlspecialchars((string)$invoice['notes']) ?></p>
  </div>
  <?php endif; ?>

  <div class="card muted" style="text-align:center;">
    Публичный просмотр · Nakladna Cloud
  </div>
</div>

<style>
.public-invoice { max-width: 760px; margin: 0 auto; padding: 12px 0 24px; }
.public-hero { background: linear-gradient(180deg, #f0fdf4 0%, #ffffff 70%); }
.public-brand { font-size: 14px; font-weight: 700; color: #16a34a; }
.public-title { font-size: 24px; margin-top: 12px; color: #111827; }
.muted { color: #6b7280; font-size: 13px; }
</style>
