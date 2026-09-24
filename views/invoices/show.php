<?php
$flash = $_SESSION['flash_success'] ?? null;
$error = $_SESSION['invoice_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['invoice_error']);
[$badge, $label] = match ($invoice['status']) {
    'paid' => ['badge-paid', 'Оплачено'],
    'partial' => ['badge-partial', 'Частично'],
    'cancelled' => ['badge-secondary', 'Отменено'],
    'draft' => ['badge-secondary', 'Черновик'],
    default => ['badge-debt', 'Не оплачено'],
};
$qtySum = 0.0;
foreach ($items as $it) {
    $qtySum += (float)($it['quantity'] ?? 0);
}
$qtyIsInt = abs($qtySum - round($qtySum)) < 0.0005;
$qtyLabel = $qtyIsInt ? (string)(int)round($qtySum) : rtrim(rtrim(number_format($qtySum, 3, '.', ''), '0'), '.');
$dateTs = strtotime((string)($invoice['created_at'] ?? $invoice['invoice_date'] ?? 'now'));
$dateText = date('d.m.Y H:i', $dateTs ?: time());
$clientName = trim((string)($invoice['client_name'] ?? '')) ?: 'Без клиента';
$clientPhone = trim((string)($invoice['client_phone'] ?? '')) ?: '—';
$total = (float)$invoice['total'];
$paid = (float)$invoice['paid_amount'];
$debt = (float)$invoice['debt_amount'];
$id = (int)$invoice['id'];
?>
<?php if ($flash): ?><div class="alert alert-success" data-autohide><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error" data-autohide><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="inv-show">
  <div class="inv-show-head">
    <div class="inv-show-title-row">
      <a href="/invoices" class="inv-back" aria-label="Назад">←</a>
      <div class="inv-show-title">
        <span class="inv-show-kicker">Накладная</span>
        <strong>№ <?= htmlspecialchars($invoice['invoice_number']) ?></strong>
      </div>
      <span class="badge <?= $badge ?> inv-status"><?= htmlspecialchars($label) ?></span>
    </div>

    <div class="inv-meta">
      <div>
        <div class="inv-meta-label">Клиент</div>
        <div class="inv-meta-value"><?= htmlspecialchars($clientName) ?></div>
        <div class="inv-meta-sub"><?= htmlspecialchars($clientPhone) ?></div>
      </div>
      <div class="inv-meta-right">
        <div class="inv-meta-label">Дата</div>
        <div class="inv-meta-value"><?= htmlspecialchars($dateText) ?></div>
      </div>
    </div>
  </div>

  <div class="inv-items">
    <?php foreach ($items as $item):
      $q = (float)$item['quantity'];
      $qTxt = abs($q - round($q)) < 0.0005 ? (string)(int)round($q) : rtrim(rtrim(number_format($q, 3, '.', ''), '0'), '.');
      ?>
      <div class="inv-item">
        <div class="inv-item-ico">📦</div>
        <div class="inv-item-body">
          <div class="inv-item-top">
            <div class="inv-item-name"><?= htmlspecialchars($item['product_name']) ?></div>
            <div class="inv-item-sum"><?= number_format((float)$item['line_total'], 2, '.', ' ') ?> с.</div>
          </div>
          <div class="inv-item-sub"><?= htmlspecialchars($item['unit']) ?></div>
          <div class="inv-item-calc"><?= htmlspecialchars($qTxt) ?> × <?= number_format((float)$item['unit_price'], 2, '.', ' ') ?> с.</div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="inv-summary">
    <div class="inv-sum-row"><span>Товаров</span><strong><?= htmlspecialchars($qtyLabel) ?> шт.</strong></div>
    <div class="inv-sum-row"><span>Сумма</span><strong><?= number_format($total, 2, '.', ' ') ?> с.</strong></div>
    <div class="inv-sum-row"><span>Оплачено</span><strong><?= number_format($paid, 2, '.', ' ') ?> с.</strong></div>
    <div class="inv-sum-row"><span>Долг</span><strong class="text-red"><?= number_format($debt, 2, '.', ' ') ?> с.</strong></div>
  </div>

  <?php if (!empty($invoice['notes'])): ?>
    <div class="inv-notes">
      <div class="inv-meta-label">Примечание</div>
      <div><?= htmlspecialchars($invoice['notes']) ?></div>
    </div>
  <?php endif; ?>

  <?php if (!empty($qrImageDataUri) && !empty($publicShareUrl) && ($invoice['status'] ?? '') !== 'draft' && ($invoice['status'] ?? '') !== 'cancelled'): ?>
  <div class="inv-qr">
    <div class="inv-meta-label">Публичная ссылка (QR)</div>
    <div class="inv-qr-body">
      <img src="<?= htmlspecialchars($qrImageDataUri) ?>" alt="QR код накладной" class="inv-qr-img" width="160" height="160">
      <div class="inv-qr-meta">
        <p class="inv-qr-hint">Клиент может отсканировать код и открыть накладную без входа в систему.</p>
        <input type="text" class="inv-qr-url" readonly value="<?= htmlspecialchars($publicShareUrl) ?>" id="publicShareUrl">
        <button type="button" class="btn btn-secondary btn-full inv-qr-copy" onclick="copyPublicUrl()">Копировать ссылку</button>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div class="inv-actions">
    <a href="/invoices/<?= $id ?>/pdf" target="_blank" class="btn btn-primary inv-pdf-btn">📄 Скачать PDF</a>
    <button type="button" class="inv-icon-btn" onclick="shareInvoice()" title="Поделиться">↗</button>
    <a href="/invoices/<?= $id ?>/print" target="_blank" class="inv-icon-btn" title="Печать">🖨</a>
  </div>

  <div class="inv-more">
    <?php if ($invoice['status'] !== 'cancelled'): ?>
      <a href="/invoices/<?= $id ?>/edit" class="btn btn-secondary btn-full">Редактировать</a>
      <form method="POST" action="/invoices/<?= $id ?>/duplicate">
        <?= \App\Helpers\Csrf::field() ?>
        <button type="submit" class="btn btn-secondary btn-full">Дублировать</button>
      </form>
      <form method="POST" action="/invoices/<?= $id ?>/cancel" onsubmit="return confirm('Отменить накладную и вернуть остатки?')">
        <?= \App\Helpers\Csrf::field() ?>
        <button type="submit" class="btn btn-secondary btn-full">Отменить</button>
      </form>
    <?php else: ?>
      <form method="POST" action="/invoices/<?= $id ?>/duplicate">
        <?= \App\Helpers\Csrf::field() ?>
        <button type="submit" class="btn btn-secondary btn-full">Дублировать</button>
      </form>
    <?php endif; ?>
    <?php if ($debt > 0 && !empty($invoice['client_id']) && $invoice['status'] !== 'cancelled'): ?>
      <a href="/debts/<?= (int)$invoice['client_id'] ?>" class="btn btn-secondary btn-full">Управление долгом</a>
    <?php endif; ?>
    <form method="POST" action="/invoices/<?= $id ?>/delete" onsubmit="return confirm('Удалить накладную?')">
      <?= \App\Helpers\Csrf::field() ?>
      <button type="submit" class="btn btn-secondary btn-full">Удалить</button>
    </form>
  </div>
</div>

<style>
.inv-show { max-width:720px; margin:0 auto; }
.inv-show-head { background:#fff; border-radius:16px; padding:14px 14px 16px; box-shadow:0 1px 4px rgba(0,0,0,.05); margin-bottom:12px; }
.inv-show-title-row { display:flex; align-items:flex-start; gap:10px; margin-bottom:16px; }
.inv-back {
  width:36px; height:36px; border-radius:10px; border:1px solid #e5e7eb; background:#fff;
  display:flex; align-items:center; justify-content:center; text-decoration:none; color:#374151; font-size:18px; flex-shrink:0;
}
.inv-show-title { flex:1; min-width:0; }
.inv-show-kicker { display:block; font-size:13px; color:#6b7280; font-weight:600; }
.inv-show-title strong { font-size:17px; word-break:break-word; }
.inv-status { flex-shrink:0; margin-top:2px; }
.inv-meta { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.inv-meta-label { font-size:12px; color:#9ca3af; font-weight:600; margin-bottom:4px; }
.inv-meta-value { font-size:15px; font-weight:700; color:#111827; word-break:break-word; }
.inv-meta-sub { font-size:13px; color:#6b7280; margin-top:2px; }
.inv-meta-right { text-align:right; }
.inv-items { background:#fff; border-radius:16px; padding:4px 0; box-shadow:0 1px 4px rgba(0,0,0,.05); margin-bottom:12px; }
.inv-item { display:grid; grid-template-columns:44px 1fr; gap:10px; padding:12px 14px; border-bottom:1px solid #f3f4f6; }
.inv-item:last-child { border-bottom:0; }
.inv-item-ico {
  width:44px; height:44px; border-radius:10px; background:#f3f4f6;
  display:flex; align-items:center; justify-content:center; font-size:20px;
}
.inv-item-top { display:flex; justify-content:space-between; gap:10px; align-items:flex-start; }
.inv-item-name { font-weight:700; font-size:15px; color:#111827; word-break:break-word; }
.inv-item-sum { font-weight:800; font-size:15px; white-space:nowrap; }
.inv-item-sub, .inv-item-calc { font-size:13px; color:#9ca3af; margin-top:2px; }
.inv-summary {
  background:#fff; border-radius:16px; padding:14px; box-shadow:0 1px 4px rgba(0,0,0,.05); margin-bottom:12px;
}
.inv-sum-row {
  display:flex; justify-content:space-between; align-items:center; gap:12px;
  padding:8px 0; font-size:15px; color:#4b5563;
}
.inv-sum-row strong { color:#111827; font-size:15px; }
.inv-notes {
  background:#fff; border-radius:16px; padding:14px; margin-bottom:12px;
  box-shadow:0 1px 4px rgba(0,0,0,.05); font-size:14px; color:#374151;
}
.inv-actions { display:grid; grid-template-columns:1fr 48px 48px; gap:10px; margin-bottom:12px; }
.inv-pdf-btn { min-height:48px; font-weight:700; display:flex; align-items:center; justify-content:center; gap:8px; }
.inv-icon-btn {
  width:48px; height:48px; border-radius:12px; border:1px solid #e5e7eb; background:#fff;
  display:flex; align-items:center; justify-content:center; text-decoration:none; color:#16a34a;
  font-size:18px; cursor:pointer;
}
.inv-more { display:grid; gap:8px; }
.inv-qr {
  background:#fff; border-radius:16px; padding:14px; margin-bottom:12px;
  box-shadow:0 1px 4px rgba(0,0,0,.05);
}
.inv-qr-body { display:flex; flex-direction:column; align-items:center; gap:12px; margin-top:10px; }
.inv-qr-img { border-radius:12px; border:1px solid #e5e7eb; background:#fff; }
.inv-qr-meta { width:100%; }
.inv-qr-hint { font-size:13px; color:#6b7280; margin:0 0 10px; line-height:1.4; }
.inv-qr-url {
  width:100%; font-size:12px; padding:10px 12px; border-radius:10px; border:1px solid #e5e7eb;
  background:#f9fafb; color:#374151; margin-bottom:8px;
}
@media (min-width:768px) {
  .inv-qr-body { flex-direction:row; align-items:flex-start; }
  .inv-qr-meta { flex:1; }
}
@media (min-width:768px) {
  .inv-show { padding:8px 0 24px; }
  .inv-show-head, .inv-items, .inv-summary, .inv-notes { padding-left:18px; padding-right:18px; }
  .inv-show-title strong { font-size:20px; }
  .inv-item { grid-template-columns:52px 1fr; padding:14px 18px; }
  .inv-item-ico { width:52px; height:52px; }
  .inv-actions { max-width:420px; }
}
</style>

<script>
function copyPublicUrl() {
  const el = document.getElementById('publicShareUrl');
  if (!el) return;
  el.select();
  el.setSelectionRange(0, 99999);
  navigator.clipboard.writeText(el.value).then(() => alert('Ссылка скопирована')).catch(() => {});
}
async function shareInvoice() {
  const res = await fetch('/api/invoices/<?= $id ?>/share');
  const data = await res.json();
  if (!res.ok) {
    alert(data.error || 'Не удалось получить ссылку');
    return;
  }
  const url = data.share_url;
  if (navigator.share) {
    try {
      await navigator.share({title: <?= json_encode($invoice['invoice_number'], JSON_UNESCAPED_UNICODE) ?>, url});
      return;
    } catch (e) {}
  }
  await navigator.clipboard.writeText(url);
  alert('Ссылка скопирована');
}
</script>
