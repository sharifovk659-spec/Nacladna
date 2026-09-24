<div class="onboarding-page">
  <div class="onboarding-card">
    <h1 class="onboarding-title">Создайте свою компанию</h1>
    <p class="onboarding-sub">Заполните данные — активируем 3 дня бесплатного доступа</p>

    <?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="/onboarding/company" enctype="multipart/form-data" id="onboardingForm" novalidate>
      <?= \App\Helpers\Csrf::field() ?>

      <div class="form-group">
        <label class="form-label" for="name">Название компании *</label>
        <input type="text" id="name" name="name" class="form-control <?= isset($fieldErrors['name']) ? 'is-invalid' : '' ?>" required
               value="<?= htmlspecialchars($old['name'] ?? '') ?>"
               placeholder="ООО «Ваша компания»" maxlength="200" autocomplete="organization">
        <?php if (!empty($fieldErrors['name'])): ?>
        <span class="field-error"><?= htmlspecialchars($fieldErrors['name']) ?></span>
        <?php endif; ?>
      </div>

      <div class="form-group">
        <label class="form-label" for="owner_name">Имя владельца *</label>
        <input type="text" id="owner_name" name="owner_name" class="form-control <?= isset($fieldErrors['owner_name']) ? 'is-invalid' : '' ?>" required
               value="<?= htmlspecialchars($old['owner_name'] ?? ($_SESSION['user']['first_name'] ?? '')) ?>"
               placeholder="Ваше имя и фамилия" maxlength="200" autocomplete="name">
        <?php if (!empty($fieldErrors['owner_name'])): ?>
        <span class="field-error"><?= htmlspecialchars($fieldErrors['owner_name']) ?></span>
        <?php endif; ?>
      </div>

      <div class="form-group">
        <label class="form-label" for="phone">Телефон *</label>
        <input type="tel" id="phone" name="phone" class="form-control <?= isset($fieldErrors['phone']) ? 'is-invalid' : '' ?>" required
               value="<?= htmlspecialchars($old['phone'] ?? '') ?>"
               placeholder="+992 9XX XXX XXX" inputmode="tel" autocomplete="tel">
        <?php if (!empty($fieldErrors['phone'])): ?>
        <span class="field-error"><?= htmlspecialchars($fieldErrors['phone']) ?></span>
        <?php else: ?>
        <span class="form-hint">Формат: +992 или 0XXXXXXXXX</span>
        <?php endif; ?>
      </div>

      <div class="form-group">
        <label class="form-label" for="address">Адрес</label>
        <textarea id="address" name="address" class="form-control" rows="2"
                  placeholder="Город, улица, дом" maxlength="500"><?= htmlspecialchars($old['address'] ?? '') ?></textarea>
      </div>

      <div class="form-group">
        <label class="form-label" for="logo">Логотип компании</label>
        <input type="file" id="logo" name="logo" class="form-control" accept="image/jpeg,image/png,image/webp">
        <span class="form-hint">Необязательно · JPEG, PNG, WebP · до 2MB</span>
      </div>

      <button type="submit" class="btn btn-primary btn-full onboarding-submit" id="onboardingSubmit">
        Продолжить
      </button>
    </form>
  </div>
</div>

<style>
.onboarding-page {
  min-height: 100vh;
  min-height: 100dvh;
  background: #ffffff;
  padding: max(16px, env(safe-area-inset-top)) 16px max(24px, env(safe-area-inset-bottom));
  display: flex;
  justify-content: center;
}
.onboarding-card {
  width: 100%;
  max-width: 560px;
  padding: 8px 0 24px;
}
.onboarding-title {
  font-size: 22px;
  font-weight: 800;
  color: #111827;
  letter-spacing: -0.02em;
  margin-top: 12px;
}
.onboarding-sub {
  font-size: 14px;
  color: #6b7280;
  margin: 6px 0 20px;
}
.field-error {
  display: block;
  margin-top: 6px;
  font-size: 13px;
  color: #ef4444;
}
.form-control.is-invalid {
  border-color: #ef4444;
}
.onboarding-submit {
  margin-top: 8px;
  font-size: 16px;
  padding: 14px;
  min-height: 52px;
}
@media (min-width: 768px) {
  .onboarding-page {
    background: #f3f4f6;
    align-items: flex-start;
    padding-top: 48px;
  }
  .onboarding-card {
    background: #ffffff;
    border-radius: 20px;
    padding: 28px 28px 32px;
    box-shadow: 0 8px 30px rgba(0,0,0,.06);
  }
}
</style>

<script>
(function () {
  const form = document.getElementById('onboardingForm');
  const btn = document.getElementById('onboardingSubmit');
  let submitted = false;
  form.addEventListener('submit', function () {
    if (submitted) {
      event.preventDefault();
      return;
    }
    submitted = true;
    btn.disabled = true;
    btn.textContent = 'Создание...';
  });
})();
</script>
