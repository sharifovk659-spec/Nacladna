<?php if ($flash): ?><div class="alert alert-success" data-autohide><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error" data-autohide><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="settings-page">
  <div class="settings-head">
    <a href="/profile" class="settings-back" aria-label="Назад">←</a>
    <h1>Настройки компании</h1>
  </div>

  <form method="POST" action="/settings" enctype="multipart/form-data" class="settings-form">
    <?= \App\Helpers\Csrf::field() ?>

    <div class="card settings-logo-card">
      <label class="form-label">Логотип</label>
      <div class="settings-logo-row">
        <div class="settings-logo-preview" id="logoPreview">
          <?php if ($logoUrl): ?>
            <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Логотип">
          <?php else: ?>
            <span class="settings-logo-placeholder">🏢</span>
          <?php endif; ?>
        </div>
        <div class="settings-logo-actions">
          <input type="file" name="logo" id="logoInput" class="form-control" accept="image/jpeg,image/png,image/webp">
          <p class="form-hint">JPEG, PNG или WebP · до 2 MB</p>
          <?php if ($logoUrl): ?>
            <label class="settings-remove-logo">
              <input type="checkbox" name="remove_logo" value="1">
              Удалить текущий логотип
            </label>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="form-group">
        <label class="form-label" for="name">Название компании *</label>
        <input type="text" name="name" id="name" class="form-control" required maxlength="200"
               value="<?= htmlspecialchars((string)$company['name']) ?>">
      </div>
      <div class="form-group">
        <label class="form-label" for="owner_name">Имя владельца *</label>
        <input type="text" name="owner_name" id="owner_name" class="form-control" required maxlength="200"
               value="<?= htmlspecialchars((string)$company['owner_name']) ?>">
      </div>
      <div class="form-group">
        <label class="form-label" for="phone">Телефон</label>
        <input type="tel" name="phone" id="phone" class="form-control"
               value="<?= htmlspecialchars((string)($company['phone'] ?? '')) ?>" placeholder="+992 9XX XXX XXX">
      </div>
      <div class="form-group">
        <label class="form-label" for="address">Адрес</label>
        <textarea name="address" id="address" class="form-control" rows="2" maxlength="500"><?= htmlspecialchars((string)($company['address'] ?? '')) ?></textarea>
      </div>
    </div>

    <div class="card">
      <div class="form-group">
        <label class="form-label" for="currency">Валюта</label>
        <select name="currency" id="currency" class="form-control">
          <?php foreach ($currencies as $code => $label): ?>
            <option value="<?= htmlspecialchars($code) ?>" <?= ($company['currency'] ?? 'TJS') === $code ? 'selected' : '' ?>>
              <?= htmlspecialchars($label) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" for="language">Язык интерфейса</label>
        <select name="language" id="language" class="form-control">
          <?php foreach ($languages as $code => $label): ?>
            <option value="<?= htmlspecialchars($code) ?>" <?= ($company['language'] ?? 'ru') === $code ? 'selected' : '' ?>>
              <?= htmlspecialchars($label) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <span class="form-hint">Сохраняется для компании (полная локализация — в следующих версиях).</span>
      </div>
      <div class="form-group">
        <label class="form-label" for="timezone">Часовой пояс</label>
        <select name="timezone" id="timezone" class="form-control">
          <?php foreach ($timezones as $tz): ?>
            <option value="<?= htmlspecialchars($tz) ?>" <?= ($company['timezone'] ?? 'Asia/Dushanbe') === $tz ? 'selected' : '' ?>>
              <?= htmlspecialchars($tz) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" for="invoice_prefix">Префикс накладной</label>
        <input type="text" name="invoice_prefix" id="invoice_prefix" class="form-control" maxlength="10"
               value="<?= htmlspecialchars((string)$company['invoice_prefix']) ?>">
        <span class="form-hint">Пример: NK → NK-000001</span>
      </div>
    </div>

    <button type="submit" class="btn btn-primary btn-full settings-save">Сохранить настройки</button>
  </form>
</div>

<style>
.settings-page { max-width: 720px; margin: 0 auto; padding-bottom: 24px; }
.settings-head { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
.settings-back {
  width: 40px; height: 40px; border-radius: 10px; border: 1px solid var(--gray-200);
  display: flex; align-items: center; justify-content: center; text-decoration: none; color: var(--gray-700);
}
.settings-head h1 { font-size: 20px; font-weight: 800; }
.settings-form { display: flex; flex-direction: column; gap: 12px; }
.settings-logo-row { display: flex; flex-direction: column; gap: 12px; }
@media (min-width: 640px) {
  .settings-logo-row { flex-direction: row; align-items: flex-start; }
  .settings-head h1 { font-size: 22px; }
}
.settings-logo-preview {
  width: 96px; height: 96px; border-radius: 16px; border: 1px solid var(--gray-200);
  background: var(--gray-50); display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0;
}
.settings-logo-preview img { max-width: 100%; max-height: 100%; object-fit: contain; }
.settings-logo-placeholder { font-size: 36px; }
.settings-logo-actions { flex: 1; min-width: 0; }
.settings-remove-logo { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--red); margin-top: 8px; cursor: pointer; }
.settings-save { min-height: 48px; font-weight: 700; margin-top: 4px; }
</style>

<script>
(function () {
  const input = document.getElementById('logoInput');
  const preview = document.getElementById('logoPreview');
  if (!input || !preview) return;
  input.addEventListener('change', function () {
    const file = input.files && input.files[0];
    if (!file) return;
    const url = URL.createObjectURL(file);
    preview.innerHTML = '<img src="' + url + '" alt="Предпросмотр">';
  });
})();
</script>
