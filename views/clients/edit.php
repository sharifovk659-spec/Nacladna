<?php if (!empty($error)): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card form-card">
  <form method="POST" action="/clients/<?= (int)$client['id'] ?>">
    <?= \App\Helpers\Csrf::field() ?>

    <div class="form-group">
      <label class="form-label">Имя клиента *</label>
      <input type="text" name="name" class="form-control" required
             value="<?= htmlspecialchars($client['name']) ?>" maxlength="200">
    </div>

    <div class="form-group">
      <label class="form-label">Телефон</label>
      <input type="tel" name="phone" class="form-control"
             value="<?= htmlspecialchars($client['phone'] ?? '') ?>">
    </div>

    <div class="form-group">
      <label class="form-label">Адрес</label>
      <textarea name="address" class="form-control" rows="2" maxlength="500"><?= htmlspecialchars($client['address'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
      <label class="form-label">Начальный долг (сом.)</label>
      <input type="number" name="opening_debt" class="form-control" step="0.01" min="0"
             value="<?= htmlspecialchars(number_format((float)$client['opening_debt'], 2, '.', '')) ?>">
    </div>

    <div class="form-group">
      <label class="form-label">Статус</label>
      <select name="status" class="form-control">
        <option value="active"   <?= $client['status'] === 'active'   ? 'selected' : '' ?>>Активен</option>
        <option value="inactive" <?= $client['status'] === 'inactive' ? 'selected' : '' ?>>Неактивен</option>
      </select>
    </div>

    <div class="form-actions">
      <a href="/clients/<?= (int)$client['id'] ?>" class="btn btn-secondary">Отмена</a>
      <button type="submit" class="btn btn-primary">Сохранить</button>
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
