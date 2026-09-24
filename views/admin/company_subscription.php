<?php
use App\Models\Subscription;
$daysLeft = Subscription::daysRemaining($subscription);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Подписка — <?= htmlspecialchars($company['name']) ?></title>
<link rel="stylesheet" href="/assets/css/main.css">
<style>
.admin-layout { display:flex; min-height:100vh; }
.admin-sidebar { width:220px; background:#1f2937; color:#fff; padding:20px 0; flex-shrink:0; }
.admin-sidebar a { display:block; padding:10px 20px; color:#d1d5db; text-decoration:none; font-size:14px; }
.admin-main { flex:1; padding:16px; background:#f9fafb; }
.sub-row-request { background: #fffbeb; }
.sub-row-request td { font-weight: 500; }
</style>
</head>
<body>
<div class="admin-layout">
  <div class="admin-sidebar">
    <a href="/admin/companies">← Компании</a>
  </div>
  <div class="admin-main">
    <h2><?= htmlspecialchars($company['name']) ?></h2>
    <p class="muted">Владелец: <?= htmlspecialchars(trim(($company['first_name'] ?? '') . ' ' . ($company['last_name'] ?? ''))) ?></p>

    <div class="card" style="margin:16px 0;">
      <h3>Текущая подписка</h3>
      <?php if ($subscription): ?>
        <p><strong><?= htmlspecialchars(Subscription::planLabel($subscription)) ?></strong></p>
        <p>Статус: <?= htmlspecialchars($subscription['status']) ?> · <?= (int)$daysLeft ?> дн.</p>
        <p>До: <?= !empty($subscription['ends_at']) ? date('d.m.Y H:i', strtotime((string)$subscription['ends_at'])) : '—' ?></p>
      <?php else: ?>
        <p>Нет активной записи</p>
      <?php endif; ?>
    </div>

    <?php
    $pendingRequest = null;
    foreach ($history as $hr) {
        if (($hr['action'] ?? '') === 'renewal_requested') {
            $pendingRequest = $hr;
            break;
        }
    }
    ?>
    <?php if ($pendingRequest): ?>
    <div class="card" style="margin:16px 0;border:2px solid #fbbf24;background:#fffbeb;">
      <h3 style="margin-bottom:8px;">⏳ Последний запрос активации</h3>
      <p style="font-size:15px;font-weight:700;">
        <?= (int)($pendingRequest['period_months'] ?? 0) ?> мес.
        <?php if (isset($pendingRequest['price_som']) && $pendingRequest['price_som'] !== null): ?>
          · <?= htmlspecialchars(Subscription::formatPriceSom((float)$pendingRequest['price_som'])) ?>
        <?php endif; ?>
      </p>
      <p class="muted" style="font-size:13px;"><?= date('d.m.Y H:i', strtotime((string)$pendingRequest['created_at'])) ?></p>
      <?php if (!empty($pendingRequest['notes'])): ?>
        <p style="font-size:13px;"><?= htmlspecialchars((string)$pendingRequest['notes']) ?></p>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="card">
      <h3>История</h3>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>Дата</th><th>Действие</th><th>План</th><th>Период</th><th>Цена</th><th>До</th><th>Примечание</th></tr></thead>
          <tbody>
          <?php foreach ($history as $row): ?>
            <tr class="<?= ($row['action'] ?? '') === 'renewal_requested' ? 'sub-row-request' : '' ?>">
              <td><?= date('d.m.Y H:i', strtotime((string)$row['created_at'])) ?></td>
              <td><?= htmlspecialchars(Subscription::actionLabel((string)$row['action'])) ?></td>
              <td><?= htmlspecialchars((string)$row['plan']) ?></td>
              <td><?= $row['period_months'] ? (int)$row['period_months'] . ' мес.' : '—' ?></td>
              <td><?= isset($row['price_som']) && $row['price_som'] !== null && $row['price_som'] !== ''
                ? htmlspecialchars(Subscription::formatPriceSom((float)$row['price_som']))
                : '—' ?></td>
              <td><?= !empty($row['ends_at']) ? date('d.m.Y', strtotime((string)$row['ends_at'])) : '—' ?></td>
              <td><?= htmlspecialchars((string)($row['notes'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</body>
</html>
