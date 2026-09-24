<?php if (!empty($error)): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card form-card">
  <form method="POST" action="/clients">
    <?= \App\Helpers\Csrf::field() ?>

    <div class="form-group">
      <label class="form-label">Имя клиента *</label>
      <input type="text" name="name" class="form-control" required
             value="<?= htmlspecialchars($old['name'] ?? '') ?>" placeholder="Имя и фамилия" maxlength="200">
    </div>

    <div class="form-group">
      <label class="form-label">Телефон</label>
      <input type="tel" name="phone" class="form-control"
             value="<?= htmlspecialchars($old['phone'] ?? '') ?>" placeholder="+992 9XX XXX XXX">
      <span class="form-hint">Формат: +992 или 0XXXXXXXXX</span>
    </div>

    <div class="form-group">
      <label class="form-label">Адрес</label>
      <textarea name="address" class="form-control" rows="2" maxlength="500"
                placeholder="Город, улица"><?= htmlspecialchars($old['address'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
      <label class="form-label">Начальный долг (сом.)</label>
      <input type="number" name="opening_debt" class="form-control" step="0.01" min="0"
             value="<?= htmlspecialchars($old['opening_debt'] ?? '0') ?>">
      <span class="form-hint">DECIMAL 15,2 — укажите, если клиент уже имеет долг</span>
    </div>

    <div class="form-actions">
      <a href="/clients" class="btn btn-secondary">Отмена</a>
      <button type="submit" class="btn btn-primary">Добавить клиента</button>
    </div>
  </form>
</div>

<style>
.form-card { max-width: 560px; margin: 0 auto; }
.form-actions { display:flex; gap:10px; margin-top:8px; }
.form-actions .btn { flex:1; min-height:48px; }
@media(min-width:768px) {
  .form-actions .btn-primary { flex:2; }
}
</style>
