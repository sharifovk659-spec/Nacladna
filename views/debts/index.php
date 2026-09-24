<div class="card" style="background:linear-gradient(135deg,#ef4444,#dc2626); color:#fff; margin-bottom:16px;">
  <div style="font-size:14px; opacity:.85;">Общий долг</div>
  <div style="font-size:28px; font-weight:700; margin-top:4px;"><?= number_format($totalDebt, 2) ?> с.</div>
</div>

<div class="search-bar">
  <span class="search-icon">🔍</span>
  <form method="GET" action="/debts">
    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Поиск должника...">
  </form>
</div>

<?php if (empty($debtors)): ?>
<div class="empty-state">
  <div class="empty-icon">✅</div>
  <div class="empty-text">Долгов нет!</div>
  <div class="empty-sub">Все клиенты рассчитались</div>
</div>
<?php else: ?>
<?php foreach ($debtors as $d): ?>
<a href="/debts/<?= $d['id'] ?>" class="list-item">
  <div class="list-item-icon" style="background:#fee2e2;">👤</div>
  <div class="list-item-body">
    <div class="list-item-title"><?= htmlspecialchars($d['name']) ?></div>
    <div class="list-item-sub"><?= htmlspecialchars($d['phone'] ?? '—') ?></div>
  </div>
  <div class="list-item-right">
    <div class="fw-700 text-red"><?= number_format((float)$d['total_debt'], 2) ?> с.</div>
    <span class="badge badge-debt">Долг</span>
  </div>
</a>
<?php endforeach; ?>
<?php endif; ?>
