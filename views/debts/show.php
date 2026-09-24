<?php if ($flash): ?><div class="alert alert-success" data-autohide><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error" data-autohide><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card" style="background:linear-gradient(135deg,#ef4444,#dc2626); color:#fff; margin-bottom:16px;">
  <div style="font-size:14px; opacity:.85;"><?= htmlspecialchars($client['name']) ?></div>
  <div style="font-size:26px; font-weight:700; margin-top:4px;"><?= number_format($totalDebt, 2) ?> с.</div>
  <div style="font-size:13px; opacity:.85; margin-top:4px;"><?= htmlspecialchars($client['phone'] ?? '') ?></div>
</div>

<!-- Unpaid invoices -->
<div class="section-heading">
  <h3>Неоплаченные накладные</h3>
</div>

<?php if (empty($invoices)): ?>
<div class="alert alert-success">Все накладные оплачены! ✅</div>
<?php else: ?>
<?php foreach ($invoices as $inv): ?>
<div class="card" style="margin-bottom:10px;">
  <div class="card-row">
    <a href="/invoices/<?= $inv['id'] ?>" style="font-weight:700; color:#111;"><?= htmlspecialchars($inv['invoice_number']) ?></a>
    <?php
    $badge = match($inv['payment_status']) { 'partial'=>'badge-partial', default=>'badge-debt' };
    $label = match($inv['payment_status']) { 'partial'=>'Частично', default=>'Долг' };
    ?>
    <span class="badge <?= $badge ?>"><?= $label ?></span>
  </div>
  <div class="card-row" style="margin-top:8px; font-size:14px;">
    <span>Долг: <strong class="text-red"><?= number_format((float)$inv['debt_amount'], 2) ?> с.</strong></span>
    <span><?= date('d.m.Y', strtotime($inv['invoice_date'])) ?></span>
  </div>

  <hr class="divider">
  <form method="POST" action="/debts/pay">
    <?= \App\Helpers\Csrf::field() ?>
    <input type="hidden" name="invoice_id" value="<?= $inv['id'] ?>">
    <input type="hidden" name="client_id" value="<?= $client['id'] ?>">
    <div style="display:flex; gap:10px; align-items:center;">
      <input type="number" name="amount" class="form-control"
             step="0.01" min="0.01" max="<?= $inv['debt_amount'] ?>"
             placeholder="Сумма оплаты" style="flex:1;">
      <select name="payment_method" class="form-control" style="flex:0 0 120px;">
        <option value="cash">Наличные</option>
        <option value="card">Карта</option>
        <option value="transfer">Перевод</option>
      </select>
      <button type="submit" class="btn btn-primary btn-sm">Принять</button>
    </div>
  </form>
</div>
<?php endforeach; ?>
<?php endif; ?>

<!-- Payment history -->
<?php if (!empty($payments)): ?>
<div class="section-heading mt-16">
  <h3>История платежей</h3>
</div>
<?php foreach ($payments as $p): ?>
<div style="display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid #f3f4f6; font-size:14px;">
  <div>
    <div><?= htmlspecialchars($p['invoice_number']) ?></div>
    <div style="color:#6b7280; font-size:12px;"><?= date('d.m.Y H:i', strtotime($p['created_at'])) ?> · <?= $p['payment_method'] ?></div>
  </div>
  <div class="text-green fw-700">+<?= number_format((float)$p['amount'], 2) ?> с.</div>
</div>
<?php endforeach; ?>
<?php endif; ?>
