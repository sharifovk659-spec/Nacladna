<div class="card join-card">
  <h1 style="font-size:22px;margin-bottom:8px;">Приглашение</h1>
  <p>Компания <strong><?= htmlspecialchars($companyName) ?></strong> приглашает вас как <strong><?= htmlspecialchars(\App\Services\PermissionRegistry::ROLE_LABELS[$role] ?? $role) ?></strong>.</p>
  <p class="muted">Имя: <?= htmlspecialchars($displayName) ?></p>
  <a href="/mini-app?invite=1" class="btn btn-primary btn-full" style="margin-top:16px;">Войти через Telegram</a>
  <p class="muted" style="font-size:12px;margin-top:12px;">Откройте в приложении Telegram на телефоне сотрудника.</p>
</div>
<style>
.join-card { max-width:440px; margin:24px auto; padding:20px; text-align:center; }
</style>
