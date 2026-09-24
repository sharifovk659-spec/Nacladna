<?php $flash = $_SESSION['flash_success'] ?? null; unset($_SESSION['flash_success']); ?>
<?php if ($flash): ?><div class="alert alert-success" data-autohide><?= htmlspecialchars($flash) ?></div><?php endif; ?>

<div class="card" style="text-align:center; padding:24px;">
  <?php if (!$subscription): ?>
  <div style="font-size:48px;">⚠️</div>
  <h2 style="font-size:18px; margin-top:8px;">Подписка не найдена</h2>
  <?php else: ?>

  <?php $isPlan = ($subscription['plan'] === 'trial') ? 'Пробный период' : 'Business'; ?>
  <?php $statusColor = in_array($subscription['status'], ['trial','active']) ? 'var(--green)' : 'var(--red)'; ?>

  <div style="width:80px;height:80px;border-radius:50%;background:var(--green-light);display:flex;align-items:center;justify-content:center;font-size:36px;margin:0 auto 16px;">
    <?= $subscription['status'] === 'expired' ? '❌' : '✅' ?>
  </div>
  <h2 style="font-size:20px; font-weight:700;"><?= $isPlan ?></h2>
  <p style="margin-top:4px; color:<?= $statusColor ?>; font-weight:600;">
    <?= match($subscription['status']) {
      'trial'   => 'Пробный · ' . $daysLeft . ' дней осталось',
      'active'  => 'Активен · ' . $daysLeft . ' дней осталось',
      'expired' => 'Истёк',
      default   => $subscription['status'],
    } ?>
  </p>

  <?php if ($daysLeft <= 3 && $daysLeft > 0): ?>
  <div class="alert alert-warning" style="margin-top:12px;">
    ⚠️ Ваша подписка истекает через <?= $daysLeft ?> дней. Продлите заранее.
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>

<div class="card">
  <h3 style="font-size:16px; font-weight:700; margin-bottom:16px;">📦 Business план</h3>
  <ul style="list-style:none; display:flex; flex-direction:column; gap:10px; margin-bottom:20px;">
    <li>✅ Неограниченные накладные</li>
    <li>✅ Управление клиентами и товарами</li>
    <li>✅ PDF генерация</li>
    <li>✅ Telegram интеграция</li>
    <li>✅ Управление долгами</li>
    <li>✅ Приоритетная поддержка</li>
  </ul>
  <div style="font-size:28px; font-weight:700; color:var(--green); margin-bottom:16px;">
    100 сом. / месяц
  </div>
  <form method="POST" action="/subscription/request">
    <?= \App\Helpers\Csrf::field() ?>
    <button type="submit" class="btn btn-primary btn-full">Запросить активацию</button>
  </form>
  <p style="font-size:12px; color:#9ca3af; margin-top:8px; text-align:center;">
    Администратор активирует подписку вручную
  </p>
</div>
