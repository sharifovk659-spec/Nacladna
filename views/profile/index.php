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
  <h3 style="font-size:16px; font-weight:700; margin-bottom:16px;">🏢 Настройки компании</h3>

  <?php if (!empty($company['logo_path'])): ?>
  <img src="/<?= htmlspecialchars($company['logo_path']) ?>" style="height:50px; margin-bottom:12px; border-radius:8px;">
  <?php endif; ?>

  <form method="POST" action="/profile/company" enctype="multipart/form-data">
    <?= \App\Helpers\Csrf::field() ?>

    <div class="form-group">
      <label class="form-label">Название компании *</label>
      <input type="text" name="name" class="form-control" required
             value="<?= htmlspecialchars($company['name']) ?>">
    </div>
    <div class="form-group">
      <label class="form-label">Имя владельца *</label>
      <input type="text" name="owner_name" class="form-control" required
             value="<?= htmlspecialchars($company['owner_name']) ?>">
    </div>
    <div class="form-group">
      <label class="form-label">Телефон</label>
      <input type="tel" name="phone" class="form-control"
             value="<?= htmlspecialchars($company['phone'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label class="form-label">Адрес</label>
      <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($company['address'] ?? '') ?></textarea>
    </div>
    <div class="form-group">
      <label class="form-label">Префикс накладной</label>
      <input type="text" name="invoice_prefix" class="form-control" maxlength="10"
             value="<?= htmlspecialchars($company['invoice_prefix']) ?>">
      <span class="form-hint">Пример: НКЛ → НКЛ-00001</span>
    </div>
    <div class="form-group">
      <label class="form-label">Логотип</label>
      <input type="file" name="logo" class="form-control" accept="image/*">
    </div>
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; font-size:14px; color:#6b7280; margin-bottom:16px;">
      <div>💰 Валюта: <strong>TJS (сомони)</strong></div>
      <div>🕐 Часовой пояс: <strong>Asia/Dushanbe</strong></div>
    </div>
    <button type="submit" class="btn btn-primary btn-full">Сохранить настройки</button>
  </form>
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
