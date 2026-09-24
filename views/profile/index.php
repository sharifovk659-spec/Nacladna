<?php if ($flash): ?><div class="alert alert-success" data-autohide><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error" data-autohide><?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- User profile card -->
<div class="card">
  <div style="display:flex; align-items:center; gap:16px; margin-bottom:16px;">
    <?php if (!empty($user['photo_url'])): ?>
    <img src="<?= htmlspecialchars($user['photo_url']) ?>" style="width:60px;height:60px;border-radius:50%;object-fit:cover;">
    <?php else: ?>
    <div style="width:60px;height:60px;border-radius:50%;background:var(--green);display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:700;color:#fff;">
      <?= mb_substr($user['first_name'], 0, 1) ?>
    </div>
    <?php endif; ?>
    <div>
      <h2 style="font-size:18px; font-weight:700;"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h2>
      <?php if ($user['telegram_username']): ?>
      <p style="color:#6b7280;">@<?= htmlspecialchars($user['telegram_username']) ?></p>
      <?php endif; ?>
      <span class="badge badge-active"><?= $_SESSION['company_role'] === 'owner' ? '👑 Владелец' : '👤 Сотрудник' ?></span>
    </div>
  </div>

  <form method="POST" action="/profile">
    <?= \App\Helpers\Csrf::field() ?>
    <div class="form-group">
      <label class="form-label">Телефон</label>
      <input type="tel" name="phone" class="form-control"
             value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="+992 9XX XXX XXX">
    </div>
    <button type="submit" class="btn btn-primary btn-full">Сохранить</button>
  </form>
</div>

<!-- Company settings (owner only) -->
<?php if ($isOwner): ?>
<div class="card" style="margin-top:4px;">
  <a href="/settings" class="list-item" style="box-shadow:none; padding:12px 0; display:flex; justify-content:space-between; align-items:center; text-decoration:none; color:inherit;">
    <span><strong>⚙️ Настройки компании</strong><br><small class="muted">Название, логотип, валюта, язык, префикс</small></span>
    <span>→</span>
  </a>
</div>
<?php endif; ?>

<!-- Links -->
<div class="card" style="margin-top:4px;">
  <a href="/subscription" class="list-item" style="box-shadow:none; padding:10px 0;">
    <span>💳 Подписка</span><span>→</span>
  </a>
  <a href="/debts" class="list-item" style="box-shadow:none; padding:10px 0;">
    <span>📊 Долги</span><span>→</span>
  </a>
</div>

<div style="margin-top:8px;">
  <form method="POST" action="/auth/logout">
    <?= \App\Helpers\Csrf::field() ?>
    <button type="submit" class="btn btn-danger btn-full">Выйти</button>
  </form>
</div>
