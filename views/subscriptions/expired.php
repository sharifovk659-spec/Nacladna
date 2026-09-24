<div class="auth-page">
  <div class="auth-box">
    <?php if (!empty($flashError)): ?>
      <div class="alert alert-error" style="margin-bottom:12px;text-align:left;"><?= htmlspecialchars((string)$flashError) ?></div>
    <?php endif; ?>
    <div style="font-size:64px; margin-bottom:16px;">🔒</div>
    <h1 class="auth-title">Подписка истекла</h1>
    <p class="auth-sub">Ваш пробный период или подписка завершились. Данные сохранены.</p>

    <div style="background:#fef2f2; border-radius:12px; padding:16px; margin:16px 0; text-align:left;">
      <p style="font-size:14px; color:#dc2626; font-weight:600;">Что доступно:</p>
      <ul style="list-style:none; margin-top:8px; display:flex; flex-direction:column; gap:6px; font-size:14px; color:#374151;">
        <li>✅ Просмотр накладных</li>
        <li>✅ Просмотр клиентов</li>
        <li>✅ Просмотр товаров</li>
        <li>❌ Создание новых записей</li>
        <li>❌ Редактирование</li>
      </ul>
    </div>

    <a href="/subscription" class="btn btn-primary btn-full" style="margin-bottom:12px;">
      💳 Продлить подписку
    </a>
    <a href="/dashboard" class="btn btn-secondary btn-full">Перейти к просмотру</a>
  </div>
</div>
